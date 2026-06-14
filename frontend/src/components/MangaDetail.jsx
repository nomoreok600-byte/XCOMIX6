"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import Navbar from "./Navbar";
import { fetchManga, proxyImage, decodeEntities } from "../lib/api";

export default function MangaDetail({ slug }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

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

  return (
    <>
      <Navbar />
      <main className="container">
        <Link href="/" className="back-link">
          ‹ Back to library
        </Link>

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
              <div
                className="detail-hero-bg"
                style={{ backgroundImage: `url(${proxyImage(manga.cover_url)})` }}
              />
              <div className="detail-hero-veil" />
              <div className="detail-grid">
                <div className="detail-cover">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={proxyImage(manga.cover_url)} alt={decodeEntities(manga.title)} />
                </div>
                <div className="detail-info">
                  <h1>{decodeEntities(manga.title)}</h1>
                  <div className="detail-tags">
                    {manga.is_18_plus && <span className="tag adult">18+</span>}
                    <span className="tag type">{manga.type}</span>
                    <span className="tag ongoing">{manga.status}</span>
                    <span className="tag">{chapters.length} Chapters</span>
                  </div>
                  <p className="detail-synopsis">{decodeEntities(manga.synopsis) || "No synopsis available."}</p>
                  <div className="detail-actions">
                    {firstChapter && (
                      <Link href={`/reader/?id=${firstChapter.id}`} className="btn btn-primary">
                        Read First Chapter
                      </Link>
                    )}
                    {lastChapter && lastChapter.id !== firstChapter?.id && (
                      <Link href={`/reader/?id=${lastChapter.id}`} className="btn btn-ghost">
                        Latest Chapter
                      </Link>
                    )}
                  </div>
                </div>
              </div>
            </section>

            <section className="section">
              <div className="section-head">
                <span className="bar" />
                <h2>Chapters</h2>
              </div>
              {chapters.length === 0 ? (
                <div className="center-state">No chapters indexed yet.</div>
              ) : (
                <div className="chapter-list">
                  {chapters
                    .slice()
                    .reverse()
                    .map((ch) => (
                      <Link key={ch.id} href={`/reader/?id=${ch.id}`} className="chapter-row">
                        <span className="num">
                          {decodeEntities(ch.title) || `Chapter ${ch.chapter_number}`}
                        </span>
                        <span className="date">#{ch.chapter_number}</span>
                      </Link>
                    ))}
                </div>
              )}
            </section>
          </>
        )}
      </main>
      <footer className="footer">
        <div className="container">XCOMIX · neon manga engine</div>
      </footer>
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
