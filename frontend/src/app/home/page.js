"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import MangaCard from "../../components/MangaCard";
import MangaGrid from "../../components/MangaGrid";
import { GridSkeleton } from "../../components/Skeletons";
import { fetchRecent, fetchPopular, fetchCompleted, proxyImage, decodeEntities } from "../../lib/api";

export default function HomePage() {
  const [recent, setRecent] = useState([]);
  const [popular, setPopular] = useState([]);
  const [completed, setCompleted] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [query, setQuery] = useState("");

  useEffect(() => {
    let active = true;
    setLoading(true);
    Promise.all([fetchRecent(36), fetchPopular(12), fetchCompleted(12)])
      .then(([r, p, c]) => {
        if (!active) return;
        setRecent(r.data || []);
        setPopular(p.data || []);
        setCompleted(c.data || []);
        setError(null);
      })
      .catch((err) => active && setError(err.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  const hero = popular[0] || recent[0];
  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return recent;
    return recent.filter((m) => decodeEntities(m.title).toLowerCase().includes(q));
  }, [recent, query]);

  return (
    <>
      <SiteNav query={query} onQuery={setQuery} />
      <main className="container">
        {loading ? (
          <div className="hero skeleton" style={{ minHeight: 320 }} />
        ) : hero ? (
          <section className="hero" id="featured">
            <div className="hero-bg" style={{ backgroundImage: `url(${proxyImage(hero.cover_url)})` }} />
            <div className="hero-veil" />
            <div className="hero-content">
              <div className="detail-tags">
                {hero.is_18_plus && <span className="tag adult">18+</span>}
                <span className="tag type">{hero.type}</span>
                <span className="tag ongoing">{hero.status}</span>
              </div>
              <h1>{decodeEntities(hero.title)}</h1>
              <div>
                <Link href={`/manga/?slug=${encodeURIComponent(hero.slug)}`} className="btn btn-primary">
                  Read Now ›
                </Link>
              </div>
            </div>
          </section>
        ) : null}

        {error && (
          <div className="center-state">
            <p>Could not reach the engine.</p>
            <p style={{ color: "var(--text-faint)", fontSize: 13 }}>{error}</p>
          </div>
        )}

        {!query && (
          <section className="section" id="popular">
            <div className="section-head">
              <span className="bar" />
              <h2>🔥 Popular</h2>
            </div>
            {loading ? <GridSkeleton count={12} /> : (
              <div className="grid">{popular.map((m) => <MangaCard key={m.id} manga={m} />)}</div>
            )}
          </section>
        )}

        <section className="section" id="latest">
          <div className="section-head">
            <span className="bar" />
            <h2>{query ? `Results for "${query}"` : "⚡ Latest Updates"}</h2>
          </div>
          <MangaGrid items={filtered} loading={loading} empty="No titles match your search." />
        </section>

        {!query && completed.length > 0 && (
          <section className="section" id="completed">
            <div className="section-head">
              <span className="bar" />
              <h2>✅ Completed</h2>
              <Link href="/browse?status=Completed" className="faint" style={{ marginLeft: "auto" }}>
                View all →
              </Link>
            </div>
            <div className="grid">{completed.map((m) => <MangaCard key={m.id} manga={m} />)}</div>
          </section>
        )}
      </main>
      <footer className="footer">
        <div className="container">XCOMIX · neon manga engine · streamed, never stored</div>
      </footer>
    </>
  );
}
