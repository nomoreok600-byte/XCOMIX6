"use strict";

const path = require("node:path");
const express = require("express");
const { query } = require("../db");
const { requireAdmin } = require("../lib/auth");
const importers = require("../lib/importers");
const scheduler = require("../lib/scheduler");
const settings = require("../lib/settings");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// Serve the static admin dashboard (token entered in the page itself).
router.get("/", (_req, res) => {
  res.sendFile(path.join(__dirname, "..", "public", "admin.html"));
});

// All JSON endpoints below require the admin token / admin role.
router.use(requireAdmin);

router.get("/stats", wrap(async (_req, res) => {
  const one = async (sql) => Number((await query(sql))[0].c);
  const [
    mangas, chapters, pages, users, comments, reviews, library,
    notifications, messages, genres, completed, adult,
  ] = await Promise.all([
    one("SELECT COUNT(*) AS c FROM mangas"),
    one("SELECT COUNT(*) AS c FROM chapters"),
    one("SELECT COUNT(*) AS c FROM pages"),
    one("SELECT COUNT(*) AS c FROM users"),
    one("SELECT COUNT(*) AS c FROM comments"),
    one("SELECT COUNT(*) AS c FROM reviews"),
    one("SELECT COUNT(*) AS c FROM library"),
    one("SELECT COUNT(*) AS c FROM notifications"),
    one("SELECT COUNT(*) AS c FROM messages"),
    one("SELECT COUNT(*) AS c FROM genres"),
    one("SELECT COUNT(*) AS c FROM mangas WHERE status = 'Completed'"),
    one("SELECT COUNT(*) AS c FROM mangas WHERE is_18_plus = 1"),
  ]);
  const bySource = await query(
    "SELECT COALESCE(source, 'unknown') AS source, COUNT(*) AS c FROM mangas GROUP BY source"
  );
  res.json({
    totals: { mangas, chapters, pages, users, comments, reviews, library, notifications, messages, genres, completed, adult },
    by_source: bySource.map((r) => ({ source: r.source, count: Number(r.c) })),
    scheduler: scheduler.status(),
  });
}));

router.get("/recent", wrap(async (_req, res) => {
  const recentManga = await query(
    `SELECT id, slug, title, type, status, source, updated_at,
            (SELECT COUNT(*) FROM chapters c WHERE c.manga_id = mangas.id) AS chapters
     FROM mangas ORDER BY updated_at DESC LIMIT 15`
  );
  const recentUsers = await query(
    "SELECT id, username, role, created_at FROM users ORDER BY created_at DESC LIMIT 10"
  );
  const recentComments = await query(
    `SELECT c.id, c.body, c.created_at, u.username FROM comments c
     JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 10`
  );
  res.json({
    manga: recentManga.map((r) => ({ ...r, id: String(r.id), chapters: Number(r.chapters) })),
    users: recentUsers.map((r) => ({ ...r, id: String(r.id) })),
    comments: recentComments.map((r) => ({ ...r, id: String(r.id) })),
  });
}));

// Import a single manga by URL (auto-detect katana/buddy). Awaited.
router.post("/import/url", wrap(async (req, res) => {
  const url = String(req.body.url || "").trim();
  if (!url) return res.status(400).json({ error: "url required" });
  const result = await importers.importByUrl(url, {
    read: req.body.read !== false,
    maxChapters: Number(req.body.max_chapters || 3),
  });
  res.json({ ok: true, result });
}));

// Kick off a latest-feed crawl in the background; poll /admin/scheduler.
router.post("/import/run", wrap(async (req, res) => {
  const overrides = {};
  if (req.body.sources) {
    overrides.sources = String(req.body.sources).split(",").map((s) => s.trim()).filter(Boolean);
  }
  if (req.body.limit) overrides.limit = Number(req.body.limit);
  if (req.body.max_chapters) overrides.maxChapters = Number(req.body.max_chapters);

  scheduler
    .runOnce(overrides)
    .then((s) => console.log("[admin] manual import run done:", JSON.stringify(s.sources || {})))
    .catch((err) => console.warn("[admin] manual import run failed:", err.message));
  res.json({ ok: true, started: true });
}));

// Deep backlog crawl across multiple "latest" pages (backfills everything).
router.post("/import/backlog", wrap(async (req, res) => {
  const sources = (req.body.sources ? String(req.body.sources) : "katana,buddy")
    .split(",").map((s) => s.trim()).filter(Boolean);
  const startPage = Number(req.body.start_page || 1);
  const endPage = Number(req.body.end_page || 5);
  const limit = Number(req.body.limit || 15);
  const maxChapters = Number(req.body.max_chapters || 0);

  // Run in the background (can be long); poll /admin/scheduler or /admin/stats.
  (async () => {
    for (const src of sources) {
      try {
        if (src === "katana") await importers.importKatanaBacklog({ startPage, endPage, limit, maxChapters });
        else if (src === "buddy") await importers.importBuddyBacklog({ startPage, endPage, limit, maxChapters });
        console.log(`[admin] backlog ${src} pages ${startPage}-${endPage} done`);
      } catch (err) {
        console.warn(`[admin] backlog ${src} failed:`, err.message);
      }
    }
  })();
  res.json({ ok: true, started: true, sources, startPage, endPage });
}));

// ---- Site settings: ad slots + announcement banner ----
router.get("/settings", wrap(async (_req, res) => {
  res.json(await settings.adminConfig());
}));

router.post("/settings", wrap(async (req, res) => {
  const body = req.body || {};
  const updates = {};
  const flag = (v) => (v ? "1" : "0");
  // Ad slots: { ads: { header: { html, enabled }, ... } }
  if (body.ads && typeof body.ads === "object") {
    for (const slot of settings.AD_SLOTS) {
      const a = body.ads[slot];
      if (!a) continue;
      if (a.html !== undefined) updates[`ad_${slot}`] = String(a.html);
      if (a.enabled !== undefined) updates[`ad_${slot}_on`] = flag(a.enabled);
    }
  }
  // Announcement: { announcement: { text, enabled } }
  if (body.announcement && typeof body.announcement === "object") {
    if (body.announcement.text !== undefined) updates.announcement_text = String(body.announcement.text);
    if (body.announcement.enabled !== undefined) updates.announcement_on = flag(body.announcement.enabled);
  }
  await settings.setMany(updates);
  res.json({ ok: true, config: await settings.adminConfig() });
}));

router.get("/scheduler", (_req, res) => {
  res.json(scheduler.status());
});

// Toggle the auto-import scheduler on/off at runtime.
router.post("/scheduler/start", (req, res) => {
  scheduler.enable(req.body.interval_min ? Number(req.body.interval_min) : undefined);
  res.json({ ok: true, status: scheduler.status() });
});
router.post("/scheduler/stop", (_req, res) => {
  scheduler.disable();
  res.json({ ok: true, status: scheduler.status() });
});

module.exports = router;
