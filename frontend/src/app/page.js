"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import Navbar from "../components/Navbar";
import MangaCard from "../components/MangaCard";
import { GridSkeleton } from "../components/Skeletons";
import { fetchMangaList, proxyImage, decodeEntities } from "../lib/api";

export default function HomePage() {
  const [all, setAll] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [query, setQuery] = useState("");

  useEffect(() => {
    let active = true;
    setLoading(true);
    fetchMangaList({ limit: 60 })
      .then((res) => {
        if (!active) return;
        setAll(res.data || []);
        setError(null);
      })
      .catch((err) => active && setError(err.message))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return all;
    return all.filter((m) => decodeEntities(m.title).toLowerCase().includes(q));
  }, [all, query]);

  const hero = all[0];

  return (
    <>
      <Navbar query={query} onQuery={setQuery} />
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
              <p>{decodeEntities(hero.synopsis)}</p>
              <div>
                <Link href={`/manga/${hero.slug}/`} className="btn btn-primary">
                  Read Now ›
                </Link>
              </div>
            </div>
          </section>
        ) : null}

        <section className="section" id="hot">
          <div className="section-head">
            <span className="bar" />
            <h2>{query ? `Results for "${query}"` : "Hot Updates"}</h2>
          </div>

          {error && (
            <div className="center-state">
              <p>Could not reach the engine.</p>
              <p style={{ color: "var(--text-faint)", fontSize: 13 }}>{error}</p>
            </div>
          )}

          {loading ? (
            <GridSkeleton count={18} />
          ) : filtered.length === 0 && !error ? (
            <div className="center-state">No titles match your search.</div>
          ) : (
            <div className="grid">
              {filtered.map((m) => (
                <MangaCard key={m.id} manga={m} />
              ))}
            </div>
          )}
        </section>
      </main>
      <footer className="footer">
        <div className="container">XCOMIX · neon manga engine · streamed, never stored</div>
      </footer>
    </>
  );
}
