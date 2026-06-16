"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "./SiteNav";
import Footer from "./Footer";
import LibraryButton from "./LibraryButton";
import Reviews from "./Reviews";
import Comments from "./Comments";
import Stars from "./Stars";
import Icon from "./Icon";
import AdSlot from "./AdSlot";
import { fetchManga, proxyImage, decodeEntities, formatDateTime, timeAgo, isFresh } from "../lib/api";

export default function MangaDetail({ slug }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState("chapters");

  useEffect(() => {
    if (!slug) return;
    let active = true;
    setLoading(true);
    fetchManga(slug)
      .then((res) => active && (setData(res), setError(null)))
      .catch((err) => active && setError(err.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [slug]);

  const manga = data?.manga;
  const chapters = data?.chapters || [];
  const firstChapter = chapters[0];
  const lastChapter = chapters[chapters.length - 1];

  // Client-side SEO: reflect the title/description for shares + crawlers that run JS.
  useEffect(() => {
    if (!manga) return;
    const title = `Read ${decodeEntities(manga.title)} ${manga.type} Online · XCOMIX`;
    document.title = title;
    const desc = (decodeEntities(manga.synopsis) || `Read ${decodeEntities(manga.title)} online free on XCOMIX.`).slice(0, 160);
    let tag = document.querySelector('meta[name="description"]');
    if (!tag) { tag = document.createElement("meta"); tag.setAttribute("name", "description"); document.head.appendChild(tag); }
    tag.setAttribute("content", desc);
  }, [manga]);

  return (
    <>
      <SiteNav />
      <main className="container">
        <Link href="/home" className="back-link"><Icon name="chevronLeft" size={15} /> Back to home</Link>

        {!slug || loading ? (
          <DetailSkeleton />
        ) : error ? (
          <div className="center-state">
            <p>Could not load this title.</p>
            <p style={{ color: "var(--text-faint)", fontSize: 13 }}>{error}</p>
          </div>
        ) : !manga ? (
          <div className="center-state">Title not found.</div>
        ) : (
          <>
            <section className="detail-hero">
              <div className="detail-hero-bg" style={{ backgroundImage: `url(${proxyImage(manga.cover_url)})` }} />
              <div className="detail-hero-veil" />
              <div className="detail-grid">
                <div className="detail-cover">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={proxyImage(manga.cover_url)} alt={decodeEntities(manga.title)} />
                </div>
                <div className="detail-info">
                  <h1>{decodeEntities(manga.title)}</h1>
                  {manga.alt_title && <p className="faint" style={{ marginTop: -4 }}>{decodeEntities(manga.alt_title)}</p>}
                  <div className="detail-tags">
                    {manga.is_18_plus && <span className="tag adult">18+</span>}
                    <span className="tag type">{manga.type}</span>
                    <span className="tag ongoing">{manga.status}</span>
                    <span className="tag">{chapters.length} Chapters</span>
                  </div>
                  <div className="detail-meta">
                    {manga.author && (
                      <span><Icon name="user" size={13} /> {decodeEntities(manga.author)}</span>
                    )}
                    {manga.views != null && (
                      <span><Icon name="chart" size={13} /> {Number(manga.views).toLocaleString()} views</span>
                    )}
                    {manga.last_chapter_at && (
                      <span title={formatDateTime(manga.last_chapter_at)}>
                        <Icon name="clock" size={13} /> Updated {timeAgo(manga.last_chapter_at)}
                      </span>
                    )}
                  </div>
                  {manga.rating?.avg != null && (
                    <div className="rating-line">
                      <Stars value={manga.rating.avg} />
                      <span className="faint">{manga.rating.avg.toFixed(1)} · {manga.rating.count} reviews</span>
                    </div>
                  )}
                  {manga.genres?.length > 0 && (
                    <div className="genre-chips" style={{ marginBottom: 14 }}>
                      {manga.genres.map((g) => (
                        <Link key={g.id} href={`/browse?genre=${g.slug}`} className="chip">{g.name}</Link>
                      ))}
                    </div>
                  )}
                  <p className="detail-synopsis">{decodeEntities(manga.synopsis) || "No synopsis available."}</p>
                  <div className="detail-actions">
                    {firstChapter && (
                      <Link href={`/reader/?id=${firstChapter.id}`} className="btn btn-primary">Read First</Link>
                    )}
                    {lastChapter && lastChapter.id !== firstChapter?.id && (
                      <Link href={`/reader/?id=${lastChapter.id}`} className="btn btn-ghost">Latest Chapter</Link>
                    )}
                    <LibraryButton mangaId={manga.id} />
                  </div>
                </div>
              </div>
            </section>

            <AdSlot slot="manga" className="ad-slot-manga" />

            <div className="tabs">
              {[["chapters", `Chapters (${chapters.length})`], ["about", "About"], ["reviews", "Reviews"], ["comments", "Comments"]].map(([v, l]) => (
                <button key={v} className={`tab${tab === v ? " active" : ""}`} onClick={() => setTab(v)}>{l}</button>
              ))}
            </div>

            {tab === "chapters" && (
              chapters.length === 0 ? (
                <div className="center-state">No chapters indexed yet.</div>
              ) : (
                <div className="chapter-list">
                  {chapters.slice().reverse().map((ch) => {
                    const title = decodeEntities(ch.title);
                    const hasSub = title && title.toLowerCase() !== `chapter ${ch.chapter_number}`.toLowerCase();
                    return (
                      <Link key={ch.id} href={`/reader/?id=${ch.id}`} className="chapter-row">
                        <span className="ch-left">
                          <span className="ch-head">
                            <span className="ch-no">Ch. {ch.chapter_number}</span>
                            {isFresh(ch.created_at) && <span className="ch-new">NEW</span>}
                          </span>
                          <span className="ch-sub">{hasSub ? title : "—"}</span>
                        </span>
                        {ch.created_at && (
                          <span className="ch-time" title={formatDateTime(ch.created_at)}>
                            <Icon name="clock" size={12} /> {timeAgo(ch.created_at)}
                          </span>
                        )}
                      </Link>
                    );
                  })}
                </div>
              )
            )}

            {tab === "about" && (
              <div className="panel-box">
                <p><b>Title:</b> {decodeEntities(manga.title)}</p>
                {manga.alt_title && <p><b>Alternative:</b> {decodeEntities(manga.alt_title)}</p>}
                {manga.author && <p><b>Author:</b> {decodeEntities(manga.author)}</p>}
                <p><b>Type:</b> {manga.type} · <b>Status:</b> {manga.status}</p>
                <p><b>Genres:</b> {manga.genres?.map((g) => g.name).join(", ") || "—"}</p>
                <p><b>Views:</b> {manga.views} · <b>Source:</b> {manga.source || "—"}</p>
                <div className="divider" />
                <p className="muted">{decodeEntities(manga.synopsis) || "No synopsis available."}</p>
              </div>
            )}

            {tab === "reviews" && <Reviews mangaId={manga.id} />}
            {tab === "comments" && <Comments mangaId={manga.id} />}
          </>
        )}
      </main>
      <Footer />
    </>
  );
}

function DetailSkeleton() {
  return (
    <div className="detail-hero">
      <div className="detail-grid">
        <div className="skeleton detail-cover" />
        <div style={{ flex: 1, width: "100%" }}>
          <div className="skeleton skeleton-line" style={{ width: "60%", height: 28 }} />
          <div className="skeleton skeleton-line" style={{ width: "40%" }} />
          <div className="skeleton skeleton-line" style={{ width: "100%", marginTop: 20 }} />
          <div className="skeleton skeleton-line" style={{ width: "92%" }} />
          <div className="skeleton skeleton-line" style={{ width: "85%" }} />
        </div>
      </div>
    </div>
  );
}
