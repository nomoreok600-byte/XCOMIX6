const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "https://www.xcomix.top";
const API_BASE = (process.env.NEXT_PUBLIC_API_BASE || "https://www.a3555bet.com").replace(/\/+$/, "");

export const dynamic = "force-static";

// Pull the full (non-adult) catalog at build time so Google can discover and
// index every series. Per-title URLs use query params (client-rendered shell),
// which Google does crawl/render. Resilient: if the API is unreachable during
// the build, we still emit the core routes.
async function fetchAllManga() {
  const out = [];
  const pageSize = 60; // matches the catalog browse max limit so paging continues
  try {
    for (let offset = 0; offset < 5000; offset += pageSize) {
      // Build-time fetch (sitemap is force-static): use the default cache so it
      // doesn't conflict with static generation.
      const res = await fetch(`${API_BASE}/api/catalog/browse?limit=${pageSize}&offset=${offset}`);
      if (!res.ok) break;
      const json = await res.json();
      const data = json.data || [];
      for (const m of data) {
        out.push({ slug: m.slug, updated: m.last_chapter_at || m.updated_at || m.created_at });
      }
      if (data.length < pageSize) break;
    }
  } catch {
    /* API not reachable at build time — fall back to core routes only. */
  }
  return out;
}

export default async function sitemap() {
  const now = new Date();
  const core = [
    { path: "", priority: 1, freq: "hourly" },
    { path: "/home", priority: 1, freq: "hourly" },
    { path: "/browse", priority: 0.8, freq: "daily" },
    { path: "/recent", priority: 0.9, freq: "hourly" },
    { path: "/community", priority: 0.6, freq: "daily" },
    { path: "/leaderboard", priority: 0.5, freq: "weekly" },
  ].map((r) => ({
    url: `${SITE_URL}${r.path}`,
    lastModified: now,
    changeFrequency: r.freq,
    priority: r.priority,
  }));

  const mangas = (await fetchAllManga()).map((m) => ({
    url: `${SITE_URL}/manga/?slug=${encodeURIComponent(m.slug)}`,
    lastModified: m.updated ? new Date(m.updated) : now,
    changeFrequency: "weekly",
    priority: 0.7,
  }));

  return [...core, ...mangas];
}
