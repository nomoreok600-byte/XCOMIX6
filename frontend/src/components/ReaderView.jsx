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
import Select from "./Select";

const FIT_KEY = "xcomix_reader_fit";
const FIT_MODES = [
  ["width", "Fit width"],
  ["height", "Fit height"],
  ["native", "Original"],
];

function ReaderImage({ page, eager, fit, onError, onManualRetry }) {
  const [status, setStatus] = useState("loading");
  const [attempt, setAttempt] = useState(0);
  const src = proxyImage(page.source_url);
  // Cache-bust on retry so a transient proxy failure can recover.
  const finalSrc = attempt > 0 ? `${src}${src.includes("?") ? "&" : "?"}r=${attempt}` : src;

  // When the parent swaps in a freshly re-scraped URL for this page (because the
  // old source CDN link expired), clear the error state and load the new image.
  useEffect(() => {
    setStatus("loading");
    setAttempt(0);
  }, [page.source_url]);

  return (
    <div className={`reader-page fit-${fit}`}>
      {status === "error" ? (
        <div className="reader-fallback">
          <p>Page {page.page_number} could not be streamed.</p>
          <button
            className="btn btn-ghost"
            onClick={() => {
              setStatus("loading");
              setAttempt((a) => a + 1);
              onManualRetry?.();
            }}
          >
            Retry
          </button>
        </div>
      ) : (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={finalSrc}
          alt={`Page ${page.page_number}`}
          loading={eager ? "eager" : "lazy"}
          // eslint-disable-next-line react/no-unknown-property
          fetchpriority={eager ? "high" : "auto"}
          decoding="async"
          onLoad={() => setStatus("loaded")}
          onError={() => {
            setStatus("error");
            onError?.();
          }}
          style={status === "loading" ? { minHeight: 240, background: "var(--bg-2)" } : undefined}
        />
      )}
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

  useEffect(() => {
    if (!id) return;
    let active = true;
    setLoading(true);
    window.scrollTo({ top: 0 });
    fetchChapterPages(id)
      .then((res) => {
        if (!active) return;
        setData(res);
        setError(null);
        if (getToken() && res.chapter) {
          pushHistory({ manga_id: res.chapter.manga_id, chapter_id: res.chapter.id }).catch(() => {});
        }
      })
      .catch((err) => active && setError(err.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [id]);

  const chapter = data?.chapter;
  const pages = data?.pages || [];
  const prevId = data?.prev_chapter_id;
  const nextId = data?.next_chapter_id;

  // Self-healing pages: source CDN URLs expire after a few days, so a chapter
  // that loaded once can later show broken images. When a page image fails, ask
  // the backend to re-scrape fresh URLs (once per chapter to avoid loops); the
  // new URLs flow back into the pages and each image reloads automatically.
  const refreshingRef = useRef(false);
  const refreshedForRef = useRef(null);
  const refreshPages = useCallback(async () => {
    if (!id || refreshingRef.current) return;
    refreshingRef.current = true;
    try {
      const res = await fetchChapterPages(id, { refresh: true });
      if (res?.pages?.length) setData(res);
    } catch {
      /* keep showing the retry fallback */
    } finally {
      refreshingRef.current = false;
    }
  }, [id]);
  const onImageError = useCallback(() => {
    if (refreshedForRef.current === id) return;
    refreshedForRef.current = id;
    refreshPages();
  }, [id, refreshPages]);

  // Newest-first options for the custom chapter picker.
  const chapterOptions = chapters
    .slice()
    .reverse()
    .map((c) => ({
      value: String(c.id),
      label: decodeEntities(c.title) || `Chapter ${c.chapter_number}`,
    }));

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
  };

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
            <span className="reader-settings-label">Jump to chapter</span>
            <Select
              className="reader-select"
              ariaLabel="Jump to chapter"
              searchable
              value={id || ""}
              onChange={(v) => go(v)}
              options={chapterOptions}
              placeholder={`Chapter ${chapter?.chapter_number || ""}`}
            />
          </div>
          <p className="reader-settings-hint">
            Tip: tap the page to hide these bars · use ← and → to change chapters.
          </p>
        </div>
      )}

      <AdSlot slot="chapter" className="ad-slot-chapter" />

      <div className={`reader-stage fit-${fit}`} onClick={toggleChrome}>
        {!id || loading ? (
          <div className="center-state">Streaming pages…</div>
        ) : error ? (
          <div className="center-state">
            <p>Could not load this chapter.</p>
            <p style={{ color: "var(--text-faint)", fontSize: 13 }}>{error}</p>
          </div>
        ) : pages.length === 0 ? (
          <div className="center-state">No pages found for this chapter.</div>
        ) : (
          pages.map((p, i) => (
            <ReaderImage
              key={p.page_number}
              page={p}
              fit={fit}
              eager={i < 2}
              onError={onImageError}
              onManualRetry={refreshPages}
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

      {!loading && !error && chapter && (
        <div className="reader-bar bottom">
          <button className="btn btn-ghost reader-nav-btn" disabled={!prevId} onClick={() => go(prevId)}>
            <Icon name="chevronLeft" size={16} /> Prev
          </button>
          <Select
            className="reader-select compact"
            ariaLabel="Jump to chapter"
            searchable
            up
            value={id || ""}
            onChange={(v) => go(v)}
            options={chapterOptions.length ? chapterOptions : [{ value: id, label: `Ch. ${chapter.chapter_number}` }]}
            placeholder={`Ch. ${chapter.chapter_number}`}
          />
          <button className="btn btn-primary reader-nav-btn" disabled={!nextId} onClick={() => go(nextId)}>
            Next <Icon name="chevronRight" size={16} />
          </button>
        </div>
      )}
    </div>
  );
}
