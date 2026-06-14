"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import MangaGrid from "../../components/MangaGrid";
import Footer from "../../components/Footer";
import Stars from "../../components/Stars";
import Icon from "../../components/Icon";
import { browse, fetchGenres, proxyImage, decodeEntities } from "../../lib/api";

const STATUSES = ["", "Ongoing", "Completed"];
const TYPES = ["", "Manga", "Manhwa", "Manhua"];
const ORDERS = [
  ["updated", "Latest"],
  ["popular", "Most popular"],
  ["newest", "Newest"],
  ["rating", "Top rated"],
  ["title", "A–Z"],
];
const PER_PAGE = 24;

function ListRow({ m }) {
  return (
    <Link href={`/manga/?slug=${encodeURIComponent(m.slug)}`} className="list-row" style={{ textDecoration: "none" }}>
      <div className="cover">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={proxyImage(m.cover_url)} alt={decodeEntities(m.title)} loading="lazy" />
      </div>
      <div className="info">
        <h3>{decodeEntities(m.title)}</h3>
        <div className="meta-line">
          <span className="kind">{m.type}</span>
          <span>{m.status}</span>
          {m.latest_chapter != null && <span>Ch. {m.latest_chapter}</span>}
          <Stars value={m.avg_rating || 0} />
        </div>
      </div>
    </Link>
  );
}

export default function BrowsePage() {
  const [genres, setGenres] = useState([]);
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState("list");
  const [page, setPage] = useState(0);
  const [f, setF] = useState({ genre: "", status: "", type: "", order: "updated", q: "" });

  useEffect(() => {
    const p = new URLSearchParams(window.location.search);
    setF((prev) => ({
      ...prev,
      genre: p.get("genre") || "",
      status: p.get("status") || "",
      type: p.get("type") || "",
      order: p.get("order") || "updated",
      q: p.get("q") || "",
    }));
    fetchGenres().then((d) => setGenres(d.data || [])).catch(() => {});
  }, []);

  const load = useCallback(() => {
    setLoading(true);
    browse({ ...f, limit: PER_PAGE, offset: page * PER_PAGE })
      .then((d) => {
        setItems(d.data || []);
        setTotal(d.total);
      })
      .finally(() => setLoading(false));
  }, [f, page]);

  useEffect(() => {
    load();
  }, [load]);

  const set = (k, v) => {
    setPage(0);
    setF((prev) => ({ ...prev, [k]: v }));
  };

  const pageCount = Math.max(1, Math.ceil(total / PER_PAGE));
  const pages = [];
  for (let i = Math.max(0, page - 2); i <= Math.min(pageCount - 1, page + 2); i += 1) pages.push(i);

  return (
    <>
      <SiteNav query={f.q} onQuery={(v) => set("q", v)} />
      <main className="container">
        <div style={{ margin: "24px 0 8px" }}>
          <h1 style={{ margin: 0, fontSize: 30, fontWeight: 900 }}>Browse Manga</h1>
          <p className="muted" style={{ margin: "4px 0 0" }}>Discover your next favorite series.</p>
        </div>

        <div className="filter-bar">
          <select value={f.status} onChange={(e) => set("status", e.target.value)}>
            {STATUSES.map((s) => <option key={s} value={s}>{s || "All status"}</option>)}
          </select>
          <select value={f.type} onChange={(e) => set("type", e.target.value)}>
            {TYPES.map((t) => <option key={t} value={t}>{t || "All types"}</option>)}
          </select>
          <select value={f.order} onChange={(e) => set("order", e.target.value)}>
            {ORDERS.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
          </select>
          <span style={{ flex: 1 }} />
          <div className="view-toggle">
            <button className={view === "list" ? "active" : ""} onClick={() => setView("list")} aria-label="List view"><Icon name="list" size={16} /></button>
            <button className={view === "grid" ? "active" : ""} onClick={() => setView("grid")} aria-label="Grid view"><Icon name="collection" size={16} /></button>
          </div>
        </div>

        {genres.length > 0 && (
          <div className="genre-chips">
            <span className={`chip${!f.genre ? " active" : ""}`} onClick={() => set("genre", "")}>All genres</span>
            {genres.map((g) => (
              <span key={g.id} className={`chip${f.genre === g.slug ? " active" : ""}`} onClick={() => set("genre", g.slug)}>
                {g.name} ({g.count})
              </span>
            ))}
          </div>
        )}

        <p className="faint" style={{ marginBottom: 14 }}>{total} manga found</p>

        {loading ? (
          <MangaGrid items={[]} loading empty="" />
        ) : items.length === 0 ? (
          <div className="center-state">No titles match these filters.</div>
        ) : view === "grid" ? (
          <MangaGrid items={items} />
        ) : (
          <div className="list-rows">{items.map((m) => <ListRow key={m.id} m={m} />)}</div>
        )}

        {pageCount > 1 && (
          <div className="pagination">
            <button disabled={page === 0} onClick={() => setPage((p) => p - 1)}>‹</button>
            {page > 2 && <button onClick={() => setPage(0)}>1</button>}
            {page > 3 && <span className="faint">…</span>}
            {pages.map((p) => (
              <button key={p} className={p === page ? "active" : ""} onClick={() => setPage(p)}>{p + 1}</button>
            ))}
            {page < pageCount - 4 && <span className="faint">…</span>}
            {page < pageCount - 3 && <button onClick={() => setPage(pageCount - 1)}>{pageCount}</button>}
            <button disabled={page >= pageCount - 1} onClick={() => setPage((p) => p + 1)}>›</button>
          </div>
        )}
      </main>
      <Footer />
    </>
  );
}
