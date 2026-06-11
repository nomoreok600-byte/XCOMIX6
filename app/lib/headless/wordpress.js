const API_PREFIX = process.env.WORDPRESS_API_PREFIX || "/wp-json/xcomix/v1";
const WP_BASE_URL =
  process.env.WORDPRESS_API_URL ||
  process.env.NEXT_PUBLIC_WORDPRESS_API_URL ||
  process.env.NEXT_PUBLIC_WP_API_URL ||
  "";

const DEFAULT_REVALIDATE_SECONDS = 60;

function trimSlashes(value = "") {
  return value.replace(/^\/+|\/+$/g, "");
}

function buildUrl(path) {
  if (!WP_BASE_URL) return null;
  const base = WP_BASE_URL.replace(/\/+$/, "");
  const prefix = trimSlashes(API_PREFIX);
  const endpoint = trimSlashes(path);
  return `${base}/${prefix}/${endpoint}`;
}

async function fetchJson(path, { revalidate = DEFAULT_REVALIDATE_SECONDS } = {}) {
  const url = buildUrl(path);
  if (!url) return null;

  try {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "User-Agent": "AniTeams-Headless-Frontend/1.0",
      },
      next: { revalidate },
    });

    if (!response.ok) {
      console.warn(`WordPress API ${response.status}: ${url}`);
      return null;
    }

    return response.json();
  } catch (error) {
    console.warn(`WordPress API request failed: ${url}`, error?.message);
    return null;
  }
}

export function proxiedImageUrl(url) {
  if (!url) return "";
  if (url.startsWith("/") || url.startsWith("data:") || url.startsWith("blob:")) return url;
  return `/api/proxy-image?url=${encodeURIComponent(url)}`;
}

function asArray(value) {
  if (!value) return [];
  return Array.isArray(value) ? value : Object.values(value);
}

function firstValue(source, keys, fallback = "") {
  for (const key of keys) {
    const value = source?.[key];
    if (value !== undefined && value !== null && value !== "") return value;
  }
  return fallback;
}

export function normalizeManga(raw = {}) {
  const id = firstValue(raw, ["id", "ID", "manga_id", "slug"], "");
  const title =
    typeof raw.title === "object"
      ? raw.title.rendered || raw.title.name || raw.title.userPreferred
      : firstValue(raw, ["title", "name", "post_title"], "Untitled");

  return {
    id: String(id),
    slug: firstValue(raw, ["slug", "post_name"], String(id)),
    title,
    altTitle: firstValue(raw, ["altTitle", "alt_title", "alternative", "alternative_titles"], ""),
    description:
      typeof raw.description === "object"
        ? raw.description.rendered
        : firstValue(raw, ["description", "excerpt", "content", "summary"], ""),
    cover: firstValue(raw, ["cover", "coverUrl", "cover_url", "image", "thumbnail"], ""),
    banner: firstValue(raw, ["banner", "bannerUrl", "banner_url"], firstValue(raw, ["cover", "coverUrl", "cover_url", "image", "thumbnail"], "")),
    author: firstValue(raw, ["author", "artist", "writer"], "Unknown"),
    artist: firstValue(raw, ["artist"], ""),
    type: firstValue(raw, ["type", "manga_type"], "Manga"),
    status: firstValue(raw, ["status", "manga_status"], "Ongoing"),
    score: firstValue(raw, ["score", "rating"], "N/A"),
    isAdult: Boolean(firstValue(raw, ["isAdult", "is_18_plus", "adult", "nsfw"], false)),
    genres: asArray(firstValue(raw, ["genres", "categories", "tags"], [])).map((genre) =>
      typeof genre === "string" ? genre : genre?.name || genre?.slug || String(genre)
    ),
    latestChapter: raw.latestChapter || raw.latest_chapter || null,
    updatedAt: firstValue(raw, ["updatedAt", "modified", "date"], ""),
  };
}

export function normalizeChapter(raw = {}) {
  const id = firstValue(raw, ["id", "ID", "chapter_id", "slug"], "");
  const title =
    typeof raw.title === "object"
      ? raw.title.rendered || raw.title.name || `Chapter ${firstValue(raw, ["number", "chapter_number"], "")}`
      : firstValue(raw, ["title", "name", "post_title"], `Chapter ${firstValue(raw, ["number", "chapter_number"], "")}`);

  return {
    id: String(id),
    mangaId: String(firstValue(raw, ["mangaId", "manga_id", "parent", "post_parent"], "")),
    title,
    number: firstValue(raw, ["number", "chapter_number", "chapterNum"], ""),
    url: firstValue(raw, ["url", "permalink", "link"], id ? `/read/${id}` : "#"),
    image: firstValue(raw, ["image", "thumbnail", "cover"], ""),
    createdAt: firstValue(raw, ["createdAt", "date", "created_at"], ""),
  };
}

function normalizeHome(raw) {
  if (!raw) return demoHomePayload();

  const trending = raw.trending || raw.trendingRanked || {};
  return {
    hero: asArray(raw.hero || raw.hot_sliders || raw.sliders || raw.featured).map(normalizeManga),
    hot: asArray(raw.hot || raw.hotUpdates || raw.hot_entries || raw.latest).map(normalizeManga),
    trending: {
      day: asArray(trending.day || raw.day || raw.trending_day).map(normalizeManga),
      week: asArray(trending.week || raw.week || raw.trending_week).map(normalizeManga),
      month: asArray(trending.month || raw.month || raw.trending_month).map(normalizeManga),
    },
    recentChapters: asArray(raw.recentChapters || raw.recent_chapters || raw.global_recent).map(normalizeChapter),
    liveFeed: asArray(raw.liveFeed || raw.live_feed || raw.comments).map((item) => ({
      id: String(firstValue(item, ["id", "comment_id"], Math.random())),
      author: firstValue(item, ["author", "user", "name"], "Reader"),
      text: firstValue(item, ["text", "content", "comment"], ""),
      targetTitle: firstValue(item, ["targetTitle", "manga_title", "chapter_title"], ""),
      createdAt: firstValue(item, ["createdAt", "date"], ""),
    })),
  };
}

function normalizeMangaInfo(raw) {
  if (!raw) return demoMangaInfo();
  const source = raw.manga || raw;
  return {
    ...normalizeManga(source),
    chapters: asArray(raw.chapters || source.chapters || raw.chapter_list).map(normalizeChapter).sort((a, b) => {
      const aNum = Number.parseFloat(a.number) || 0;
      const bNum = Number.parseFloat(b.number) || 0;
      return bNum - aNum;
    }),
    related: asArray(raw.related || raw.recommendations || []).map(normalizeManga),
  };
}

function normalizeChapterScans(raw) {
  if (!raw) return demoChapterScans();
  const chapter = raw.chapter || raw;
  const images = asArray(raw.images || raw.pages || raw.sources || raw.scan_urls || chapter.images)
    .map((image) => (typeof image === "string" ? image : image?.url || image?.src))
    .filter(Boolean);

  return {
    id: String(firstValue(chapter, ["id", "ID", "chapter_id"], "")),
    mangaId: String(firstValue(chapter, ["mangaId", "manga_id", "parent", "post_parent"], "")),
    mangaTitle: firstValue(chapter, ["mangaTitle", "manga_title"], "Manga"),
    mangaCover: firstValue(chapter, ["mangaCover", "manga_cover", "cover"], ""),
    title: firstValue(chapter, ["title", "name", "post_title"], "Chapter"),
    number: firstValue(chapter, ["number", "chapter_number"], ""),
    images,
    chapters: asArray(raw.chapters || raw.chapter_list || []).map(normalizeChapter),
    prevChapterId: firstValue(raw, ["prevChapterId", "prev_chapter_id", "previous"], null),
    nextChapterId: firstValue(raw, ["nextChapterId", "next_chapter_id", "next"], null),
    comments: asArray(raw.comments || raw.comment_feed).map((item) => ({
      id: String(firstValue(item, ["id", "comment_id"], Math.random())),
      author: firstValue(item, ["author", "user", "name"], "Reader"),
      text: firstValue(item, ["text", "content", "comment"], ""),
      createdAt: firstValue(item, ["createdAt", "date"], ""),
    })),
  };
}

export async function getHeadlessHome() {
  const raw = await fetchJson("home", { revalidate: 45 });
  return normalizeHome(raw);
}

export async function getMangaInfo(id) {
  const raw = await fetchJson(`manga/${encodeURIComponent(id)}`, { revalidate: 120 });
  return normalizeMangaInfo(raw);
}

export async function getChapterScans(chapterId) {
  const raw = await fetchJson(`chapter/${encodeURIComponent(chapterId)}`, { revalidate: 30 });
  return normalizeChapterScans(raw);
}

function demoManga(id = "demo-manga", index = 1) {
  return normalizeManga({
    id: `${id}-${index}`,
    title: `Headless Manga ${index}`,
    description: "Connect WORDPRESS_API_URL to replace this demo payload with your WordPress REST data.",
    cover: "/file.svg",
    score: "9." + index,
    type: index % 2 ? "Manhwa" : "Manga",
    status: index % 3 ? "Ongoing" : "Completed",
    genres: ["Action", "Fantasy"],
    latestChapter: { id: `demo-chapter-${index}`, number: index },
  });
}

function demoHomePayload() {
  const list = Array.from({ length: 12 }, (_, index) => demoManga("demo", index + 1));
  return {
    hero: list.slice(0, 5),
    hot: list,
    trending: { day: list.slice(0, 6), week: list.slice(3, 9), month: list.slice(6, 12) },
    recentChapters: list.slice(0, 8).map((manga, index) =>
      normalizeChapter({
        id: `demo-chapter-${index + 1}`,
        mangaId: manga.id,
        title: `${manga.title} Chapter ${index + 1}`,
        number: index + 1,
        image: manga.cover,
        date: new Date().toISOString(),
      })
    ),
    liveFeed: [
      { id: "demo-feed-1", author: "System", text: "Connect your WordPress backend to show live comments.", targetTitle: "Headless setup" },
    ],
  };
}

function demoMangaInfo() {
  const manga = demoManga("demo-info", 1);
  return {
    ...manga,
    chapters: Array.from({ length: 18 }, (_, index) =>
      normalizeChapter({
        id: `demo-chapter-${index + 1}`,
        mangaId: manga.id,
        title: `Chapter ${index + 1}`,
        number: index + 1,
      })
    ).reverse(),
    related: Array.from({ length: 6 }, (_, index) => demoManga("related", index + 1)),
  };
}

function demoChapterScans() {
  return {
    id: "demo-chapter-1",
    mangaId: "demo-info-1",
    mangaTitle: "Headless Manga",
    mangaCover: "/file.svg",
    title: "Chapter 1",
    number: "1",
    images: ["/file.svg", "/window.svg", "/globe.svg"],
    chapters: demoMangaInfo().chapters,
    prevChapterId: null,
    nextChapterId: "demo-chapter-2",
    comments: [{ id: "demo-comment", author: "System", text: "Native body scrolling reader is ready for WordPress scan URLs." }],
  };
}
