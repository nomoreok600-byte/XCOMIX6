"use strict";

/**
 * Chapter reader — resolves and persists the page images for a chapter by
 * fetching its source URL and extracting the scan image URLs. This is the
 * clean-JS equivalent of the legacy `mvx_api_chapter_images()` stage: it never
 * downloads the binaries (those stream on demand via /api/proxy/image), it only
 * records the ordered remote image URLs into the `pages` table.
 */

const { fetchHtml, extractPageImagesFor } = require("./source");

// Source CDN page URLs are tokenised / rotated and stop working after a few
// days, which is why a chapter that loaded fine "the first time" shows broken
// images 2–3 days later. We treat stored pages as stale once they age past this
// TTL and transparently re-scrape fresh URLs on the next read. Tunable via env.
const PAGE_TTL_HOURS = Number(process.env.PAGE_TTL_HOURS || 36);
const PAGE_TTL_MS = Math.max(1, PAGE_TTL_HOURS) * 3600 * 1000;

// Whether the optional `pages.resolved_at` column exists (added by app-schema.sql).
// Probed once and cached; when absent we simply skip the time-based staleness
// refresh (force + the reader's error-driven refresh still work everywhere).
let hasResolvedAt = null;
async function resolvedAtSupported(db) {
  if (hasResolvedAt !== null) return hasResolvedAt;
  try {
    const [cols] = await db.execute("SHOW COLUMNS FROM pages LIKE 'resolved_at'");
    hasResolvedAt = cols.length > 0;
  } catch {
    hasResolvedAt = false;
  }
  return hasResolvedAt;
}

/** Fetch a chapter source page and return ordered page-image URLs. */
async function readChapterPages(sourceUrl, { referer } = {}) {
  if (!sourceUrl) return [];
  const html = await fetchHtml(sourceUrl, { referer });
  return extractPageImagesFor(html, sourceUrl);
}

// Per-chapter in-flight resolution promises. Without this, the reader's
// next-chapter prefetch and the click's own request hit ensureChapterPages
// concurrently; the DELETE-then-INSERT below would race and one caller could
// receive 0 pages (the reader then appears stuck on "Streaming pages…" until a
// reload). Deduping concurrent resolves makes every caller share one result.
const inFlight = new Map();

/**
 * Resolve a chapter's pages from its source and persist them (idempotent).
 * @returns {Promise<{pages: Array<{page_number:number, source_url:string}>, inserted:number}>}
 */
function ensureChapterPages(db, chapter, { force = false } = {}) {
  const chapterId = Number(chapter.id);
  if (!force && inFlight.has(chapterId)) return inFlight.get(chapterId);

  const promise = resolveChapterPages(db, chapter, { force });
  if (!force) {
    inFlight.set(chapterId, promise);
    promise.finally(() => {
      if (inFlight.get(chapterId) === promise) inFlight.delete(chapterId);
    });
  }
  return promise;
}

async function resolveChapterPages(db, chapter, { force = false } = {}) {
  const chapterId = Number(chapter.id);
  const supportsTtl = await resolvedAtSupported(db);

  const selectSql = supportsTtl
    ? "SELECT page_number, remote_source_url, resolved_at FROM pages WHERE chapter_id = ? ORDER BY page_number ASC"
    : "SELECT page_number, remote_source_url FROM pages WHERE chapter_id = ? ORDER BY page_number ASC";
  const [existing] = await db.execute(selectSql, [chapterId]);
  const toPages = (rows) =>
    rows.map((p) => ({ page_number: Number(p.page_number), source_url: p.remote_source_url }));

  // Decide whether the cached rows are still good enough to serve as-is.
  let stale = false;
  if (existing.length > 0 && supportsTtl) {
    const oldest = existing.reduce((min, p) => {
      const t = p.resolved_at ? new Date(p.resolved_at).getTime() : 0;
      return Number.isNaN(t) ? 0 : Math.min(min, t);
    }, Date.now());
    stale = Date.now() - oldest > PAGE_TTL_MS;
  }

  if (existing.length > 0 && !force && !stale) {
    return { pages: toPages(existing), inserted: 0 };
  }

  if (!chapter.source_url) {
    // Nothing to re-scrape from; serve whatever we have.
    return { pages: toPages(existing), inserted: 0 };
  }

  let images = [];
  try {
    images = await readChapterPages(chapter.source_url, { referer: chapter.referer });
  } catch {
    images = [];
  }

  // Re-scrape failed (network/markup change): keep the existing rows rather than
  // wiping a chapter to zero pages. A later read will try again.
  if (images.length === 0) {
    return { pages: toPages(existing), inserted: 0 };
  }

  // Replace any stale rows then insert fresh, ordered pages.
  await db.execute("DELETE FROM pages WHERE chapter_id = ?", [chapterId]);
  let n = 0;
  for (const url of images) {
    n += 1;
    await db.execute(
      "INSERT INTO pages (chapter_id, page_number, remote_source_url) VALUES (?, ?, ?)",
      [chapterId, n, url]
    );
  }
  return {
    pages: images.map((url, i) => ({ page_number: i + 1, source_url: url })),
    inserted: n,
  };
}

module.exports = { readChapterPages, ensureChapterPages };
