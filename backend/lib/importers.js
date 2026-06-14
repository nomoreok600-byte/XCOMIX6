"use strict";

/**
 * Shared import engine (CommonJS) used by the CLI scripts, the backend admin
 * trigger endpoints and the auto-import scheduler. Clean-JS port of the legacy
 * PHP engines (xcomix-buddy-engine.php + scraper.php) writing straight to MySQL.
 *
 * Nothing here downloads binaries — only metadata + remote image URLs are
 * stored. Page images stream on demand through /api/proxy/image.
 */

const { pool } = require("../db");
const source = require("./source");
const { ensureChapterPages } = require("./chapterReader");

const {
  fetchHtml,
  slugFromUrl,
  canonicalKatanaUrl,
  extractKatanaMeta,
  extractKatanaChapters,
  extractKatanaListing,
  extractMangaMeta,
  extractChapterLinks,
  extractListingLinks,
} = source;

const KATANA_ORIGIN = "https://mangakatana.com";
const BUDDY_ORIGIN = "https://manhwabuddy.com";
const BUDDY_DEFAULT_CATEGORY = "https://manhwabuddy.com/only-18-webtoons";

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const slugify = (s) =>
  String(s || "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80) || "genre";

/** Upsert a genre by name and return its id. */
async function upsertGenre(name) {
  const slug = slugify(name);
  await pool.execute(
    `INSERT INTO genres (name, slug) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)`,
    [name.slice(0, 80), slug]
  );
  const [rows] = await pool.execute("SELECT id FROM genres WHERE slug = ? LIMIT 1", [slug]);
  return rows[0] ? Number(rows[0].id) : null;
}

async function linkGenres(mangaId, genres) {
  for (const g of genres || []) {
    const name = String(g || "").trim();
    if (!name) continue;
    const gid = await upsertGenre(name);
    if (gid) {
      await pool.execute(
        "INSERT IGNORE INTO manga_genres (manga_id, genre_id) VALUES (?, ?)",
        [mangaId, gid]
      );
    }
  }
}

/** Notify every user who bookmarked this manga that a new chapter dropped. */
async function notifyNewChapter(mangaId, title, chapterNumber) {
  await pool.execute(
    `INSERT INTO notifications (user_id, type, manga_id, message)
     SELECT user_id, 'new_chapter', ?, ? FROM library WHERE manga_id = ?`,
    [mangaId, `New chapter ${chapterNumber} of ${title}`.slice(0, 255), mangaId]
  );
}

/**
 * Persist a normalized manga record (+chapters, +genres) idempotently.
 * @returns {Promise<{mangaId:number, newChapters:number, chapterRows:Array}>}
 */
async function persistManga(rec) {
  const [mres] = await pool.execute(
    `INSERT INTO mangas (slug, title, alt_title, synopsis, author, cover_url, status, type, is_18_plus, source_url, source)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE title=VALUES(title), alt_title=VALUES(alt_title),
       synopsis=VALUES(synopsis), author=VALUES(author), cover_url=VALUES(cover_url),
       status=VALUES(status), type=VALUES(type), is_18_plus=VALUES(is_18_plus),
       source_url=VALUES(source_url), source=VALUES(source), id=LAST_INSERT_ID(id)`,
    [
      rec.slug,
      rec.title,
      rec.alt || null,
      rec.description || "",
      rec.author || null,
      rec.cover || "",
      rec.status || "Ongoing",
      rec.type || "Manga",
      rec.isAdult ? 1 : 0,
      rec.sourceUrl,
      rec.source,
    ]
  );
  const mangaId = mres.insertId;

  await linkGenres(mangaId, rec.genres);

  const [existingRows] = await pool.execute(
    "SELECT chapter_number FROM chapters WHERE manga_id = ?",
    [mangaId]
  );
  const existing = new Set(existingRows.map((r) => String(r.chapter_number)));

  let newChapters = 0;
  let latestNew = null;
  const chapterRows = [];
  for (const ch of rec.chapters || []) {
    const isNew = !existing.has(String(ch.number));
    if (isNew) {
      newChapters += 1;
      latestNew = ch.number;
    }
    const [cres] = await pool.execute(
      `INSERT INTO chapters (manga_id, chapter_number, title, source_url)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE title=VALUES(title), source_url=VALUES(source_url), id=LAST_INSERT_ID(id)`,
      [mangaId, ch.number, ch.title || `Chapter ${ch.number}`, ch.url]
    );
    chapterRows.push({ id: cres.insertId, source_url: ch.url, number: ch.number });
  }

  // Touch updated_at so the manga surfaces in "recently updated" feeds.
  if (newChapters > 0) {
    await pool.execute("UPDATE mangas SET updated_at = CURRENT_TIMESTAMP WHERE id = ?", [mangaId]);
    try {
      await notifyNewChapter(mangaId, rec.title, latestNew);
    } catch {
      /* notifications are best-effort */
    }
  }

  return { mangaId, newChapters, chapterRows };
}

async function resolveRecentPages(chapterRows, { read, maxChapters, delay }) {
  let pagesResolved = 0;
  if (!read || chapterRows.length === 0) return pagesResolved;
  const target = maxChapters > 0 ? chapterRows.slice(-maxChapters) : chapterRows;
  for (const ch of target) {
    try {
      const { inserted } = await ensureChapterPages(pool, ch);
      pagesResolved += inserted;
    } catch {
      /* a single chapter failing should not abort the import */
    }
    await sleep(delay);
  }
  return pagesResolved;
}

// ---------------------------------------------------------------------------
// MangaKatana
// ---------------------------------------------------------------------------
async function importKatanaManga(url, { read = true, maxChapters = 3, delay = 600 } = {}) {
  const canon = canonicalKatanaUrl(url);
  if (!canon) throw new Error("Invalid MangaKatana URL");
  const html = await fetchHtml(canon, { referer: `${KATANA_ORIGIN}/` });
  const meta = extractKatanaMeta(html);
  const chapters = extractKatanaChapters(html);
  const slug = slugFromUrl(canon);

  const { mangaId, newChapters, chapterRows } = await persistManga({
    ...meta,
    slug,
    source: "katana",
    sourceUrl: canon,
    chapters,
  });
  const pagesResolved = await resolveRecentPages(chapterRows, { read, maxChapters, delay });
  return {
    source: "katana",
    title: meta.title,
    type: meta.type,
    mangaId,
    chapters: chapters.length,
    newChapters,
    pagesResolved,
  };
}

async function importKatanaLatest({ limit = 12, maxChapters = 3, read = true, page = 1, delay = 600 } = {}) {
  const url = page > 1 ? `${KATANA_ORIGIN}/latest/page/${page}` : `${KATANA_ORIGIN}/latest`;
  const html = await fetchHtml(url, { referer: `${KATANA_ORIGIN}/` });
  const links = extractKatanaListing(html).slice(0, limit);
  const results = [];
  for (const link of links) {
    try {
      results.push(await importKatanaManga(link, { read, maxChapters, delay }));
    } catch (err) {
      results.push({ source: "katana", url: link, error: err.message });
    }
    await sleep(delay);
  }
  return results;
}

// ---------------------------------------------------------------------------
// ManhwaBuddy
// ---------------------------------------------------------------------------
async function importBuddyManga(url, { read = true, maxChapters = 3, delay = 600 } = {}) {
  const html = await fetchHtml(url, { referer: `${BUDDY_ORIGIN}/` });
  const meta = extractMangaMeta(html);
  const slug = slugFromUrl(url);
  const chapters = extractChapterLinks(html, { origin: BUDDY_ORIGIN, mangaSlug: slug });

  const { mangaId, newChapters, chapterRows } = await persistManga({
    title: meta.title,
    description: meta.description,
    cover: meta.cover,
    type: meta.type,
    isAdult: true,
    genres: meta.genres,
    status: "Ongoing",
    slug,
    source: "buddy",
    sourceUrl: url,
    chapters,
  });
  const pagesResolved = await resolveRecentPages(chapterRows, { read, maxChapters, delay });
  return {
    source: "buddy",
    title: meta.title,
    type: meta.type,
    mangaId,
    chapters: chapters.length,
    newChapters,
    pagesResolved,
  };
}

async function importBuddyLatest({
  limit = 12,
  maxChapters = 3,
  read = true,
  category = BUDDY_DEFAULT_CATEGORY,
  page = 1,
  delay = 600,
} = {}) {
  const pageUrl = page > 1 ? `${category.replace(/\/+$/, "")}/page/${page}/` : category;
  const html = await fetchHtml(pageUrl, { referer: `${BUDDY_ORIGIN}/` });
  const links = extractListingLinks(html, { origin: BUDDY_ORIGIN }).slice(0, limit);
  const results = [];
  for (const link of links) {
    try {
      results.push(await importBuddyManga(link, { read, maxChapters, delay }));
    } catch (err) {
      results.push({ source: "buddy", url: link, error: err.message });
    }
    await sleep(delay);
  }
  return results;
}

/** Detect source from a single manga URL and import it. */
async function importByUrl(url, opts = {}) {
  if (/mangakatana/i.test(url)) return importKatanaManga(url, opts);
  return importBuddyManga(url, opts);
}

module.exports = {
  persistManga,
  importKatanaManga,
  importKatanaLatest,
  importBuddyManga,
  importBuddyLatest,
  importByUrl,
};
