"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { fetchChapterPages, proxyImage, decodeEntities } from "../lib/api";

function ReaderImage({ page }) {
  const [status, setStatus] = useState("loading");
  const [attempt, setAttempt] = useState(0);
  const src = proxyImage(page.source_url);
  // Cache-bust on retry so a transient proxy failure can recover.
  const finalSrc = attempt > 0 ? `${src}${src.includes("?") ? "&" : "?"}r=${attempt}` : src;

  return (
    <div className="reader-page">
      {status === "error" ? (
        <div className="reader-fallback">
          <p>Page {page.page_number} could not be streamed.</p>
          <button className="btn btn-ghost" onClick={() => (setStatus("loading"), setAttempt((a) => a + 1))}>
            Retry
          </button>
        </div>
      ) : (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={finalSrc}
          alt={`Page ${page.page_number}`}
          loading="lazy"
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

  useEffect(() => {
    if (!id) return;
    let active = true;
    setLoading(true);
    window.scrollTo({ top: 0 });
    fetchChapterPages(id)
      .then((res) => active && (setData(res), setError(null)))
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

  const go = (target) => target && router.push(`/reader/?id=${target}`);

  return (
    <div className="reader">
      <div className="reader-bar top">
        <Link href={chapter ? `/manga/?slug=${chapter.manga_slug}` : "/"} className="btn btn-ghost reader-nav-btn">
          ‹ Series
        </Link>
        <div className="reader-title">
          {chapter
            ? `${decodeEntities(chapter.manga_title)} · ${decodeEntities(chapter.title)}`
            : "Loading…"}
        </div>
        <Link href="/" className="btn btn-ghost reader-nav-btn">
          Home
        </Link>
      </div>

      <div className="reader-stage">
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
          pages.map((p) => <ReaderImage key={p.page_number} page={p} />)
        )}
      </div>

      {!loading && !error && chapter && (
        <div className="reader-bar bottom">
          <button className="btn btn-ghost reader-nav-btn" disabled={!prevId} onClick={() => go(prevId)}>
            ‹ Prev
          </button>
          <span className="reader-title">Ch. {chapter.chapter_number}</span>
          <button className="btn btn-primary reader-nav-btn" disabled={!nextId} onClick={() => go(nextId)}>
            Next ›
          </button>
        </div>
      )}
    </div>
  );
}
