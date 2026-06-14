"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import MangaCard from "../../components/MangaCard";
import MangaGrid from "../../components/MangaGrid";
import Slider from "../../components/Slider";
import Footer from "../../components/Footer";
import Avatar from "../../components/Avatar";
import Icon from "../../components/Icon";
import { GridSkeleton } from "../../components/Skeletons";
import {
  fetchRecent,
  fetchPopular,
  fetchCompleted,
  fetchLatestComments,
  proxyImage,
  decodeEntities,
} from "../../lib/api";

export default function HomePage() {
  const [recent, setRecent] = useState([]);
  const [popular, setPopular] = useState([]);
  const [completed, setCompleted] = useState([]);
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [query, setQuery] = useState("");
  const [heroIdx, setHeroIdx] = useState(0);
  const [shown, setShown] = useState(12);

  useEffect(() => {
    let active = true;
    setLoading(true);
    Promise.all([fetchRecent(48), fetchPopular(15), fetchCompleted(15)])
      .then(([r, p, c]) => {
        if (!active) return;
        setRecent(r.data || []);
        setPopular(p.data || []);
        setCompleted(c.data || []);
        setError(null);
      })
      .catch((err) => active && setError(err.message))
      .finally(() => active && setLoading(false));
    fetchLatestComments().then((d) => active && setComments(d.data || [])).catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  const heroes = popular.slice(0, 5);
  useEffect(() => {
    if (heroes.length < 2) return;
    const t = setInterval(() => setHeroIdx((i) => (i + 1) % heroes.length), 5000);
    return () => clearInterval(t);
  }, [heroes.length]);
  const hero = heroes[heroIdx] || recent[0];

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
          <div className="hero skeleton" style={{ minHeight: 280 }} />
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
              {heroes.length > 1 && (
                <div style={{ display: "flex", gap: 6, marginTop: 8 }}>
                  {heroes.map((_, i) => (
                    <span
                      key={i}
                      onClick={() => setHeroIdx(i)}
                      style={{
                        width: i === heroIdx ? 22 : 8,
                        height: 8,
                        borderRadius: 999,
                        background: i === heroIdx ? "var(--crimson)" : "rgba(255,255,255,0.3)",
                        cursor: "pointer",
                        transition: "width 0.2s",
                      }}
                    />
                  ))}
                </div>
              )}
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
              <h2><Icon name="flame" size={20} className="head-ico" /> Popular</h2>
              <Link href="/browse?order=popular" className="head-link">View all →</Link>
            </div>
            {loading ? <GridSkeleton count={8} /> : <Slider items={popular} />}
          </section>
        )}

        <section className="section" id="latest">
          <div className="section-head">
            <span className="bar" />
            <h2>{query ? `Results for "${query}"` : <><Icon name="bolt" size={20} className="head-ico" /> Recently Updated</>}</h2>
            {!query && <Link href="/recent" className="head-link">View all →</Link>}
          </div>
          <MangaGrid items={filtered.slice(0, shown)} loading={loading} empty="No titles match your search." />
          {!query && filtered.length > shown && (
            <div style={{ textAlign: "center", marginTop: 18 }}>
              <button className="btn btn-ghost" onClick={() => setShown((s) => s + 12)}>Load more ▾</button>
            </div>
          )}
          {!query && (
            <div style={{ textAlign: "center", marginTop: 12 }}>
              <Link href="/recent" className="btn btn-primary">See all recent updates →</Link>
            </div>
          )}
        </section>

        {!query && completed.length > 0 && (
          <section className="section" id="completed">
            <div className="section-head">
              <span className="bar" />
              <h2><Icon name="checkCircle" size={20} className="head-ico" /> Completed</h2>
              <Link href="/browse?status=Completed" className="head-link">View all →</Link>
            </div>
            <Slider items={completed} />
          </section>
        )}

        {!query && comments.length > 0 && (
          <section className="section" id="comments">
            <div className="section-head">
              <span className="bar" />
              <h2><Icon name="comment" size={20} className="head-ico" /> Latest Comments</h2>
            </div>
            <div className="list-rows">
              {comments.map((c) => (
                <Link
                  key={c.id}
                  href={c.manga_slug ? `/manga/?slug=${encodeURIComponent(c.manga_slug)}` : "/community"}
                  className="list-row"
                  style={{ textDecoration: "none" }}
                >
                  <Avatar user={c.user} />
                  <div className="info">
                    <div className="meta-line">
                      <span className="kind">@{c.user.username}</span>
                      {c.manga_title && <span>{decodeEntities(c.manga_title)}</span>}
                      <span>{new Date(c.created_at).toLocaleString()}</span>
                    </div>
                    <p className="excerpt" style={{ margin: "4px 0 0" }}>{c.body}</p>
                  </div>
                </Link>
              ))}
            </div>
          </section>
        )}
      </main>
      <Footer />
    </>
  );
}
