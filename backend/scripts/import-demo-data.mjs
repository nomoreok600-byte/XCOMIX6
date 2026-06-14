/**
 * Content sync pathway — pulls manga/chapter/page records from a source API
 * and upserts them into the XCOMIX MySQL tables. This is the clean-JS port of
 * the legacy scraper's "sync" stage: it only persists metadata + remote image
 * URLs (never the binaries — those are streamed on demand by /api/proxy/image).
 *
 * Usage:
 *   node scripts/import-demo-data.mjs [--manga=20] [--chapters=5] [--pages=12]
 *
 * Env:
 *   SOURCE_API   source REST base (default: live XCOMIX WordPress API)
 *   DB_*         MySQL connection (see .env.example)
 */
import "dotenv/config";
import mysql from "mysql2/promise";

const SOURCE_API = process.env.SOURCE_API || "https://www.a3555bet.com/wp-json/xcomix/v1";

function flag(name, fallback) {
  const hit = process.argv.find((a) => a.startsWith(`--${name}=`));
  return hit ? Number(hit.split("=")[1]) : fallback;
}

const MAX_MANGA = flag("manga", 12);
const MAX_CHAPTERS = flag("chapters", 5);
const MAX_PAGES = flag("pages", 12);

async function getJson(url) {
  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (!res.ok) throw new Error(`${res.status} ${url}`);
  return res.json();
}

async function main() {
  const db = await mysql.createConnection({
    host: process.env.DB_HOST || "localhost",
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || "root",
    password: process.env.DB_PASSWORD || "",
    database: process.env.DB_NAME || "xcomix",
    charset: "utf8mb4_unicode_ci",
  });

  console.log(`Source: ${SOURCE_API}`);
  const home = await getJson(`${SOURCE_API}/home`);
  const candidates = [...(home.hero || []), ...(home.hot || [])];

  const seen = new Set();
  let mangaCount = 0;
  let chapterCount = 0;
  let pageCount = 0;

  for (const item of candidates) {
    if (mangaCount >= MAX_MANGA) break;
    if (seen.has(item.id)) continue;
    seen.add(item.id);

    let info;
    try {
      info = await getJson(`${SOURCE_API}/manga/${item.id}`);
    } catch {
      continue;
    }
    const m = info.manga || item;
    const slug = m.slug || `manga-${item.id}`;

    const [mres] = await db.execute(
      `INSERT INTO mangas (slug, title, synopsis, cover_url, status, type, is_18_plus)
       VALUES (?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE title=VALUES(title), synopsis=VALUES(synopsis),
         cover_url=VALUES(cover_url), status=VALUES(status), type=VALUES(type),
         is_18_plus=VALUES(is_18_plus), id=LAST_INSERT_ID(id)`,
      [
        slug,
        m.title || "Untitled",
        (m.description || "").slice(0, 4000),
        m.cover_url || m.cover || "",
        m.status || "Ongoing",
        m.type || "Manga",
        m.is_18_plus ? 1 : 0,
      ]
    );
    const mangaId = mres.insertId;
    mangaCount++;

    const chapters = (info.chapters || []).slice().reverse().slice(0, MAX_CHAPTERS);
    for (const ch of chapters) {
      let images = [];
      try {
        const cd = await getJson(`${SOURCE_API}/chapter/${ch.id}`);
        images = (cd.images || []).slice(0, MAX_PAGES);
      } catch {
        /* skip chapters whose pages cannot be resolved */
      }
      if (images.length === 0) continue;

      const number = String(ch.number ?? ch.chapter_number ?? chapterCount + 1);
      const [cres] = await db.execute(
        `INSERT INTO chapters (manga_id, chapter_number, title)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE title=VALUES(title), id=LAST_INSERT_ID(id)`,
        [mangaId, number, ch.title || `Chapter ${number}`]
      );
      const chapterId = cres.insertId;
      chapterCount++;

      await db.execute(`DELETE FROM pages WHERE chapter_id = ?`, [chapterId]);
      let n = 0;
      for (const src of images) {
        n++;
        await db.execute(
          `INSERT INTO pages (chapter_id, page_number, remote_source_url) VALUES (?, ?, ?)`,
          [chapterId, n, src]
        );
        pageCount++;
      }
    }
    console.log(`+ ${m.title} (${chapters.length} chapters)`);
  }

  await db.end();
  console.log(`Done. mangas=${mangaCount} chapters=${chapterCount} pages=${pageCount}`);
}

main().catch((err) => {
  console.error("Import failed:", err.message);
  process.exit(1);
});
