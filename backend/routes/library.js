"use strict";

const express = require("express");
const { query, pool } = require("../db");
const { requireAuth } = require("../lib/auth");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

const STATUSES = ["reading", "plan", "completed", "on_hold", "dropped"];

router.use(requireAuth);

// ---- Folders ----
router.get("/folders", wrap(async (req, res) => {
  const rows = await query(
    `SELECT f.id, f.name, f.created_at,
            (SELECT COUNT(*) FROM library l WHERE l.folder_id = f.id) AS count
     FROM folders f WHERE f.user_id = :uid ORDER BY f.name`,
    { uid: req.user.id }
  );
  res.json({ data: rows.map((r) => ({ ...r, id: String(r.id), count: Number(r.count) })) });
}));

router.post("/folders", wrap(async (req, res) => {
  const name = String(req.body.name || "").trim().slice(0, 80);
  if (!name) return res.status(400).json({ error: "Folder name required" });
  const dupe = await query(
    "SELECT id FROM folders WHERE user_id = :uid AND name = :n LIMIT 1",
    { uid: req.user.id, n: name }
  );
  if (dupe.length) return res.status(409).json({ error: "Folder already exists" });
  const [ins] = await pool.execute(
    "INSERT INTO folders (user_id, name) VALUES (?, ?)",
    [req.user.id, name]
  );
  res.status(201).json({ id: String(ins.insertId), name });
}));

router.delete("/folders/:id", wrap(async (req, res) => {
  await query("DELETE FROM folders WHERE id = :id AND user_id = :uid", {
    id: req.params.id,
    uid: req.user.id,
  });
  res.json({ ok: true });
}));

// ---- Library / bookmarks ----
router.get("/", wrap(async (req, res) => {
  const status = (req.query.status || "").toString().trim();
  const folder = (req.query.folder || "").toString().trim();
  const where = ["l.user_id = :uid"];
  const params = { uid: req.user.id };
  if (status && STATUSES.includes(status)) { where.push("l.status = :status"); params.status = status; }
  if (folder) { where.push("l.folder_id = :folder"); params.folder = folder; }

  const rows = await query(
    `SELECT l.id, l.status, l.folder_id, l.created_at,
            m.id AS manga_id, m.slug, m.title, m.cover_url, m.type, m.status AS manga_status, m.is_18_plus
     FROM library l JOIN mangas m ON m.id = l.manga_id
     WHERE ${where.join(" AND ")} ORDER BY l.created_at DESC`,
    params
  );
  res.json({
    data: rows.map((r) => ({
      id: String(r.id),
      status: r.status,
      folder_id: r.folder_id != null ? String(r.folder_id) : null,
      manga: {
        id: String(r.manga_id),
        slug: r.slug,
        title: r.title,
        cover_url: r.cover_url || "",
        type: r.type,
        status: r.manga_status,
        is_18_plus: Boolean(r.is_18_plus),
      },
    })),
  });
}));

// Lookup a single manga's library status for the current user.
router.get("/status/:mangaId", wrap(async (req, res) => {
  const rows = await query(
    "SELECT status, folder_id FROM library WHERE user_id = :uid AND manga_id = :mid LIMIT 1",
    { uid: req.user.id, mid: req.params.mangaId }
  );
  res.json({ in_library: rows.length > 0, entry: rows[0] || null });
}));

router.post("/", wrap(async (req, res) => {
  const mangaId = Number(req.body.manga_id);
  const status = STATUSES.includes(req.body.status) ? req.body.status : "plan";
  const folderId = req.body.folder_id ? Number(req.body.folder_id) : null;
  if (!mangaId) return res.status(400).json({ error: "manga_id required" });

  await pool.execute(
    `INSERT INTO library (user_id, manga_id, status, folder_id) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE status = VALUES(status), folder_id = VALUES(folder_id)`,
    [req.user.id, mangaId, status, folderId]
  );
  res.json({ ok: true, status, folder_id: folderId != null ? String(folderId) : null });
}));

router.delete("/:mangaId", wrap(async (req, res) => {
  await query("DELETE FROM library WHERE user_id = :uid AND manga_id = :mid", {
    uid: req.user.id,
    mid: req.params.mangaId,
  });
  res.json({ ok: true });
}));

// ---- Reading history ----
router.get("/history", wrap(async (req, res) => {
  const rows = await query(
    `SELECT h.manga_id, h.chapter_id, h.updated_at,
            m.slug, m.title, m.cover_url,
            c.chapter_number
     FROM reading_history h
     JOIN mangas m ON m.id = h.manga_id
     LEFT JOIN chapters c ON c.id = h.chapter_id
     WHERE h.user_id = :uid ORDER BY h.updated_at DESC LIMIT 100`,
    { uid: req.user.id }
  );
  res.json({
    data: rows.map((r) => ({
      manga: { id: String(r.manga_id), slug: r.slug, title: r.title, cover_url: r.cover_url || "" },
      chapter_id: r.chapter_id != null ? String(r.chapter_id) : null,
      chapter_number: r.chapter_number || null,
      updated_at: r.updated_at,
    })),
  });
}));

router.post("/history", wrap(async (req, res) => {
  const mangaId = Number(req.body.manga_id);
  const chapterId = req.body.chapter_id ? Number(req.body.chapter_id) : null;
  if (!mangaId) return res.status(400).json({ error: "manga_id required" });
  await pool.execute(
    `INSERT INTO reading_history (user_id, manga_id, chapter_id) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE chapter_id = VALUES(chapter_id), updated_at = CURRENT_TIMESTAMP`,
    [req.user.id, mangaId, chapterId]
  );
  res.json({ ok: true });
}));

module.exports = router;
