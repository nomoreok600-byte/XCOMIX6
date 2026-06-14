/**
 * ManhwaBuddy importer — clean-JS port of xcomix-buddy-engine.php.
 *
 * Scrapes manga metadata + chapter lists from ManhwaBuddy directly into MySQL
 * (the `mangas` + `chapters` tables), then optionally runs the chapter reader
 * to resolve each chapter's page images into the `pages` table. Only metadata
 * and remote image URLs are stored — never binaries.
 *
 * Usage (run from the backend/ folder so .env is picked up):
 *   node scripts/buddy-import.mjs --url=https://manhwabuddy.com/manhwa/<slug>/
 *   node scripts/buddy-import.mjs --category=https://manhwabuddy.com/only-18-webtoons --start=1 --end=2 --limit=8
 *   node scripts/buddy-import.mjs --latest --limit=10 --max-chapters=3
 *
 * Flags:
 *   --url=URL           import a single manga page
 *   --category=URL      listing page to crawl (default only-18-webtoons)
 *   --latest            shorthand for crawling page 1 of the default category
 *   --start=N --end=N   page range for the category crawl (default 1..1)
 *   --limit=N           max manga per listing page (default 12)
 *   --max-chapters=N    chapters per manga to resolve pages for; 0 = all (default 3)
 *   --no-read           import metadata + chapter list only (skip the page reader)
 *   --delay=MS          polite delay between source requests (default 700)
 */
import "dotenv/config";
import db from "../db.js";
import source from "../lib/source.js";
import { ensureChapterPages } from "../lib/chapterReader.js";

const {
  fetchHtml,
  extractMangaMeta,
  extractChapterLinks,
  extractListingLinks,
  slugFromUrl,
} = source;

const DEFAULT_CATEGORY = "https://manhwabuddy.com/only-18-webtoons";
const ORIGIN = "https://manhwabuddy.com";

function arg(name, fallback) {
  const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
  if (!hit) return fallback;
  const eq = hit.indexOf("=");
  return eq === -1 ? true : hit.slice(eq + 1);
}

const OPTS = {
  url: arg("url", null),
  category: arg("latest", false) ? DEFAULT_CATEGORY : arg("category", null),
  start: Number(arg("start", 1)),
  end: Number(arg("end", 1)),
  limit: Number(arg("limit", 12)),
  maxChapters: Number(arg("max-chapters", 3)),
  read: arg("no-read", false) ? false : true,
  delay: Number(arg("delay", 700)),
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function collectMangaUrls() {
  if (OPTS.url) return [OPTS.url];

  const category = OPTS.category || DEFAULT_CATEGORY;
  const urls = [];
  const seen = new Set();
  for (let page = OPTS.start; page <= OPTS.end; page += 1) {
    const pageUrl = page === 1 ? category : `${category.replace(/\/+$/, "")}/page/${page}/`;
    console.log(`[listing] page ${page}: ${pageUrl}`);
    let html;
    try {
      html = await fetchHtml(pageUrl);
    } catch (err) {
      console.warn(`[listing] failed: ${err.message}`);
      break;
    }
    const links = extractListingLinks(html, { origin: ORIGIN }).filter((u) => !seen.has(u));
    if (links.length === 0) {
      console.warn(`[listing] no manga links on page ${page}; stopping.`);
      break;
    }
    for (const u of links.slice(0, OPTS.limit)) {
      seen.add(u);
      urls.push(u);
    }
    await sleep(OPTS.delay);
  }
  return urls;
}

async function importManga(url) {
  const html = await fetchHtml(url);
  const meta = extractMangaMeta(html);
  const slug = slugFromUrl(url);

  const [mres] = await db.pool.execute(
    `INSERT INTO mangas (slug, title, synopsis, cover_url, status, type, is_18_plus, source_url)
     VALUES (?, ?, ?, ?, 'Ongoing', ?, 1, ?)
     ON DUPLICATE KEY UPDATE title=VALUES(title), synopsis=VALUES(synopsis),
       cover_url=VALUES(cover_url), type=VALUES(type), is_18_plus=VALUES(is_18_plus),
       source_url=VALUES(source_url), id=LAST_INSERT_ID(id)`,
    [slug, meta.title, meta.description || "", meta.cover || "", meta.type, url]
  );
  const mangaId = mres.insertId;

  const chapters = extractChapterLinks(html, { origin: ORIGIN, mangaSlug: slug });

  // Pre-fetch existing chapter numbers so "new" counts are accurate regardless
  // of how the MySQL/MariaDB driver reports affectedRows for upserts.
  const [existingRows] = await db.pool.execute(
    "SELECT chapter_number FROM chapters WHERE manga_id = ?",
    [mangaId]
  );
  const existing = new Set(existingRows.map((r) => String(r.chapter_number)));

  let newChapters = 0;
  const chapterRows = [];
  for (const ch of chapters) {
    if (!existing.has(String(ch.number))) newChapters += 1;
    const [cres] = await db.pool.execute(
      `INSERT INTO chapters (manga_id, chapter_number, title, source_url)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE title=VALUES(title), source_url=VALUES(source_url), id=LAST_INSERT_ID(id)`,
      [mangaId, ch.number, `Chapter ${ch.number}`, ch.url]
    );
    chapterRows.push({ id: cres.insertId, source_url: ch.url, number: ch.number });
  }

  // Chapter reader: resolve page images for the most recent chapters.
  let pagesResolved = 0;
  if (OPTS.read && chapterRows.length > 0) {
    const target = OPTS.maxChapters > 0 ? chapterRows.slice(-OPTS.maxChapters) : chapterRows;
    for (const ch of target) {
      try {
        const { inserted } = await ensureChapterPages(db.pool, ch);
        pagesResolved += inserted;
      } catch (err) {
        console.warn(`  [reader] chapter ${ch.number} failed: ${err.message}`);
      }
      await sleep(OPTS.delay);
    }
  }

  console.log(
    `+ ${meta.title} [${meta.type}] — chapters: ${chapters.length} (+${newChapters} new), pages resolved: ${pagesResolved}`
  );
}

async function main() {
  const urls = await collectMangaUrls();
  if (urls.length === 0) {
    console.error("No manga URLs to import. Provide --url or --category/--latest.");
    process.exit(1);
  }
  console.log(`Importing ${urls.length} manga from ManhwaBuddy…`);
  for (const url of urls) {
    try {
      await importManga(url);
    } catch (err) {
      console.warn(`! failed ${url}: ${err.message}`);
    }
    await sleep(OPTS.delay);
  }
  await db.pool.end();
  console.log("Done.");
}

main().catch(async (err) => {
  console.error("Importer crashed:", err.message);
  try {
    await db.pool.end();
  } catch {
    /* ignore */
  }
  process.exit(1);
});
