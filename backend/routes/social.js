"use strict";

const express = require("express");
const { query, pool } = require("../db");
const { requireAuth } = require("../lib/auth");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// ---- Comments (manga and/or chapter scoped, threaded) ----
async function loadComments({ mangaId, chapterId, viewerId }) {
  const where = [];
  const params = {};
  if (chapterId) { where.push("c.chapter_id = :cid"); params.cid = chapterId; }
  else if (mangaId) { where.push("c.manga_id = :mid AND c.chapter_id IS NULL"); params.mid = mangaId; }
  else return [];

  const rows = await query(
    `SELECT c.*, u.username, u.avatar_url
     FROM comments c JOIN users u ON u.id = c.user_id
     WHERE ${where.join(" AND ")}
     ORDER BY c.created_at ASC`,
    params
  );
  if (!rows.length) return [];

  const ids = rows.map((r) => Number(r.id));
  const reactions = await query(
    `SELECT comment_id, emoji, COUNT(*) AS count,
            SUM(CASE WHEN user_id = :viewer THEN 1 ELSE 0 END) AS mine
     FROM comment_reactions WHERE comment_id IN (${ids.join(",")})
     GROUP BY comment_id, emoji`,
    { viewer: viewerId || 0 }
  );
  const reactionMap = {};
  for (const r of reactions) {
    (reactionMap[r.comment_id] = reactionMap[r.comment_id] || []).push({
      emoji: r.emoji,
      count: Number(r.count),
      mine: Number(r.mine) > 0,
    });
  }

  const byId = {};
  const roots = [];
  for (const r of rows) {
    byId[r.id] = {
      id: String(r.id),
      user: { id: String(r.user_id), username: r.username, avatar_url: r.avatar_url || "" },
      body: r.body,
      image_url: r.image_url || "",
      is_spoiler: Boolean(r.is_spoiler),
      created_at: r.created_at,
      reactions: reactionMap[r.id] || [],
      replies: [],
    };
  }
  for (const r of rows) {
    if (r.parent_id && byId[r.parent_id]) byId[r.parent_id].replies.push(byId[r.id]);
    else roots.push(byId[r.id]);
  }
  return roots;
}

router.get("/comments", wrap(async (req, res) => {
  const mangaId = req.query.manga_id ? Number(req.query.manga_id) : null;
  const chapterId = req.query.chapter_id ? Number(req.query.chapter_id) : null;
  const data = await loadComments({ mangaId, chapterId, viewerId: req.user && req.user.id });
  res.json({ data });
}));

router.post("/comments", requireAuth, wrap(async (req, res) => {
  const body = String(req.body.body || "").trim().slice(0, 4000);
  const mangaId = req.body.manga_id ? Number(req.body.manga_id) : null;
  const chapterId = req.body.chapter_id ? Number(req.body.chapter_id) : null;
  const parentId = req.body.parent_id ? Number(req.body.parent_id) : null;
  const imageUrl = req.body.image_url ? String(req.body.image_url).slice(0, 1000) : null;
  const isSpoiler = req.body.is_spoiler ? 1 : 0;
  if (!body && !imageUrl) return res.status(400).json({ error: "Comment body or image required" });
  if (!mangaId && !chapterId) return res.status(400).json({ error: "manga_id or chapter_id required" });

  const [ins] = await pool.execute(
    `INSERT INTO comments (user_id, manga_id, chapter_id, parent_id, body, image_url, is_spoiler)
     VALUES (?, ?, ?, ?, ?, ?, ?)`,
    [req.user.id, mangaId, chapterId, parentId, body, imageUrl, isSpoiler]
  );
  res.status(201).json({ id: String(ins.insertId) });
}));

router.delete("/comments/:id", requireAuth, wrap(async (req, res) => {
  const isAdmin = req.user.role === "admin";
  await query(
    `DELETE FROM comments WHERE id = :id ${isAdmin ? "" : "AND user_id = :uid"}`,
    isAdmin ? { id: req.params.id } : { id: req.params.id, uid: req.user.id }
  );
  res.json({ ok: true });
}));

router.post("/comments/:id/react", requireAuth, wrap(async (req, res) => {
  const emoji = String(req.body.emoji || "").trim().slice(0, 16);
  if (!emoji) return res.status(400).json({ error: "emoji required" });
  // Toggle: remove if present, else add.
  const existing = await query(
    "SELECT id FROM comment_reactions WHERE comment_id = :c AND user_id = :u AND emoji = :e LIMIT 1",
    { c: req.params.id, u: req.user.id, e: emoji }
  );
  if (existing.length) {
    await query("DELETE FROM comment_reactions WHERE id = :id", { id: existing[0].id });
    return res.json({ ok: true, reacted: false });
  }
  await pool.execute(
    "INSERT INTO comment_reactions (comment_id, user_id, emoji) VALUES (?, ?, ?)",
    [req.params.id, req.user.id, emoji]
  );
  res.json({ ok: true, reacted: true });
}));

// ---- Reviews / star ratings ----
router.get("/reviews", wrap(async (req, res) => {
  const mangaId = Number(req.query.manga_id);
  if (!mangaId) return res.status(400).json({ error: "manga_id required" });
  const rows = await query(
    `SELECT r.id, r.rating, r.body, r.created_at, u.id AS user_id, u.username, u.avatar_url
     FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.manga_id = :mid ORDER BY r.created_at DESC`,
    { mid: mangaId }
  );
  const agg = await query(
    "SELECT AVG(rating) AS avg, COUNT(*) AS count FROM reviews WHERE manga_id = :mid",
    { mid: mangaId }
  );
  res.json({
    summary: {
      avg: agg[0]?.avg != null ? Number(Number(agg[0].avg).toFixed(2)) : null,
      count: Number(agg[0]?.count || 0),
    },
    data: rows.map((r) => ({
      id: String(r.id),
      rating: Number(r.rating),
      body: r.body || "",
      created_at: r.created_at,
      user: { id: String(r.user_id), username: r.username, avatar_url: r.avatar_url || "" },
    })),
  });
}));

router.post("/reviews", requireAuth, wrap(async (req, res) => {
  const mangaId = Number(req.body.manga_id);
  const rating = Number(req.body.rating);
  const body = String(req.body.body || "").trim().slice(0, 4000);
  if (!mangaId) return res.status(400).json({ error: "manga_id required" });
  if (!(rating >= 1 && rating <= 5)) return res.status(400).json({ error: "rating must be 1-5" });
  await pool.execute(
    `INSERT INTO reviews (user_id, manga_id, rating, body) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE rating = VALUES(rating), body = VALUES(body)`,
    [req.user.id, mangaId, rating, body]
  );
  res.json({ ok: true });
}));

router.delete("/reviews/:mangaId", requireAuth, wrap(async (req, res) => {
  await query("DELETE FROM reviews WHERE manga_id = :mid AND user_id = :uid", {
    mid: req.params.mangaId,
    uid: req.user.id,
  });
  res.json({ ok: true });
}));

module.exports = router;
