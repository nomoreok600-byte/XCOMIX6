"use strict";

const express = require("express");
const { query } = require("../db");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

function asInt(value, fallback, { min = 0, max = Number.MAX_SAFE_INTEGER } = {}) {
  const n = Number.parseInt(value, 10);
  if (Number.isNaN(n)) return fallback;
  return Math.min(Math.max(n, min), max);
}

// 18+ titles are hidden unless the client explicitly opts in (?adult=1).
function showAdult(req) {
  const v = String(req.query.adult || "").toLowerCase();
  return v === "1" || v === "true" || v === "yes";
}

function mangaCard(row) {
  return {
    id: String(row.id),
    slug: row.slug,
    title: row.title,
    cover_url: row.cover_url || "",
    status: row.status || "Ongoing",
    type: row.type || "Manga",
    is_18_plus: Boolean(row.is_18_plus),
    views: Number(row.views || 0),
    updated_at: row.updated_at,
    created_at: row.created_at,
    chapter_count: row.chapter_count != null ? Number(row.chapter_count) : undefined,
    avg_rating: row.avg_rating != null ? Number(Number(row.avg_rating).toFixed(2)) : null,
    latest_chapter: row.latest_chapter || null,
    last_chapter_at: row.last_chapter_at || null,
  };
}

const CARD_SELECT = `
  SELECT m.*,
    (SELECT COUNT(*) FROM chapters c WHERE c.manga_id = m.id) AS chapter_count,
    (SELECT AVG(r.rating) FROM reviews r WHERE r.manga_id = m.id) AS avg_rating,
    (SELECT c2.chapter_number FROM chapters c2 WHERE c2.manga_id = m.id
       ORDER BY CAST(c2.chapter_number AS DECIMAL(10,2)) DESC, c2.id DESC LIMIT 1) AS latest_chapter,
    (SELECT MAX(c3.created_at) FROM chapters c3 WHERE c3.manga_id = m.id) AS last_chapter_at
  FROM mangas m`;

// "Recently updated" must reflect new titles / new chapters only — NOT a manga
// being viewed or re-crawled. We sort by when the newest chapter was added
// (falling back to when the title itself was added), which is immune to view
// counters and metadata re-imports bumping mangas.updated_at.
const RECENT_ORDER = "COALESCE(last_chapter_at, m.created_at) DESC, m.id DESC";

// All genres (with counts) for the browse filter UI.
router.get("/genres", wrap(async (_req, res) => {
  const rows = await query(
    `SELECT g.id, g.name, g.slug, COUNT(mg.manga_id) AS count
     FROM genres g LEFT JOIN manga_genres mg ON mg.genre_id = g.id
     GROUP BY g.id, g.name, g.slug ORDER BY g.name ASC`
  );
  res.json({ data: rows.map((r) => ({ ...r, id: String(r.id), count: Number(r.count) })) });
}));

// Browse with genre / status / type / order filters + pagination.
router.get("/browse", wrap(async (req, res) => {
  const limit = asInt(req.query.limit, 24, { min: 1, max: 60 });
  const offset = asInt(req.query.offset, 0, { min: 0 });
  const status = (req.query.status || "").toString().trim();
  const type = (req.query.type || "").toString().trim();
  const q = (req.query.q || "").toString().trim();
  const genre = (req.query.genre || "").toString().trim();
  const order = (req.query.order || "updated").toString().trim();

  const where = [];
  const params = {};
  if (!showAdult(req)) where.push("m.is_18_plus = 0");
  if (status) { where.push("m.status = :status"); params.status = status; }
  if (type) { where.push("m.type = :type"); params.type = type; }
  if (q) {
    // Match across title, alternate titles and slug; also match a slugified form
    // of the query so "killer pietro" finds the "killer-pietro" slug.
    where.push("(m.title LIKE :q OR m.alt_title LIKE :q OR m.slug LIKE :q OR m.slug LIKE :qslug)");
    params.q = `%${q}%`;
    params.qslug = `%${q.toLowerCase().replace(/[^a-z0-9]+/g, "-")}%`;
  }

  let join = "";
  if (genre) {
    join = "JOIN manga_genres mgf ON mgf.manga_id = m.id JOIN genres gf ON gf.id = mgf.genre_id AND gf.slug = :genre";
    params.genre = genre;
  }
  const whereSql = where.length ? `WHERE ${where.join(" AND ")}` : "";
  const orderSql =
    order === "popular" ? "m.views DESC, m.id DESC"
    : order === "newest" ? "m.created_at DESC, m.id DESC"
    : order === "title" ? "m.title ASC"
    : order === "rating" ? "avg_rating DESC, m.id DESC"
    : RECENT_ORDER;

  const totalRows = await query(
    `SELECT COUNT(DISTINCT m.id) AS total FROM mangas m ${join} ${whereSql}`,
    params
  );
  const rows = await query(
    `${CARD_SELECT} ${join} ${whereSql} ORDER BY ${orderSql} LIMIT ${limit} OFFSET ${offset}`,
    params
  );

  // When a search is run with 18+ hidden, tell the client how many adult matches
  // are being withheld so it can prompt the user to enable the 18+ toggle.
  let adultHidden = 0;
  if (q && !showAdult(req)) {
    const adWhere = where.filter((c) => c !== "m.is_18_plus = 0").concat("m.is_18_plus = 1");
    const adRows = await query(
      `SELECT COUNT(DISTINCT m.id) AS total FROM mangas m ${join} WHERE ${adWhere.join(" AND ")}`,
      params
    );
    adultHidden = Number(adRows[0]?.total || 0);
  }

  res.json({
    data: rows.map(mangaCard),
    total: Number(totalRows[0]?.total || 0),
    adult_hidden: adultHidden,
    limit,
    offset,
  });
}));

router.get("/popular", wrap(async (req, res) => {
  const limit = asInt(req.query.limit, 12, { min: 1, max: 60 });
  const adult = showAdult(req) ? "" : "WHERE m.is_18_plus = 0";
  const rows = await query(`${CARD_SELECT} ${adult} ORDER BY m.views DESC, m.id DESC LIMIT ${limit}`);
  res.json({ data: rows.map(mangaCard) });
}));

router.get("/recent", wrap(async (req, res) => {
  const limit = asInt(req.query.limit, 24, { min: 1, max: 60 });
  const adult = showAdult(req) ? "" : "WHERE m.is_18_plus = 0";
  const rows = await query(`${CARD_SELECT} ${adult} ORDER BY ${RECENT_ORDER} LIMIT ${limit}`);
  res.json({ data: rows.map(mangaCard) });
}));

router.get("/completed", wrap(async (req, res) => {
  const limit = asInt(req.query.limit, 24, { min: 1, max: 60 });
  const adult = showAdult(req) ? "" : "AND m.is_18_plus = 0";
  const rows = await query(
    `${CARD_SELECT} WHERE m.status = 'Completed' ${adult} ORDER BY m.updated_at DESC, m.id DESC LIMIT ${limit}`
  );
  res.json({ data: rows.map(mangaCard) });
}));

router.get("/random", wrap(async (req, res) => {
  const adult = showAdult(req) ? "" : "WHERE m.is_18_plus = 0";
  const rows = await query(`${CARD_SELECT} ${adult} ORDER BY RAND() LIMIT 1`);
  if (!rows.length) return res.status(404).json({ error: "No manga available" });
  res.json({ data: mangaCard(rows[0]) });
}));

// Rich manga detail: manga + genres + chapters + rating summary.
router.get("/manga/:slug", wrap(async (req, res) => {
  const slug = req.params.slug;
  const rows = await query(`${CARD_SELECT} WHERE m.slug = :slug LIMIT 1`, { slug });
  if (!rows.length) return res.status(404).json({ error: "Manga not found", slug });
  const row = rows[0];

  // Best-effort view counter. Keep updated_at unchanged (it has ON UPDATE
  // CURRENT_TIMESTAMP) so simply viewing a title never bumps it into the
  // "recently updated" feed.
  await query("UPDATE mangas SET views = views + 1, updated_at = updated_at WHERE id = :id", { id: row.id });

  const genres = await query(
    `SELECT g.id, g.name, g.slug FROM genres g
     JOIN manga_genres mg ON mg.genre_id = g.id WHERE mg.manga_id = :id ORDER BY g.name`,
    { id: row.id }
  );
  const chapters = await query(
    `SELECT id, manga_id, chapter_number, title, created_at FROM chapters
     WHERE manga_id = :id ORDER BY CAST(chapter_number AS DECIMAL(10,2)) ASC, id ASC`,
    { id: row.id }
  );
  const ratingRows = await query(
    `SELECT AVG(rating) AS avg, COUNT(*) AS count FROM reviews WHERE manga_id = :id`,
    { id: row.id }
  );

  res.json({
    manga: {
      ...mangaCard(row),
      alt_title: row.alt_title || "",
      author: row.author || "",
      synopsis: row.synopsis || "",
      source: row.source || "",
      genres: genres.map((g) => ({ ...g, id: String(g.id) })),
      rating: {
        avg: ratingRows[0]?.avg != null ? Number(Number(ratingRows[0].avg).toFixed(2)) : null,
        count: Number(ratingRows[0]?.count || 0),
      },
    },
    chapters: chapters.map((c) => ({
      id: String(c.id),
      manga_id: String(c.manga_id),
      chapter_number: c.chapter_number,
      title: c.title || `Chapter ${c.chapter_number}`,
      created_at: c.created_at,
    })),
  });
}));

module.exports = router;
