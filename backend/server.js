"use strict";

require("dotenv").config();

const express = require("express");
const cors = require("cors");
const { Readable } = require("node:stream");
const { pool, query } = require("./db");
const { ensureChapterPages } = require("./lib/chapterReader");

const app = express();
const PORT = Number(process.env.PORT || 4000);
const PROXY_CACHE_MAX_AGE = Number(process.env.PROXY_CACHE_MAX_AGE || 86400);

app.disable("x-powered-by");
app.set("trust proxy", true);

// ---------------------------------------------------------------------------
// CORS — strictly limited to the static frontend origin(s).
// ---------------------------------------------------------------------------
const ALLOWED_ORIGINS = (process.env.FRONTEND_ORIGIN || "https://www.xcomix.top")
  .split(",")
  .map((o) => o.trim().replace(/\/+$/, ""))
  .filter(Boolean);

const corsOptions = {
  origin(origin, callback) {
    // Allow non-browser clients (no Origin header) and any allow-listed origin.
    if (!origin || ALLOWED_ORIGINS.includes(origin.replace(/\/+$/, ""))) {
      return callback(null, true);
    }
    return callback(new Error("Origin not allowed by CORS policy"));
  },
  methods: ["GET", "OPTIONS"],
  maxAge: 600,
};

app.use(cors(corsOptions));
app.options("*", cors(corsOptions));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function asInt(value, fallback, { min = 0, max = Number.MAX_SAFE_INTEGER } = {}) {
  const n = Number.parseInt(value, 10);
  if (Number.isNaN(n)) return fallback;
  return Math.min(Math.max(n, min), max);
}

function mangaPublic(row) {
  return {
    id: String(row.id),
    slug: row.slug,
    title: row.title,
    synopsis: row.synopsis || "",
    cover_url: row.cover_url || "",
    status: row.status || "Ongoing",
    type: row.type || "Manga",
    is_18_plus: Boolean(row.is_18_plus),
    created_at: row.created_at,
    chapter_count: row.chapter_count != null ? Number(row.chapter_count) : undefined,
  };
}

function chapterPublic(row) {
  return {
    id: String(row.id),
    manga_id: String(row.manga_id),
    chapter_number: row.chapter_number,
    title: row.title || `Chapter ${row.chapter_number}`,
    created_at: row.created_at,
  };
}

// Async wrapper so thrown/rejected errors hit the central error handler.
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// ---------------------------------------------------------------------------
// Health
// ---------------------------------------------------------------------------
app.get("/api/health", wrap(async (_req, res) => {
  await query("SELECT 1");
  res.json({ status: "ok", service: "xcomix-backend", time: new Date().toISOString() });
}));

// ---------------------------------------------------------------------------
// GET /api/manga  — paginated catalog with optional search / filters
// ---------------------------------------------------------------------------
app.get("/api/manga", wrap(async (req, res) => {
  const limit = asInt(req.query.limit, 24, { min: 1, max: 100 });
  const offset = asInt(req.query.offset, 0, { min: 0 });
  const search = (req.query.q || "").toString().trim();
  const type = (req.query.type || "").toString().trim();
  const status = (req.query.status || "").toString().trim();

  const where = [];
  const params = {};
  if (search) {
    where.push("(m.title LIKE :search OR m.slug LIKE :search)");
    params.search = `%${search}%`;
  }
  if (type) {
    where.push("m.type = :type");
    params.type = type;
  }
  if (status) {
    where.push("m.status = :status");
    params.status = status;
  }
  const whereSql = where.length ? `WHERE ${where.join(" AND ")}` : "";

  const totalRows = await query(`SELECT COUNT(*) AS total FROM mangas m ${whereSql}`, params);
  const total = Number(totalRows[0]?.total || 0);

  // LIMIT/OFFSET are validated integers (not user strings) so they are safe to inline.
  const rows = await query(
    `SELECT m.*, (SELECT COUNT(*) FROM chapters c WHERE c.manga_id = m.id) AS chapter_count
     FROM mangas m
     ${whereSql}
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT ${limit} OFFSET ${offset}`,
    params
  );

  res.json({ data: rows.map(mangaPublic), total, limit, offset });
}));

// ---------------------------------------------------------------------------
// GET /api/manga/:slug  — single series + ordered chapter directory
// ---------------------------------------------------------------------------
app.get("/api/manga/:slug", wrap(async (req, res) => {
  const slug = req.params.slug;
  const rows = await query(
    `SELECT m.*, (SELECT COUNT(*) FROM chapters c WHERE c.manga_id = m.id) AS chapter_count
     FROM mangas m WHERE m.slug = :slug LIMIT 1`,
    { slug }
  );
  if (rows.length === 0) {
    return res.status(404).json({ error: "Manga not found", slug });
  }
  const manga = mangaPublic(rows[0]);

  const chapters = await query(
    `SELECT id, manga_id, chapter_number, title, created_at
     FROM chapters
     WHERE manga_id = :id
     ORDER BY CAST(chapter_number AS DECIMAL(10,2)) ASC, id ASC`,
    { id: rows[0].id }
  );

  res.json({ manga, chapters: chapters.map(chapterPublic) });
}));

// ---------------------------------------------------------------------------
// GET /api/chapters/:id/pages  — page list + prev/next navigation
// ---------------------------------------------------------------------------
app.get("/api/chapters/:id/pages", wrap(async (req, res) => {
  const id = asInt(req.params.id, NaN, { min: 1 });
  if (Number.isNaN(id)) return res.status(400).json({ error: "Invalid chapter id" });

  const chapterRows = await query(
    `SELECT c.id, c.manga_id, c.chapter_number, c.title, c.source_url, c.created_at,
            m.title AS manga_title, m.slug AS manga_slug, m.cover_url AS manga_cover
     FROM chapters c
     JOIN mangas m ON m.id = c.manga_id
     WHERE c.id = :id LIMIT 1`,
    { id }
  );
  if (chapterRows.length === 0) {
    return res.status(404).json({ error: "Chapter not found", id: String(id) });
  }
  const ch = chapterRows[0];

  let pageRows = await query(
    `SELECT page_number, remote_source_url
     FROM pages WHERE chapter_id = :id ORDER BY page_number ASC`,
    { id }
  );

  // Lazy "chapter reader": if pages were never resolved but we know the source
  // chapter URL, scrape + cache them on first read, then continue normally.
  if (pageRows.length === 0 && ch.source_url) {
    try {
      const result = await ensureChapterPages(pool, { id: ch.id, source_url: ch.source_url });
      if (result.inserted > 0) {
        pageRows = result.pages.map((p) => ({
          page_number: p.page_number,
          remote_source_url: p.source_url,
        }));
      }
    } catch (err) {
      console.warn(`[reader] could not resolve pages for chapter ${id}:`, err.message);
    }
  }

  // Sibling chapters for prev/next navigation (ordered by numeric chapter).
  const siblings = await query(
    `SELECT id FROM chapters
     WHERE manga_id = :manga_id
     ORDER BY CAST(chapter_number AS DECIMAL(10,2)) ASC, id ASC`,
    { manga_id: ch.manga_id }
  );
  const order = siblings.map((s) => Number(s.id));
  const idx = order.indexOf(id);
  const prevId = idx > 0 ? order[idx - 1] : null;
  const nextId = idx >= 0 && idx < order.length - 1 ? order[idx + 1] : null;

  res.json({
    chapter: {
      id: String(ch.id),
      manga_id: String(ch.manga_id),
      manga_slug: ch.manga_slug,
      manga_title: ch.manga_title,
      manga_cover: ch.manga_cover || "",
      chapter_number: ch.chapter_number,
      title: ch.title || `Chapter ${ch.chapter_number}`,
      created_at: ch.created_at,
    },
    pages: pageRows.map((p) => ({
      page_number: Number(p.page_number),
      source_url: p.remote_source_url,
    })),
    prev_chapter_id: prevId != null ? String(prevId) : null,
    next_chapter_id: nextId != null ? String(nextId) : null,
  });
}));

// ---------------------------------------------------------------------------
// GET /api/proxy/image?url=...  — security-hardened streaming image proxy
//
// Bypasses 403/hotlink/Cloudflare blocks by spoofing desktop browser headers
// and an algorithmic Referer derived from the requested host. Streams the raw
// binary socket straight to the client — nothing is buffered or stored on disk.
// ---------------------------------------------------------------------------
const USER_AGENTS = [
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
  "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15",
  "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36",
];

function isBlockedHost(hostname) {
  if (!hostname) return true;
  const h = hostname.toLowerCase();
  if (h === "localhost" || h === "::1") return true;
  if (/^127\./.test(h)) return true;
  if (/^10\./.test(h)) return true;
  if (/^192\.168\./.test(h)) return true;
  if (/^169\.254\./.test(h)) return true;
  if (/^172\.(1[6-9]|2\d|3[0-1])\./.test(h)) return true;
  if (h === "0.0.0.0" || h.startsWith("fc") || h.startsWith("fd")) return true;
  return false;
}

function refererFor(url) {
  const host = url.hostname.replace(/^www\./, "");
  if (host.includes("mangakatana")) return "https://mangakatana.com/";
  if (host.includes("manhwabuddy")) return "https://manhwabuddy.com/";
  if (host.includes("mgeko") || host.includes("mangageko")) return `https://${url.hostname}/`;
  return `${url.protocol}//${url.hostname}/`;
}

app.get("/api/proxy/image", wrap(async (req, res) => {
  const target = (req.query.url || "").toString();
  if (!target) return res.status(400).json({ error: "Missing url parameter" });

  let url;
  try {
    url = new URL(target);
  } catch {
    return res.status(400).json({ error: "Invalid url parameter" });
  }
  if (!["http:", "https:"].includes(url.protocol) || isBlockedHost(url.hostname)) {
    return res.status(400).json({ error: "Blocked target" });
  }

  const headers = {
    "User-Agent": USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)],
    Accept: "image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8",
    "Accept-Language": "en-US,en;q=0.9",
    Referer: refererFor(url),
    Origin: `${url.protocol}//${url.hostname}`,
    Connection: "keep-alive",
  };
  if (process.env.PROXY_CF_COOKIE) headers.Cookie = process.env.PROXY_CF_COOKIE;

  // Guard time-to-first-byte so a hung upstream can't pin a worker on cPanel.
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 20000);

  let upstream;
  try {
    upstream = await fetch(url, { headers, redirect: "follow", signal: controller.signal });
  } catch (err) {
    clearTimeout(timeout);
    return res.status(504).json({
      error: "Upstream image request failed",
      detail: err && err.name === "AbortError" ? "timeout" : (err && err.message) || "unknown",
    });
  }
  clearTimeout(timeout);

  if (!upstream.ok || !upstream.body) {
    return res.status(upstream.status || 502).json({ error: "Unable to fetch image" });
  }

  // Some hosts (e.g. tokenized CDN URLs) mislabel images as octet-stream.
  // Normalize to a real image MIME from the URL extension so browsers render it.
  let contentType = upstream.headers.get("content-type") || "";
  if (!contentType.startsWith("image/")) {
    const ext = (url.pathname.split(".").pop() || "").toLowerCase();
    const byExt = {
      jpg: "image/jpeg",
      jpeg: "image/jpeg",
      png: "image/png",
      webp: "image/webp",
      gif: "image/gif",
      avif: "image/avif",
    };
    contentType = byExt[ext] || "image/jpeg";
  }
  res.status(200);
  res.setHeader("Content-Type", contentType);
  res.setHeader("Cache-Control", `public, max-age=${PROXY_CACHE_MAX_AGE}`);
  res.setHeader("X-Content-Type-Options", "nosniff");
  const len = upstream.headers.get("content-length");
  if (len) res.setHeader("Content-Length", len);

  // Pipe the binary stream straight through; abort upstream if client leaves.
  const nodeStream = Readable.fromWeb(upstream.body);
  res.on("close", () => nodeStream.destroy());
  nodeStream.on("error", () => {
    if (!res.headersSent) res.status(502).end();
    else res.end();
  });
  nodeStream.pipe(res);
}));

// ---------------------------------------------------------------------------
// 404 + central error handler
// ---------------------------------------------------------------------------
app.use((req, res) => res.status(404).json({ error: "Not found", path: req.path }));

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, _next) => {
  const status = err && err.message && err.message.includes("CORS") ? 403 : 500;
  console.error(`[error] ${req.method} ${req.originalUrl}:`, err && err.message);
  if (res.headersSent) return;
  res.status(status).json({ error: status === 403 ? "Forbidden" : "Internal server error" });
});

app.listen(PORT, () => {
  console.log(`XCOMIX backend listening on :${PORT}`);
  console.log(`CORS allow-list: ${ALLOWED_ORIGINS.join(", ")}`);
});

module.exports = app;
