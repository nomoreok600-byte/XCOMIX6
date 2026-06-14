/**
 * MangaKatana importer — clean-JS port of wordpress-themes/.../scraper.php.
 *
 * Scrapes manga metadata + chapter lists from MangaKatana into MySQL, links
 * genres, then optionally resolves recent chapters' page images. Only metadata
 * and remote image URLs are stored — never binaries.
 *
 * Usage (run from the backend/ folder so .env is picked up):
 *   node scripts/katana-import.mjs --url=https://mangakatana.com/manga/<slug>.<id>
 *   node scripts/katana-import.mjs --latest --limit=15 --max-chapters=3
 *   node scripts/katana-import.mjs --latest --page=2 --limit=15   # deep backlog
 *
 * Flags:
 *   --url=URL           import a single manga page
 *   --latest            crawl the MangaKatana /latest feed
 *   --page=N            latest page number (default 1; >1 = backlog crawler)
 *   --limit=N           max manga per listing page (default 15)
 *   --max-chapters=N    chapters per manga to resolve pages for; 0 = all (default 3)
 *   --no-read           import metadata + chapter list only (skip the page reader)
 *   --delay=MS          polite delay between source requests (default 600)
 */
import "dotenv/config";
import db from "../db.js";
import importers from "../lib/importers.js";

function arg(name, fallback) {
  const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
  if (!hit) return fallback;
  const eq = hit.indexOf("=");
  return eq === -1 ? true : hit.slice(eq + 1);
}

const OPTS = {
  url: arg("url", null),
  latest: Boolean(arg("latest", false)),
  page: Number(arg("page", 1)),
  limit: Number(arg("limit", 15)),
  maxChapters: Number(arg("max-chapters", 3)),
  read: arg("no-read", false) ? false : true,
  delay: Number(arg("delay", 600)),
};

function logResult(r) {
  if (r.error) {
    console.warn(`! ${r.url || ""}: ${r.error}`);
  } else {
    console.log(
      `+ [${r.source}] ${r.title} [${r.type}] — chapters: ${r.chapters} (+${r.newChapters} new), pages resolved: ${r.pagesResolved}`
    );
  }
}

async function main() {
  if (!OPTS.url && !OPTS.latest) {
    console.error("Provide --url=<manga url> or --latest.");
    process.exit(1);
  }
  if (OPTS.url) {
    logResult(
      await importers.importKatanaManga(OPTS.url, {
        read: OPTS.read,
        maxChapters: OPTS.maxChapters,
        delay: OPTS.delay,
      })
    );
  } else {
    console.log(`Crawling MangaKatana /latest (page ${OPTS.page}, limit ${OPTS.limit})…`);
    const results = await importers.importKatanaLatest({
      limit: OPTS.limit,
      maxChapters: OPTS.maxChapters,
      read: OPTS.read,
      page: OPTS.page,
      delay: OPTS.delay,
    });
    results.forEach(logResult);
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
