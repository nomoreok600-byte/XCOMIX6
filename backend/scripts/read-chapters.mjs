/**
 * Bulk chapter reader — finds chapters that have a known source URL but no
 * resolved pages yet, fetches each source page, and persists the ordered page
 * images into the `pages` table. Complements the lazy reader baked into
 * GET /api/chapters/:id/pages.
 *
 * Usage (run from backend/):
 *   node scripts/read-chapters.mjs [--limit=50] [--delay=700] [--force]
 */
import "dotenv/config";
import db from "../db.js";
import { ensureChapterPages } from "../lib/chapterReader.js";

function arg(name, fallback) {
  const hit = process.argv.find((a) => a === `--${name}` || a.startsWith(`--${name}=`));
  if (!hit) return fallback;
  const eq = hit.indexOf("=");
  return eq === -1 ? true : hit.slice(eq + 1);
}

const LIMIT = Number(arg("limit", 50));
const DELAY = Number(arg("delay", 700));
const FORCE = Boolean(arg("force", false));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
  const [rows] = await db.pool.execute(
    `SELECT c.id, c.chapter_number, c.source_url
     FROM chapters c
     WHERE c.source_url IS NOT NULL
       AND (? = 1 OR NOT EXISTS (SELECT 1 FROM pages p WHERE p.chapter_id = c.id))
     ORDER BY c.id ASC
     LIMIT ${LIMIT}`,
    [FORCE ? 1 : 0]
  );

  console.log(`Resolving pages for ${rows.length} chapter(s)…`);
  let total = 0;
  for (const ch of rows) {
    try {
      const { inserted } = await ensureChapterPages(db.pool, ch, { force: FORCE });
      total += inserted;
      console.log(`  chapter ${ch.id} (#${ch.chapter_number}): ${inserted} pages`);
    } catch (err) {
      console.warn(`  chapter ${ch.id} failed: ${err.message}`);
    }
    await sleep(DELAY);
  }
  await db.pool.end();
  console.log(`Done. ${total} pages inserted.`);
}

main().catch(async (err) => {
  console.error("Reader crashed:", err.message);
  try {
    await db.pool.end();
  } catch {
    /* ignore */
  }
  process.exit(1);
});
