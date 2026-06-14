// Centralized API access for the static frontend. The same base URL is used at
// build time (Node) and runtime (browser); NEXT_PUBLIC_ makes it available to both.
export const API_BASE = (
  process.env.NEXT_PUBLIC_API_BASE || "https://www.a3555bet.com"
).replace(/\/+$/, "");

/** Wrap any remote image URL through the backend streaming proxy. */
export function proxyImage(url) {
  if (!url) return "";
  if (url.startsWith("/") || url.startsWith("data:") || url.startsWith("blob:")) return url;
  return `${API_BASE}/api/proxy/image?url=${encodeURIComponent(url)}`;
}

/** Decode the handful of HTML entities that arrive in scraped titles/synopses. */
export function decodeEntities(input) {
  if (!input) return "";
  return String(input)
    .replace(/&#(\d+);/g, (_, code) => String.fromCharCode(Number(code)))
    .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCharCode(parseInt(code, 16)))
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#8217;|&rsquo;/g, "\u2019")
    .replace(/&#8216;|&lsquo;/g, "\u2018")
    .replace(/&#8220;|&ldquo;/g, "\u201C")
    .replace(/&#8221;|&rdquo;/g, "\u201D")
    .replace(/&#8230;|&hellip;/g, "\u2026")
    .replace(/&nbsp;/g, " ");
}

async function getJson(path, { cache = "no-store" } = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    headers: { Accept: "application/json" },
    cache,
  });
  if (!res.ok) {
    throw new Error(`API ${res.status} for ${path}`);
  }
  return res.json();
}

export function fetchMangaList(params = {}) {
  const qs = new URLSearchParams();
  if (params.limit != null) qs.set("limit", params.limit);
  if (params.offset != null) qs.set("offset", params.offset);
  if (params.q) qs.set("q", params.q);
  if (params.type) qs.set("type", params.type);
  if (params.status) qs.set("status", params.status);
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  return getJson(`/api/manga${suffix}`);
}

export function fetchManga(slug) {
  return getJson(`/api/manga/${encodeURIComponent(slug)}`);
}

export function fetchChapterPages(id) {
  return getJson(`/api/chapters/${encodeURIComponent(id)}/pages`);
}
