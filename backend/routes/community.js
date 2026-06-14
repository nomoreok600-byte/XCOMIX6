"use strict";

const express = require("express");
const { query, pool } = require("../db");
const { requireAuth } = require("../lib/auth");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

function userCard(r) {
  return {
    id: String(r.id),
    username: r.username,
    avatar_url: r.avatar_url || "",
    banner_url: r.banner_url || "",
    bio: r.bio || "",
    created_at: r.created_at,
  };
}

// ---- Community directory ----
router.get("/users", wrap(async (req, res) => {
  const q = (req.query.q || "").toString().trim();
  const params = {};
  let where = "";
  if (q) { where = "WHERE u.username LIKE :q"; params.q = `%${q}%`; }
  const rows = await query(
    `SELECT u.id, u.username, u.avatar_url, u.banner_url, u.bio, u.created_at,
            (SELECT COUNT(*) FROM follows f WHERE f.following_id = u.id) AS followers,
            (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comments
     FROM users u ${where} ORDER BY followers DESC, u.id ASC LIMIT 60`,
    params
  );
  res.json({
    data: rows.map((r) => ({
      ...userCard(r),
      followers: Number(r.followers),
      comments: Number(r.comments),
    })),
  });
}));

// ---- Leaderboard: rank by an activity score ----
router.get("/leaderboard", wrap(async (_req, res) => {
  const rows = await query(
    `SELECT u.id, u.username, u.avatar_url, u.created_at,
            (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comments,
            (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) AS reviews,
            (SELECT COUNT(*) FROM follows f WHERE f.following_id = u.id) AS followers,
            (SELECT COUNT(*) FROM library l WHERE l.user_id = u.id) AS library
     FROM users u
     ORDER BY (
       (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) * 2 +
       (SELECT COUNT(*) FROM reviews r WHERE r.user_id = u.id) * 3 +
       (SELECT COUNT(*) FROM follows f WHERE f.following_id = u.id) * 5 +
       (SELECT COUNT(*) FROM library l WHERE l.user_id = u.id)
     ) DESC, u.id ASC
     LIMIT 50`
  );
  res.json({
    data: rows.map((r, i) => {
      const comments = Number(r.comments), reviews = Number(r.reviews),
        followers = Number(r.followers), library = Number(r.library);
      return {
        rank: i + 1,
        id: String(r.id),
        username: r.username,
        avatar_url: r.avatar_url || "",
        comments, reviews, followers, library,
        score: comments * 2 + reviews * 3 + followers * 5 + library,
      };
    }),
  });
}));

// ---- Public profile ----
router.get("/profile/:username", wrap(async (req, res) => {
  const rows = await query("SELECT * FROM users WHERE username = :u LIMIT 1", {
    u: req.params.username,
  });
  if (!rows.length) return res.status(404).json({ error: "User not found" });
  const u = rows[0];
  const viewerId = req.user && req.user.id;
  const isSelf = viewerId && Number(viewerId) === Number(u.id);
  const visible = u.profile_public || isSelf;

  const stats = await query(
    `SELECT
      (SELECT COUNT(*) FROM follows f WHERE f.following_id = :id) AS followers,
      (SELECT COUNT(*) FROM follows f WHERE f.follower_id = :id) AS following,
      (SELECT COUNT(*) FROM comments c WHERE c.user_id = :id) AS comments,
      (SELECT COUNT(*) FROM reviews r WHERE r.user_id = :id) AS reviews,
      (SELECT COUNT(*) FROM library l WHERE l.user_id = :id) AS library`,
    { id: u.id }
  );

  let isFollowing = false;
  if (viewerId) {
    const f = await query(
      "SELECT 1 FROM follows WHERE follower_id = :me AND following_id = :id LIMIT 1",
      { me: viewerId, id: u.id }
    );
    isFollowing = f.length > 0;
  }

  let library = [];
  if (visible) {
    library = await query(
      `SELECT l.status, m.id AS manga_id, m.slug, m.title, m.cover_url
       FROM library l JOIN mangas m ON m.id = l.manga_id
       WHERE l.user_id = :id ORDER BY l.created_at DESC LIMIT 24`,
      { id: u.id }
    );
  }

  res.json({
    profile: { ...userCard(u), theme: u.theme, profile_public: Boolean(u.profile_public) },
    is_self: Boolean(isSelf),
    is_following: isFollowing,
    profile_visible: Boolean(visible),
    stats: {
      followers: Number(stats[0].followers),
      following: Number(stats[0].following),
      comments: Number(stats[0].comments),
      reviews: Number(stats[0].reviews),
      library: Number(stats[0].library),
    },
    library: library.map((r) => ({
      status: r.status,
      manga: { id: String(r.manga_id), slug: r.slug, title: r.title, cover_url: r.cover_url || "" },
    })),
  });
}));

// ---- Follow / unfollow ----
router.post("/follow/:userId", requireAuth, wrap(async (req, res) => {
  const target = Number(req.params.userId);
  if (target === Number(req.user.id)) return res.status(400).json({ error: "Cannot follow yourself" });
  await pool.execute(
    "INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)",
    [req.user.id, target]
  );
  res.json({ ok: true, following: true });
}));

router.delete("/follow/:userId", requireAuth, wrap(async (req, res) => {
  await query("DELETE FROM follows WHERE follower_id = :me AND following_id = :id", {
    me: req.user.id,
    id: req.params.userId,
  });
  res.json({ ok: true, following: false });
}));

// ---- Direct messages ----
router.get("/messages", requireAuth, wrap(async (req, res) => {
  // Conversation list: latest message per peer.
  const rows = await query(
    `SELECT peer.id, peer.username, peer.avatar_url, m.body, m.created_at, m.sender_id,
            (SELECT COUNT(*) FROM messages mm WHERE mm.sender_id = peer.id AND mm.recipient_id = :me AND mm.is_read = 0) AS unread
     FROM (
       SELECT CASE WHEN sender_id = :me THEN recipient_id ELSE sender_id END AS peer_id, MAX(id) AS last_id
       FROM messages WHERE sender_id = :me OR recipient_id = :me GROUP BY peer_id
     ) t
     JOIN messages m ON m.id = t.last_id
     JOIN users peer ON peer.id = t.peer_id
     ORDER BY m.created_at DESC`,
    { me: req.user.id }
  );
  res.json({
    data: rows.map((r) => ({
      peer: { id: String(r.id), username: r.username, avatar_url: r.avatar_url || "" },
      last_message: r.body,
      last_at: r.created_at,
      unread: Number(r.unread),
      outgoing: Number(r.sender_id) === Number(req.user.id),
    })),
  });
}));

router.get("/messages/:userId", requireAuth, wrap(async (req, res) => {
  const peer = Number(req.params.userId);
  const rows = await query(
    `SELECT id, sender_id, recipient_id, body, is_read, created_at FROM messages
     WHERE (sender_id = :me AND recipient_id = :peer) OR (sender_id = :peer AND recipient_id = :me)
     ORDER BY created_at ASC LIMIT 200`,
    { me: req.user.id, peer }
  );
  await query(
    "UPDATE messages SET is_read = 1 WHERE recipient_id = :me AND sender_id = :peer",
    { me: req.user.id, peer }
  );
  res.json({
    data: rows.map((r) => ({
      id: String(r.id),
      body: r.body,
      created_at: r.created_at,
      outgoing: Number(r.sender_id) === Number(req.user.id),
    })),
  });
}));

router.post("/messages/:userId", requireAuth, wrap(async (req, res) => {
  const peer = Number(req.params.userId);
  const body = String(req.body.body || "").trim().slice(0, 4000);
  if (!body) return res.status(400).json({ error: "Message body required" });
  if (peer === Number(req.user.id)) return res.status(400).json({ error: "Cannot message yourself" });
  const exists = await query("SELECT id FROM users WHERE id = :id LIMIT 1", { id: peer });
  if (!exists.length) return res.status(404).json({ error: "Recipient not found" });
  const [ins] = await pool.execute(
    "INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)",
    [req.user.id, peer, body]
  );
  await pool.execute(
    `INSERT INTO notifications (user_id, type, message) VALUES (?, 'message', ?)`,
    [peer, `New message from ${req.user.username}`.slice(0, 255)]
  );
  res.status(201).json({ id: String(ins.insertId) });
}));

// ---- Community wall (global forum feed) ----
router.get("/feed", wrap(async (req, res) => {
  const sort = (req.query.sort || "new").toString();
  const viewerId = (req.user && req.user.id) || 0;
  const orderSql =
    sort === "old" ? "p.created_at ASC"
    : sort === "top" ? "likes DESC, p.created_at DESC"
    : "p.created_at DESC";

  const rows = await query(
    `SELECT p.id, p.user_id, p.body, p.image_url, p.created_at, u.username, u.avatar_url,
            (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id) AS likes,
            (SELECT COUNT(*) FROM community_posts r WHERE r.parent_id = p.id) AS replies,
            (SELECT COUNT(*) FROM community_post_likes l WHERE l.post_id = p.id AND l.user_id = :viewer) AS liked
     FROM community_posts p JOIN users u ON u.id = p.user_id
     WHERE p.parent_id IS NULL
     ORDER BY ${orderSql} LIMIT 100`,
    { viewer: viewerId }
  );
  res.json({
    data: rows.map((r) => ({
      id: String(r.id),
      user: { id: String(r.user_id), username: r.username, avatar_url: r.avatar_url || "" },
      body: r.body,
      image_url: r.image_url || "",
      created_at: r.created_at,
      likes: Number(r.likes),
      replies: Number(r.replies),
      liked: Number(r.liked) > 0,
    })),
  });
}));

router.get("/feed/:id/replies", wrap(async (req, res) => {
  const rows = await query(
    `SELECT p.id, p.user_id, p.body, p.image_url, p.created_at, u.username, u.avatar_url
     FROM community_posts p JOIN users u ON u.id = p.user_id
     WHERE p.parent_id = :id ORDER BY p.created_at ASC`,
    { id: req.params.id }
  );
  res.json({
    data: rows.map((r) => ({
      id: String(r.id),
      user: { id: String(r.user_id), username: r.username, avatar_url: r.avatar_url || "" },
      body: r.body,
      image_url: r.image_url || "",
      created_at: r.created_at,
    })),
  });
}));

router.post("/feed", requireAuth, wrap(async (req, res) => {
  const body = String(req.body.body || "").trim().slice(0, 4000);
  const parentId = req.body.parent_id ? Number(req.body.parent_id) : null;
  const imageUrl = req.body.image_url ? String(req.body.image_url).slice(0, 1000) : null;
  if (!body && !imageUrl) return res.status(400).json({ error: "Message body or image required" });
  const [ins] = await pool.execute(
    "INSERT INTO community_posts (user_id, parent_id, body, image_url) VALUES (?, ?, ?, ?)",
    [req.user.id, parentId, body, imageUrl]
  );
  res.status(201).json({ id: String(ins.insertId) });
}));

router.post("/feed/:id/like", requireAuth, wrap(async (req, res) => {
  const existing = await query(
    "SELECT 1 FROM community_post_likes WHERE post_id = :p AND user_id = :u LIMIT 1",
    { p: req.params.id, u: req.user.id }
  );
  if (existing.length) {
    await query("DELETE FROM community_post_likes WHERE post_id = :p AND user_id = :u", {
      p: req.params.id,
      u: req.user.id,
    });
    return res.json({ ok: true, liked: false });
  }
  await pool.execute("INSERT IGNORE INTO community_post_likes (post_id, user_id) VALUES (?, ?)", [
    req.params.id,
    req.user.id,
  ]);
  res.json({ ok: true, liked: true });
}));

router.delete("/feed/:id", requireAuth, wrap(async (req, res) => {
  const isAdmin = req.user.role === "admin";
  await query(
    `DELETE FROM community_posts WHERE id = :id ${isAdmin ? "" : "AND user_id = :uid"}`,
    isAdmin ? { id: req.params.id } : { id: req.params.id, uid: req.user.id }
  );
  res.json({ ok: true });
}));

// ---- Latest site-wide comments (for the home page) ----
router.get("/latest-comments", wrap(async (_req, res) => {
  const rows = await query(
    `SELECT c.id, c.body, c.is_spoiler, c.created_at, c.manga_id, c.chapter_id,
            u.username, u.avatar_url, m.slug AS manga_slug, m.title AS manga_title
     FROM comments c JOIN users u ON u.id = c.user_id
     LEFT JOIN mangas m ON m.id = c.manga_id
     ORDER BY c.created_at DESC LIMIT 12`
  );
  res.json({
    data: rows.map((r) => ({
      id: String(r.id),
      body: r.is_spoiler ? "(spoiler hidden)" : r.body,
      created_at: r.created_at,
      user: { username: r.username, avatar_url: r.avatar_url || "" },
      manga_slug: r.manga_slug || null,
      manga_title: r.manga_title || null,
    })),
  });
}));

// ---- Notifications ----
router.get("/notifications", requireAuth, wrap(async (req, res) => {
  const rows = await query(
    `SELECT n.id, n.type, n.message, n.is_read, n.created_at, n.manga_id, m.slug AS manga_slug
     FROM notifications n LEFT JOIN mangas m ON m.id = n.manga_id
     WHERE n.user_id = :uid ORDER BY n.created_at DESC LIMIT 100`,
    { uid: req.user.id }
  );
  const unread = await query(
    "SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0",
    { uid: req.user.id }
  );
  res.json({
    unread: Number(unread[0].c),
    data: rows.map((r) => ({
      id: String(r.id),
      type: r.type,
      message: r.message,
      is_read: Boolean(r.is_read),
      created_at: r.created_at,
      manga_slug: r.manga_slug || null,
    })),
  });
}));

router.post("/notifications/read", requireAuth, wrap(async (req, res) => {
  await query("UPDATE notifications SET is_read = 1 WHERE user_id = :uid", { uid: req.user.id });
  res.json({ ok: true });
}));

module.exports = router;
