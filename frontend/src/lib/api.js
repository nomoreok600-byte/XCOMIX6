// Centralized API access for the static frontend. The same base URL is used at
// build time (Node) and runtime (browser); NEXT_PUBLIC_ makes it available to both.
export const API_BASE = (
  process.env.NEXT_PUBLIC_API_BASE || "https://www.a3555bet.com"
).replace(/\/+$/, "");

const TOKEN_KEY = "xcomix_token";
const ADULT_KEY = "xcomix_adult";

export function getToken() {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}
export function setToken(token) {
  if (typeof window === "undefined") return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

// 18+ content is hidden by default; the user can opt in via a nav toggle.
export function getAdult() {
  if (typeof window === "undefined") return false;
  return window.localStorage.getItem(ADULT_KEY) === "1";
}
export function setAdult(on) {
  if (typeof window === "undefined") return;
  if (on) window.localStorage.setItem(ADULT_KEY, "1");
  else window.localStorage.removeItem(ADULT_KEY);
}
const adultQ = () => (getAdult() ? { adult: 1 } : {});

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
    // Scraped synopses ship literal markup (e.g. <br>, <p>, <a …>). Turn line
    // breaks into newlines and drop every other tag so nothing renders as raw
    // "<br><br>" text in titles/synopses.
    .replace(/<\s*br\s*\/?\s*>/gi, "\n")
    .replace(/<\/(p|div|li)\s*>/gi, "\n")
    .replace(/<[^>]+>/g, "")
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
    .replace(/&nbsp;/g, " ")
    .replace(/[ \t]+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

async function request(path, { method = "GET", body, auth = false, cache = "no-store" } = {}) {
  const headers = { Accept: "application/json" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (auth) {
    const t = getToken();
    if (t) headers.Authorization = `Bearer ${t}`;
  }
  const res = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    cache,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `API ${res.status}`);
  return data;
}

const qs = (params) => {
  const u = new URLSearchParams();
  for (const [k, v] of Object.entries(params || {})) {
    if (v !== undefined && v !== null && v !== "") u.set(k, v);
  }
  const s = u.toString();
  return s ? `?${s}` : "";
};

// ---- Catalog ----
export const fetchMangaList = (params = {}) => request(`/api/manga${qs(params)}`);
export const fetchManga = (slug) => request(`/api/catalog/manga/${encodeURIComponent(slug)}`);
export const fetchChapterPages = (id) => request(`/api/chapters/${encodeURIComponent(id)}/pages`);
export const fetchGenres = () => request(`/api/catalog/genres`);
export const browse = (params = {}) => request(`/api/catalog/browse${qs({ ...adultQ(), ...params })}`);
export const fetchPopular = (limit = 12) => request(`/api/catalog/popular${qs({ limit, ...adultQ() })}`);
export const fetchRecent = (limit = 24) => request(`/api/catalog/recent${qs({ limit, ...adultQ() })}`);
export const fetchCompleted = (limit = 24) => request(`/api/catalog/completed${qs({ limit, ...adultQ() })}`);
export const fetchRandom = () => request(`/api/catalog/random${qs(adultQ())}`);

// ---- Auth ----
export const register = (body) => request(`/api/auth/register`, { method: "POST", body });
export const login = (body) => request(`/api/auth/login`, { method: "POST", body });
export const fetchMe = () => request(`/api/auth/me`, { auth: true });
export const updateMe = (body) => request(`/api/auth/me`, { method: "PATCH", body, auth: true });
export const changePassword = (body) =>
  request(`/api/auth/change-password`, { method: "POST", body, auth: true });
export const deleteAccount = () => request(`/api/auth/me`, { method: "DELETE", auth: true });

// ---- Library ----
export const fetchLibrary = (params = {}) => request(`/api/library${qs(params)}`, { auth: true });
export const libraryStatus = (mangaId) => request(`/api/library/status/${mangaId}`, { auth: true });
export const setLibrary = (body) => request(`/api/library`, { method: "POST", body, auth: true });
export const removeLibrary = (mangaId) =>
  request(`/api/library/${mangaId}`, { method: "DELETE", auth: true });
export const fetchFolders = () => request(`/api/library/folders`, { auth: true });
export const createFolder = (name) =>
  request(`/api/library/folders`, { method: "POST", body: { name }, auth: true });
export const deleteFolder = (id) =>
  request(`/api/library/folders/${id}`, { method: "DELETE", auth: true });
export const fetchHistory = () => request(`/api/library/history`, { auth: true });
export const pushHistory = (body) =>
  request(`/api/library/history`, { method: "POST", body, auth: true });

// ---- Social ----
export const fetchComments = (params = {}) =>
  request(`/api/social/comments${qs(params)}`, { auth: true });
export const postComment = (body) =>
  request(`/api/social/comments`, { method: "POST", body, auth: true });
export const deleteComment = (id) =>
  request(`/api/social/comments/${id}`, { method: "DELETE", auth: true });
export const reactComment = (id, emoji) =>
  request(`/api/social/comments/${id}/react`, { method: "POST", body: { emoji }, auth: true });
export const fetchReviews = (mangaId) => request(`/api/social/reviews${qs({ manga_id: mangaId })}`);
export const postReview = (body) =>
  request(`/api/social/reviews`, { method: "POST", body, auth: true });

// ---- Community ----
export const fetchUsers = (q) => request(`/api/community/users${qs({ q })}`);
export const fetchLeaderboard = () => request(`/api/community/leaderboard`);
export const fetchProfile = (username) =>
  request(`/api/community/profile/${encodeURIComponent(username)}`, { auth: true });
export const follow = (userId) =>
  request(`/api/community/follow/${userId}`, { method: "POST", auth: true });
export const unfollow = (userId) =>
  request(`/api/community/follow/${userId}`, { method: "DELETE", auth: true });
export const fetchConversations = () => request(`/api/community/messages`, { auth: true });
export const fetchThread = (userId) =>
  request(`/api/community/messages/${userId}`, { auth: true });
export const sendMessage = (userId, body) =>
  request(`/api/community/messages/${userId}`, { method: "POST", body: { body }, auth: true });
export const fetchNotifications = () => request(`/api/community/notifications`, { auth: true });
export const markNotificationsRead = () =>
  request(`/api/community/notifications/read`, { method: "POST", auth: true });

// ---- Community wall (forum feed) + home extras ----
export const fetchFeed = (sort = "new") => request(`/api/community/feed${qs({ sort })}`, { auth: true });
export const fetchReplies = (id) => request(`/api/community/feed/${id}/replies`);
export const postFeed = (body) => request(`/api/community/feed`, { method: "POST", body, auth: true });
export const likeFeed = (id) => request(`/api/community/feed/${id}/like`, { method: "POST", auth: true });
export const deleteFeed = (id) => request(`/api/community/feed/${id}`, { method: "DELETE", auth: true });
export const fetchLatestComments = () => request(`/api/community/latest-comments`);

// ---- Activity (frontend-traffic-driven auto importer) ----
// Pinged on page load; the backend decides (and debounces) whether to run the
// 15-min "latest" crawl and the 60-min backlog crawl. Best-effort + silent.
export const activityPing = () =>
  fetch(`${API_BASE}/api/activity/ping`, { cache: "no-store" })
    .then((r) => r.json())
    .catch(() => null);
