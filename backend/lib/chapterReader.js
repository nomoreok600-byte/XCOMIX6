"use strict";

/**
 * Chapter reader — resolves and persists the page images for a chapter by
 * fetching its source URL and extracting the scan image URLs. This is the
 * clean-JS equivalent of the legacy `mvx_api_chapter_images()` stage: it never
 * downloads the binaries (those stream on demand via /api/proxy/image), it only
 * records the ordered remote image URLs into the `pages` table.
 */

const { fetchHtml, extractPageImages } = require("./source");

/** Fetch a chapter source page and return ordered page-image URLs. */
async function readChapterPages(sourceUrl, { referer } = {}) {
  if (!sourceUrl) return [];
  const html = await fetchHtml(sourceUrl, { referer });
  return extractPageImages(html);
}

/**
 * Resolve a chapter's pages from its source and persist them (idempotent).
 * @returns {Promise<{pages: Array<{page_number:number, source_url:string}>, inserted:number}>}
 */
async function ensureChapterPages(db, chapter, { force = false } = {}) {
  const chapterId = Number(chapter.id);

  if (!force) {
    const [existing] = await db.execute(
      "SELECT page_number, remote_source_url FROM pages WHERE chapter_id = ? ORDER BY page_number ASC",
      [chapterId]
    );
    if (existing.length > 0) {
      return {
        pages: existing.map((p) => ({ page_number: Number(p.page_number), source_url: p.remote_source_url })),
        inserted: 0,
      };
    }
  }

  if (!chapter.source_url) return { pages: [], inserted: 0 };

  const images = await readChapterPages(chapter.source_url, { referer: chapter.referer });
  if (images.length === 0) return { pages: [], inserted: 0 };

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
