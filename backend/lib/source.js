"use strict";

/**
 * Source-site access layer — the clean-JS port of the legacy PHP engines'
 * stealth fetch + HTML extraction (xcomix-buddy-engine.php / scraper.php /
 * headless-api.php). Used by the importers and the chapter reader.
 *
 * Nothing here stores binaries; it only extracts metadata + remote image URLs.
 */

const DESKTOP_UA =
  "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";

// Adult-gate cookies (ManhwaBuddy) so 18+ pages return real content.
const ADULT_COOKIES = "wpmanga-adult=1; wpmanga-adault=1; mature_warning_accept=yes; content_safe=1";

function refererFor(target) {
  let url;
  try {
    url = new URL(target);
  } catch {
    return "https://manhwabuddy.com/";
  }
  const host = url.hostname.replace(/^www\./, "");
  if (host.includes("manhwabuddy")) return "https://manhwabuddy.com/";
  if (host.includes("mangakatana")) return "https://mangakatana.com/";
  return `${url.protocol}//${url.hostname}/`;
}

/** Stealth GET with desktop UA, adult cookies and a host-aware Referer. */
async function fetchHtml(target, { referer, cookie, timeoutMs = 25000 } = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(target, {
      headers: {
        "User-Agent": DESKTOP_UA,
        Accept: "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Accept-Language": "en-US,en;q=0.9",
        Referer: referer || refererFor(target),
        Cookie: cookie || ADULT_COOKIES,
        "Upgrade-Insecure-Requests": "1",
      },
      redirect: "follow",
      signal: controller.signal,
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.text();
  } finally {
    clearTimeout(timer);
  }
}

function decodeEntities(text) {
  if (!text) return "";
  return String(text)
    .replace(/&#(\d+);/g, (_, c) => String.fromCharCode(Number(c)))
    .replace(/&#x([0-9a-f]+);/gi, (_, c) => String.fromCharCode(parseInt(c, 16)))
    .replace(/&amp;/g, "&")
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;|&apos;|&rsquo;|&#8217;/g, "\u2019")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&nbsp;/g, " ")
    .trim();
}

function metaContent(html, property) {
  const re = new RegExp(
    `<meta[^>]+(?:property|name)=["']${property}["'][^>]+content=["']([^"']+)["']`,
    "i"
  );
  const m = html.match(re);
  return m ? decodeEntities(m[1]) : "";
}

/** Clean a ManhwaBuddy-style og:title down to the bare series name. */
function cleanTitle(raw) {
  let t = raw || "";
  t = t.replace(/^Read\s+/i, "");
  t = t.replace(/\s*Manhwa\s*\[Latest Chapters\]\s*\.?COM/i, "");
  t = t.replace(/\s*\[Latest Chapters\]/i, "");
  t = t.replace(/\.COM/gi, "").replace(/-?\s*ManhwaBuddy/gi, "").replace(/\|/g, "");
  t = t.replace(/\s+/g, " ").trim();
  // Strip a trailing standalone "Manhwa" left over from the source title.
  t = t.replace(/\s+Manhwa$/i, "").trim();
  return t;
}

/**
 * Extract series metadata from a manga page.
 * @returns {{title, description, cover, type, isAdult, genres}}
 */
function extractMangaMeta(html) {
  const title = cleanTitle(metaContent(html, "og:title")) || "Untitled";
  const description = metaContent(html, "og:description");
  const cover = metaContent(html, "og:image");

  // Best-effort genre + type detection from de-tagged page text.
  let type = "Manhwa";
  const genres = [];
  const plain = html
    .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, " ")
    .replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, " ")
    .replace(/<[^>]+>/g, " ")
    .replace(/\s+/g, " ");
  const gm = plain.match(/(?:Genres?)\s*[:\-]?\s+(.{1,200}?)\s+(?:Type|Release|Status|Author|Artist|Alternative|Bookmark|Share)/i);
  if (gm) {
    for (const part of gm[1].split(/[/,|]/)) {
      const g = part.trim().replace(/\s+/g, " ");
      if (g && g.length < 25) genres.push(g.replace(/\b\w/g, (c) => c.toUpperCase()));
    }
  }
  for (const g of genres) {
    const gl = g.toLowerCase();
    if (gl === "manga") type = "Manga";
    if (gl === "manhua") type = "Manhua";
  }

  return { title, description, cover, type, genres: [...new Set(genres)] };
}

/**
 * Extract chapter links from a manga page.
 * @returns {Array<{number:string, url:string}>} ascending by number.
 */
function extractChapterLinks(html, { origin, mangaSlug }) {
  const found = new Map();
  const re = /href=["']([^"']*(?:chapter|ch)-?\d+(?:\.\d+)?[^"']*)["']/gi;
  // Match the series slug only as a bounded path segment (…/<slug>/… or
  // …/<slug>-chapter-…) so recommended-series rails can't leak foreign chapters.
  const slugLc = mangaSlug ? String(mangaSlug).toLowerCase() : null;
  let m;
  while ((m = re.exec(html)) !== null) {
    let link = m[1];
    if (slugLc) {
      const linkLc = link.toLowerCase();
      if (!linkLc.includes(`/${slugLc}/`) && !linkLc.includes(`/${slugLc}-`)) continue;
    }
    if (!/^https?:/i.test(link)) link = `${origin.replace(/\/+$/, "")}/${link.replace(/^\/+/, "")}`;
    const num = link.match(/(?:chapter|ch)-?([0-9]+(?:\.[0-9]+)?)/i);
    const number = num ? num[1] : "0";
    if (!found.has(number)) found.set(number, link.split("?")[0]);
  }
  return [...found.entries()]
    .map(([number, url]) => ({ number, url }))
    .sort((a, b) => parseFloat(a.number) - parseFloat(b.number));
}

/**
 * Extract manga listing links from a category/latest page.
 * @returns {string[]} absolute manga URLs (skips chapters, paging, raws).
 */
function extractListingLinks(html, { origin = "https://manhwabuddy.com" } = {}) {
  const links = new Set();
  const re = /href=["'](?:https?:\/\/[^"'/]+)?\/(manga|manhwa|manhua|comic)\/([a-zA-Z0-9._-]+)\/?["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const [, kind, slug] = m;
    if (slug === "page" || /chapter-/i.test(slug) || /-raw$/i.test(slug)) continue;
    links.add(`${origin.replace(/\/+$/, "")}/${kind}/${slug}/`);
  }
  return [...links];
}

const JUNK_IMG = /(logo|banner|avatar|icon|favicon|credit|promo|recruit|join|donate|support|sidebar|thumb|\/static\/)/i;

/**
 * The "chapter reader": extract ordered page-image URLs from a chapter page.
 * @returns {string[]} de-duplicated image URLs in document order.
 */
function extractPageImages(html) {
  const out = [];
  const seen = new Set();
  const re = /["'](https?:\/\/[^"']+\.(?:jpg|jpeg|png|webp)(?:\?[^"']*)?)["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const url = m[1].replace(/\\\//g, "/");
    if (JUNK_IMG.test(url)) continue;
    if (seen.has(url)) continue;
    seen.add(url);
    out.push(url);
  }
  return out;
}

function slugFromUrl(url) {
  return url.replace(/\/+$/, "").split("/").pop().toLowerCase();
}

// =====================================================================
//  MangaKatana source — clean-JS port of wordpress-themes/.../scraper.php
//  (xcomix_process_manga / xcomix_extract_strict_links / autopilot).
// =====================================================================

const KATANA_ORIGIN = "https://mangakatana.com";

/** Canonicalize any MangaKatana URL down to the base /manga/<slug> page. */
function canonicalKatanaUrl(url) {
  const m = String(url || "").match(/mangakatana\.com\/manga\/([^/?#"']+)/i);
  if (!m) return null;
  return `${KATANA_ORIGIN}/manga/${m[1]}`;
}

function tagText(html, re) {
  const m = html.match(re);
  return m ? decodeEntities(m[1].replace(/<[^>]+>/g, " ")) : "";
}

/** Extract series metadata from a MangaKatana manga page. */
function extractKatanaMeta(html) {
  const title =
    tagText(html, /<h1[^>]*class="heading"[^>]*>([\s\S]*?)<\/h1>/i) ||
    tagText(html, /<h1[^>]*>([\s\S]*?)<\/h1>/i) ||
    cleanTitle(metaContent(html, "og:title")) ||
    "Untitled";

  let description = metaContent(html, "og:description") || "";
  description = description.replace(/^\s*Summary\s*:\s*/i, "").trim();

  // Prefer the <img src> webp inside .cover; fall back to og:image.
  let cover =
    (html.match(/<div class="cover">[\s\S]{0,400}?<img[^>]+src="([^"]+)"/i) || [])[1] ||
    metaContent(html, "og:image") ||
    "";

  const alt = tagText(html, /<div class="alt_name">([\s\S]*?)<\/div>/i);
  const author = tagText(html, /class="author"[^>]*>([^<]+)<\/a>/i) || "Unknown";
  const status =
    tagText(html, /class="[^"]*\bstatus\b[^"]*"[^>]*>([^<]+)</i) || "Ongoing";

  // Genres inside the <div class="genres"> block.
  const genres = [];
  const gblock = (html.match(/<div class="genres">([\s\S]*?)<\/div>/i) || [])[1] || "";
  let gm;
  const gre = /<a[^>]*>([^<]+)<\/a>/gi;
  while ((gm = gre.exec(gblock)) !== null) {
    const g = decodeEntities(gm[1]).trim();
    if (g) genres.push(g);
  }

  let type = "Manga";
  let isAdult = false;
  for (const g of genres) {
    const gl = g.toLowerCase();
    if (gl === "manhwa" || gl === "webtoon") type = "Manhwa";
    if (gl === "manhua") type = "Manhua";
    if (["smut", "mature", "adult", "nsfw", "erotica", "ecchi"].includes(gl)) isAdult = true;
  }

  return {
    title,
    description,
    cover,
    alt,
    author,
    status: /complete/i.test(status) ? "Completed" : "Ongoing",
    type,
    isAdult,
    genres: [...new Set(genres)],
  };
}

/**
 * Extract chapter links from a MangaKatana manga page (ascending by number).
 * MangaKatana pages embed "you may also like"/related rails that link straight
 * to OTHER series' chapters; if `mangaSlug` is supplied we keep only links whose
 * `/manga/<slug>/` segment matches this series so foreign chapters never leak in.
 */
function extractKatanaChapters(html, mangaSlug) {
  const wanted = mangaSlug ? String(mangaSlug).toLowerCase() : null;
  const found = new Map();
  const re = /<a[^>]+href="(https?:\/\/(?:www\.)?mangakatana\.com\/manga\/[^"]+\/c[^"]+)"[^>]*>([\s\S]*?)<\/a>/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const url = m[1].split("?")[0];
    if (wanted) {
      const linkSlug = (url.match(/\/manga\/([^/]+)\//i) || [])[1] || "";
      if (linkSlug.toLowerCase() !== wanted) continue;
    }
    const text = decodeEntities(m[2].replace(/<[^>]+>/g, " ")).trim();
    const num = (text.match(/([0-9]+(?:\.[0-9]+)?)/) || url.match(/\/c([0-9]+(?:\.[0-9]+)?)/i) || [])[1] || "0";
    if (!found.has(num)) found.set(num, { number: num, url, title: text || `Chapter ${num}` });
  }
  return [...found.values()].sort((a, b) => parseFloat(a.number) - parseFloat(b.number));
}

/** Extract base manga URLs from a MangaKatana listing/latest page. */
function extractKatanaListing(html) {
  const links = new Set();
  const re = /href=["'](https?:\/\/(?:www\.)?mangakatana\.com\/manga\/[^/"']+)["']/gi;
  let m;
  while ((m = re.exec(html)) !== null) {
    const canon = canonicalKatanaUrl(m[1]);
    if (canon) links.add(canon);
  }
  return [...links];
}

/** Extract ordered page-image URLs from a MangaKatana chapter (thzq/ytaw array). */
function extractKatanaPageImages(html) {
  const arr =
    (html.match(/var\s+thzq\s*=\s*\[([\s\S]*?)\]/i) || [])[1] ||
    (html.match(/var\s+ytaw\s*=\s*\[([\s\S]*?)\]/i) || [])[1] ||
    "";
  if (!arr) return extractPageImages(html);
  const out = [];
  const seen = new Set();
  const re = /['"](https?:\/\/[^'"]+)['"]/g;
  let m;
  while ((m = re.exec(arr)) !== null) {
    const url = m[1].replace(/\\\//g, "/");
    if (seen.has(url)) continue;
    seen.add(url);
    out.push(url);
  }
  return out;
}

/** Host-aware page-image extraction used by the chapter reader. */
function extractPageImagesFor(html, sourceUrl) {
  if (sourceUrl && /mangakatana/i.test(sourceUrl)) return extractKatanaPageImages(html);
  return extractPageImages(html);
}

module.exports = {
  DESKTOP_UA,
  ADULT_COOKIES,
  KATANA_ORIGIN,
  fetchHtml,
  refererFor,
  decodeEntities,
  extractMangaMeta,
  extractChapterLinks,
  extractListingLinks,
  extractPageImages,
  extractPageImagesFor,
  canonicalKatanaUrl,
  extractKatanaMeta,
  extractKatanaChapters,
  extractKatanaListing,
  extractKatanaPageImages,
  slugFromUrl,
};
