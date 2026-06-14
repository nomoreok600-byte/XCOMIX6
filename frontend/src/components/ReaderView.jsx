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
} from "../lib/api";
import Comments from "./Comments";

const FIT_KEY = "xcomix_reader_fit";
const FIT_MODES = [
  ["width", "Fit width"],
  ["height", "Fit height"],
  ["native", "Original"],
];

function ReaderImage({ page, eager, fit }) {
  const [status, setStatus] = useState("loading");
  const [attempt, setAttempt] = useState(0);
  const src = proxyImage(page.source_url);
  // Cache-bust on retry so a transient proxy failure can recover.
  const finalSrc = attempt > 0 ? `${src}${src.includes("?") ? "&" : "?"}r=${attempt}` : src;

  return (
    <div className={`reader-page fit-${fit}`}>
      {status === "error" ? (
        <div className="reader-fallback">
          <p>Page {page.page_number} could not be streamed.</p>
          <button
            className="btn btn-ghost"
            onClick={() => (setStatus("loading"), setAttempt((a) => a + 1))}
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
          onError={() => setStatus("error")}
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
    <div className="reader">
      <div className="reader-progress" style={{ width: `${progress}%` }} />

      <div className="reader-bar top">
        <Link
          href={chapter ? `/manga/?slug=${chapter.manga_slug}` : "/"}
          className="btn btn-ghost reader-nav-btn"
        >
          ‹ Series
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
            ⚙ Settings
          </button>
          <Link href="/home" className="btn btn-ghost reader-nav-btn">
            Home
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
            <select
              className="reader-select"
              value={id || ""}
              onChange={(e) => go(e.target.value)}
            >
              {chapters
                .slice()
                .reverse()
                .map((c) => (
                  <option key={c.id} value={c.id}>
                    {decodeEntities(c.title) || `Chapter ${c.chapter_number}`}
                  </option>
                ))}
            </select>
          </div>
          <p className="reader-settings-hint">
            Tip: use ← and → on your keyboard to move between chapters.
          </p>
        </div>
      )}

      <div className={`reader-stage fit-${fit}`}>
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
            <ReaderImage key={p.page_number} page={p} fit={fit} eager={i < 2} />
          ))
        )}
      </div>

      {!loading && !error && chapter && (
        <div className="reader-end">
          <div className="reader-end-nav">
            <button className="btn btn-ghost" disabled={!prevId} onClick={() => go(prevId)}>
              ‹ Previous chapter
            </button>
            <button className="btn btn-primary" disabled={!nextId} onClick={() => go(nextId)}>
              Next chapter ›
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
            ‹ Prev
          </button>
          <select
            className="reader-select compact"
            value={id || ""}
            onChange={(e) => go(e.target.value)}
            aria-label="Jump to chapter"
          >
            {chapters
              .slice()
              .reverse()
              .map((c) => (
                <option key={c.id} value={c.id}>
                  {decodeEntities(c.title) || `Chapter ${c.chapter_number}`}
                </option>
              ))}
            {chapters.length === 0 && <option value={id}>Ch. {chapter.chapter_number}</option>}
          </select>
          <button className="btn btn-primary reader-nav-btn" disabled={!nextId} onClick={() => go(nextId)}>
            Next ›
          </button>
        </div>
      )}
    </div>
  );
}
