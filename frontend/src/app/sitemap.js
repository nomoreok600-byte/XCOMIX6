const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "https://www.xcomix.top";

export const dynamic = "force-static";

// Static export sitemap of the crawlable, public routes. Per-title URLs use
// query params (client-rendered), so the catalog is reached via /browse.
export default function sitemap() {
  const now = new Date();
  const routes = ["", "/home", "/browse", "/recent", "/community", "/leaderboard"];
  return routes.map((path) => ({
    url: `${SITE_URL}${path}`,
    lastModified: now,
    changeFrequency: path === "/recent" || path === "/home" ? "hourly" : "daily",
    priority: path === "" || path === "/home" ? 1 : 0.7,
  }));
}
