"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  fetchChapterPages,
  fetchManga,
  pushHistory,
  getToken,
  proxyImage,
  decodeEntities,
  formatDateTime,
} from "../lib/api";
import Comments from "./Comments";
import Icon from "./Icon";
import AdSlot from "./AdSlot";

const FIT_KEY = "xcomix_reader_fit";
const FIT_MODES = [
  ["width", "Fit width"],
  ["height", "Fit height"],
  ["native", "Original"],
];

// Pages never show a scary error to the reader. On failure they keep a subtle
// loading shimmer and silently retry with backoff (cache-busting each attempt),
// while asking the parent to re-scrape fresh source URLs for the whole chapter.
// A freshly re-scraped URL (page.source_url change) resets and reloads cleanly.
function ReaderImage({ page, eager, fit, onError }) {
  const [loaded, setLoaded] = useState(false);
  const [bust, setBust] = useState(0);
  const triesRef = useRef(0);
  const timerRef = useRef(null);
  const src = proxyImage(page.source_url);
  const finalSrc = bust > 0 ? `${src}${src.includes("?") ? "&" : "?"}r=${bust}` : src;

  useEffect(() => {
    // New URL (fresh re-scrape) or first mount: reset retry state.
    setLoaded(false);
    triesRef.current = 0;
    if (timerRef.current) clearTimeout(timerRef.current);
    return () => timerRef.current && clearTimeout(timerRef.current);
  }, [page.source_url]);

  const handleError = () => {
    triesRef.current += 1;
    const n = triesRef.current;
    // First failure for this page → ask the parent to re-resolve the chapter
    // (the parent throttles/caps this), which usually swaps in working URLs.
    if (n === 1) onError?.();
    // Keep retrying this image. Fast backoff at first, then a slow heartbeat so
    // it recovers on its own if the source/proxy comes back — without ever
    // surfacing an error message.
    const delay = n <= 6 ? Math.min(600 * n, 3500) : 15000;
    if (n % 5 === 0) onError?.();
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => setBust((b) => b + 1), delay);
  };

  return (
    <div className={`reader-page fit-${fit}`}>
      {!loaded && (
        <div className="reader-page-loading" aria-hidden="true">
          <span className="reader-spinner" />
        </div>
      )}
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={finalSrc}
        alt={`Page ${page.page_number}`}
        loading={eager ? "eager" : "lazy"}
        // eslint-disable-next-line react/no-unknown-property
        fetchpriority={eager ? "high" : "auto"}
        decoding="async"
        onLoad={() => setLoaded(true)}
        onError={handleError}
        style={loaded ? undefined : { minHeight: 320 }}
      />
    </div>
  );
}

export default function ReaderView({ id }) {
  const router = useRouter();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [chapters, setChapters] = useState([]);
  const [fit, setFit] = useState("width");
  const [showSettings, setShowSettings] = useState(false);
  const [showInfo, setShowInfo] = useState(false);
  const [showChapters, setShowChapters] = useState(false);
  const [chapterQuery, setChapterQuery] = useState("");
  const [showTop, setShowTop] = useState(false);
  const [progress, setProgress] = useState(0);
  // Immersive reading: tap the page area to toggle the top/bottom option bars so
  // they don't permanently overlay (and shift) the artwork.
  const [chrome, setChrome] = useState(true);

  // Restore persisted fit preference once on mount.
  useEffect(() => {
    const saved = typeof window !== "undefined" && window.localStorage.getItem(FIT_KEY);
    if (saved && FIT_MODES.some(([v]) => v === saved)) setFit(saved);
  }, []);
  const changeFit = (v) => {
    setFit(v);
    if (typeof window !== "undefined") window.localStorage.setItem(FIT_KEY, v);
  };

  const [retry, setRetry] = useState(0);
  useEffect(() => {
    if (!id) return undefined;
    let active = true;
    let retryTimer = null;
    setLoading(true);
    if (retry === 0) window.scrollTo({ top: 0 });
    fetchChapterPages(id)
      .then((res) => {
        if (!active) return;
        setData(res);
        setError(null);
        if (getToken() && res.chapter) {
          pushHistory({ manga_id: res.chapter.manga_id, chapter_id: res.chapter.id }).catch(() => {});
        }
      })
      .catch((err) => {
        if (!active) return;
        setError(err.message);
        // Auto-retry the chapter load in the background (capped) so a transient
        // network/API hiccup heals itself instead of showing an error.
        if (retry < 6) retryTimer = setTimeout(() => active && setRetry((r) => r + 1), 3000);
      })
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
      if (retryTimer) clearTimeout(retryTimer);
    };
  }, [id, retry]);
  // Reset the retry counter on chapter change.
  useEffect(() => setRetry(0), [id]);

  const chapter = data?.chapter;
  const pages = data?.pages || [];
  const prevId = data?.prev_chapter_id;
  const nextId = data?.next_chapter_id;

  // Self-healing pages: source CDN URLs expire after a few days, so a chapter
  // that loaded once can later show broken images. When a page image fails we
  // ask the backend to re-scrape fresh URLs; the new URLs flow back into the
  // pages and each image reloads automatically. Throttled + capped per chapter
  // so concurrent page failures don't hammer the source.
  const refreshingRef = useRef(false);
  const lastRefreshRef = useRef(0);
  const refreshCountRef = useRef(0);
  useEffect(() => {
    // Reset the per-chapter re-resolve budget whenever the chapter changes.
    lastRefreshRef.current = 0;
    refreshCountRef.current = 0;
  }, [id]);
  const refreshPages = useCallback(async () => {
    if (!id || refreshingRef.current) return;
    const now = Date.now();
    if (now - lastRefreshRef.current < 8000) return;
    if (refreshCountRef.current >= 5) return;
    refreshingRef.current = true;
    lastRefreshRef.current = now;
    refreshCountRef.current += 1;
    try {
      const res = await fetchChapterPages(id, { refresh: true });
      if (res?.pages?.length) setData(res);
    } catch {
      /* keep retrying silently */
    } finally {
      refreshingRef.current = false;
    }
  }, [id]);
  const onImageError = useCallback(() => {
    refreshPages();
  }, [refreshPages]);

  // If a chapter loads with zero pages (e.g. the very first resolve hit the
  // source while it was briefly unavailable), keep trying in the background
  // instead of showing a "no pages" dead end.
  useEffect(() => {
    if (loading || error) return undefined;
    if (data && (data.pages?.length || 0) === 0) {
      const t = setTimeout(() => refreshPages(), 4000);
      return () => clearTimeout(t);
    }
    return undefined;
  }, [data, loading, error, refreshPages]);

  // Newest-first chapter directory for the chapter drawer, with a live filter.
  const chaptersNewestFirst = chapters.slice().reverse();
  const filteredChapters = chapterQuery.trim()
    ? chaptersNewestFirst.filter((c) => {
        const q = chapterQuery.trim().toLowerCase();
        return (
          String(c.chapter_number).toLowerCase().includes(q) ||
          decodeEntities(c.title).toLowerCase().includes(q)
        );
      })
    : chaptersNewestFirst;

  // Load the full chapter directory for the jump-to selector.
  useEffect(() => {
    if (!chapter?.manga_slug) return;
    let active = true;
    fetchManga(chapter.manga_slug)
      .then((res) => active && setChapters(res.chapters || []))
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [chapter?.manga_slug]);

  const go = useCallback(
    (target) => {
      if (target) router.push(`/reader/?id=${target}`);
    },
    [router]
  );

  // Tap the artwork to hide/show the option bars (ignore taps on controls).
  const toggleChrome = (e) => {
    if (e.target.closest("button, a, select, input")) return;
    setChrome((c) => !c);
    setShowSettings(false);
    setShowInfo(false);
  };

  // Close transient panels with Escape.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key !== "Escape") return;
      setShowChapters(false);
      setShowSettings(false);
      setShowInfo(false);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  // Keyboard navigation: ←/→ jump chapters, Home returns to the series.
  useEffect(() => {
    const onKey = (e) => {
      if (e.target.matches?.("input, textarea")) return;
      if (e.key === "ArrowLeft" && prevId) go(prevId);
      else if (e.key === "ArrowRight" && nextId) go(nextId);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [prevId, nextId, go]);

  // Scroll progress indicator.
  useEffect(() => {
    const onScroll = () => {
      const h = document.documentElement.scrollHeight - window.innerHeight;
      setProgress(h > 0 ? Math.min(100, (window.scrollY / h) * 100) : 0);
      setShowTop(window.scrollY > 700);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    return () => window.removeEventListener("scroll", onScroll);
  }, [data]);

  // Warm the next chapter on the backend (lazy page resolver) so it opens fast.
  const warmedRef = useRef(null);
  useEffect(() => {
    if (!nextId || warmedRef.current === nextId) return;
    warmedRef.current = nextId;
    fetchChapterPages(nextId).catch(() => {});
  }, [nextId]);

  return (
    <div className={`reader${chrome ? "" : " chrome-hidden"}`}>
      <div className="reader-progress" style={{ width: `${progress}%` }} />

      <div className="reader-bar top">
        <Link
          href={chapter ? `/manga/?slug=${chapter.manga_slug}` : "/"}
          className="btn btn-ghost reader-nav-btn"
        >
          <Icon name="chevronLeft" size={16} /> Series
        </Link>
        <div className="reader-title">
          {chapter
            ? `${decodeEntities(chapter.manga_title)} · ${decodeEntities(chapter.title)}`
            : "Loading…"}
        </div>
        <div className="reader-bar-actions">
          <button
            className="btn btn-ghost reader-nav-btn"
            onClick={() => setShowSettings((s) => !s)}
            aria-label="Reader settings"
          >
            <Icon name="settings" size={16} /> Settings
          </button>
          <Link href="/home" className="btn btn-ghost reader-nav-btn">
            <Icon name="home" size={16} />
          </Link>
        </div>
      </div>

      {showSettings && (
        <div className="reader-settings">
          <div className="reader-settings-row">
            <span className="reader-settings-label">Image fit</span>
            <div className="seg">
              {FIT_MODES.map(([v, l]) => (
                <button
                  key={v}
                  className={`seg-btn${fit === v ? " active" : ""}`}
                  onClick={() => changeFit(v)}
                >
                  {l}
                </button>
              ))}
            </div>
          </div>
          <div className="reader-settings-row">
            <span className="reader-settings-label">Chapters</span>
            <button
              className="btn btn-ghost"
              onClick={() => {
                setShowSettings(false);
                setShowChapters(true);
              }}
            >
              <Icon name="list" size={15} /> Browse all chapters
            </button>
          </div>
          <p className="reader-settings-hint">
            Tip: tap the page to hide these bars · use ← and → to change chapters.
          </p>
        </div>
      )}

      <AdSlot slot="chapter" className="ad-slot-chapter" />

      <div className={`reader-stage fit-${fit}`} onClick={toggleChrome}>
        {!id || loading || error || pages.length === 0 ? (
          <div className="center-state reader-loading-state">
            <span className="reader-spinner lg" />
            <p>Streaming pages…</p>
          </div>
        ) : (
          pages.map((p, i) => (
            <ReaderImage
              key={p.page_number}
              page={p}
              fit={fit}
              eager={i < 2}
              onError={onImageError}
            />
          ))
        )}
      </div>

      {!loading && !error && chapter && (
        <div className="reader-end">
          {chapter.created_at && (
            <p className="reader-published">
              <Icon name="clock" size={13} /> Published {formatDateTime(chapter.created_at)}
            </p>
          )}
          <div className="reader-end-nav">
            <button className="btn btn-ghost" disabled={!prevId} onClick={() => go(prevId)}>
              <Icon name="chevronLeft" size={16} /> Previous chapter
            </button>
            <button className="btn btn-primary" disabled={!nextId} onClick={() => go(nextId)}>
              Next chapter <Icon name="chevronRight" size={16} />
            </button>
          </div>
        </div>
      )}

      {!loading && !error && chapter && (
        <div className="container" style={{ maxWidth: 900, margin: "40px auto 120px" }}>
          <div className="section-head">
            <span className="bar" />
            <h2>Chapter discussion</h2>
          </div>
          <Comments chapterId={chapter.id} />
        </div>
      )}

      {chapter && (
        <button
          type="button"
          className={`reader-to-top${showTop ? " show" : ""}`}
          aria-label="Back to top"
          onClick={() => window.scrollTo({ top: 0, behavior: "smooth" })}
        >
          <Icon name="arrowUp" size={20} />
        </button>
      )}

      {showInfo && chapter && (
        <div className="reader-info-panel">
          <div className="reader-info-head">
            <h3><Icon name="info" size={16} /> Chapter info</h3>
            <button className="btn btn-ghost reader-nav-btn" onClick={() => setShowInfo(false)} aria-label="Close info">
              <Icon name="close" size={16} />
            </button>
          </div>
          <div className="reader-info-grid">
            <div><div className="k">Series</div><div className="v">{decodeEntities(chapter.manga_title)}</div></div>
            <div><div className="k">Chapter</div><div className="v">{decodeEntities(chapter.title) || `Chapter ${chapter.chapter_number}`}</div></div>
            <div><div className="k">Number</div><div className="v">Ch. {chapter.chapter_number}</div></div>
            <div><div className="k">Pages</div><div className="v">{pages.length}</div></div>
            {chapter.created_at && (
              <div><div className="k">Published</div><div className="v">{formatDateTime(chapter.created_at)}</div></div>
            )}
          </div>
        </div>
      )}

      {!loading && !error && chapter && (
        <div className="reader-bar bottom">
          <button
            className="reader-pill-btn"
            disabled={!prevId}
            onClick={() => go(prevId)}
            aria-label="Previous chapter"
          >
            <Icon name="chevronLeft" size={18} />
          </button>
          <button
            className={`reader-pill-btn${showInfo ? " active" : ""}`}
            onClick={() => setShowInfo((s) => !s)}
            aria-label="Chapter info"
            aria-expanded={showInfo}
          >
            <Icon name="info" size={18} />
          </button>
          <button
            className="reader-ch-btn"
            onClick={() => setShowChapters(true)}
            aria-label="Select chapter"
          >
            <Icon name="list" size={16} />
            <span>Ch. {chapter.chapter_number}</span>
            <Icon name="chevronUp" size={14} className="reader-ch-caret" />
          </button>
          <button
            className="reader-pill-btn primary"
            disabled={!nextId}
            onClick={() => go(nextId)}
            aria-label="Next chapter"
          >
            <Icon name="chevronRight" size={18} />
          </button>
        </div>
      )}

      {showChapters && (
        <div
          className="chapter-drawer-overlay"
          onClick={() => setShowChapters(false)}
          role="presentation"
        >
          <div className="chapter-drawer" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Chapters">
            <div className="chapter-drawer-grab" />
            <div className="chapter-drawer-head">
              <h3><Icon name="list" size={17} /> Chapters <span className="faint">{chapters.length}</span></h3>
              <button className="reader-pill-btn" onClick={() => setShowChapters(false)} aria-label="Close">
                <Icon name="close" size={18} />
              </button>
            </div>
            <div className="chapter-drawer-search">
              <Icon name="search" size={16} />
              <input
                autoFocus
                value={chapterQuery}
                onChange={(e) => setChapterQuery(e.target.value)}
                placeholder="Search chapter number or title…"
                aria-label="Search chapters"
              />
              {chapterQuery && (
                <button className="chapter-drawer-clear" onClick={() => setChapterQuery("")} aria-label="Clear">
                  <Icon name="close" size={14} />
                </button>
              )}
            </div>
            <div className="chapter-drawer-list">
              {filteredChapters.length === 0 ? (
                <div className="chapter-drawer-empty">No chapters match “{chapterQuery}”.</div>
              ) : (
                filteredChapters.map((c) => {
                  const current = String(c.id) === String(id);
                  const title = decodeEntities(c.title);
                  const hasSub = title && title.toLowerCase() !== `chapter ${c.chapter_number}`.toLowerCase();
                  return (
                    <button
                      key={c.id}
                      className={`chapter-drawer-item${current ? " current" : ""}`}
                      onClick={() => {
                        setShowChapters(false);
                        setChapterQuery("");
                        if (!current) go(c.id);
                      }}
                    >
                      <span className="cd-no">Ch. {c.chapter_number}</span>
                      {hasSub && <span className="cd-title">{title}</span>}
                      {current && <Icon name="check" size={16} className="cd-check" />}
                    </button>
                  );
                })
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
