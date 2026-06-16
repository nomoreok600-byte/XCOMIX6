"use client";

import { Suspense, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import SiteNav from "../../components/SiteNav";
import MangaGrid from "../../components/MangaGrid";
import Footer from "../../components/Footer";
import Stars from "../../components/Stars";
import Icon from "../../components/Icon";
import Select from "../../components/Select";
import { browse, fetchGenres, proxyImage, decodeEntities, getAdult, setAdult } from "../../lib/api";

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

function BrowseInner() {
  const sp = useSearchParams();
  const [genres, setGenres] = useState([]);
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [adultHidden, setAdultHidden] = useState(0);
  const [loading, setLoading] = useState(true);
  const [view, setView] = useState("list");
  const [page, setPage] = useState(0);
  const [genresOpen, setGenresOpen] = useState(false);
  const [f, setF] = useState({ genre: "", status: "", type: "", order: "updated", q: "" });
  const GENRE_PREVIEW = 14;

  // Seed filters from the URL and re-seed whenever it changes (so a fresh nav
  // search from anywhere — even while already on /browse — re-runs the search).
  useEffect(() => {
    setPage(0);
    setF((prev) => ({
      ...prev,
      genre: sp.get("genre") || "",
      status: sp.get("status") || "",
      type: sp.get("type") || "",
      order: sp.get("order") || "updated",
      q: sp.get("q") || "",
    }));
  }, [sp]);

  useEffect(() => {
    fetchGenres().then((d) => setGenres(d.data || [])).catch(() => {});
  }, []);

  const load = useCallback(() => {
    setLoading(true);
    browse({ ...f, limit: PER_PAGE, offset: page * PER_PAGE })
      .then((d) => {
        setItems(d.data || []);
        setTotal(d.total);
        setAdultHidden(d.adult_hidden || 0);
      })
      .finally(() => setLoading(false));
  }, [f, page]);

  useEffect(() => {
    load();
  }, [load]);

  const enableAdult = () => {
    setAdult(true);
    window.location.reload();
  };

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
          <h1 style={{ margin: 0, fontSize: 30, fontWeight: 900 }}>
            {f.q ? `Search: “${f.q}”` : "Browse Manga"}
          </h1>
          <p className="muted" style={{ margin: "4px 0 0" }}>
            {f.q ? `${total} result${total === 1 ? "" : "s"} found` : "Discover your next favorite series."}
          </p>
        </div>

        <div className="filter-bar">
          <Select
            className="filter-select"
            ariaLabel="Status"
            value={f.status}
            onChange={(v) => set("status", v)}
            options={STATUSES.map((s) => ({ value: s, label: s || "All status" }))}
          />
          <Select
            className="filter-select"
            ariaLabel="Type"
            value={f.type}
            onChange={(v) => set("type", v)}
            options={TYPES.map((t) => ({ value: t, label: t || "All types" }))}
          />
          <Select
            className="filter-select"
            ariaLabel="Sort order"
            value={f.order}
            onChange={(v) => set("order", v)}
            options={ORDERS.map(([v, l]) => ({ value: v, label: l }))}
          />
          <span style={{ flex: 1 }} />
          <div className="view-toggle">
            <button className={view === "list" ? "active" : ""} onClick={() => setView("list")} aria-label="List view"><Icon name="list" size={16} /></button>
            <button className={view === "grid" ? "active" : ""} onClick={() => setView("grid")} aria-label="Grid view"><Icon name="collection" size={16} /></button>
          </div>
        </div>

        {genres.length > 0 && (
          <div className={`genre-chips ${genresOpen ? "expanded" : "collapsed"}`}>
            <span className={`chip${!f.genre ? " active" : ""}`} onClick={() => set("genre", "")}>All genres</span>
            {(genresOpen ? genres : genres.slice(0, GENRE_PREVIEW)).map((g) => (
              <span key={g.id} className={`chip${f.genre === g.slug ? " active" : ""}`} onClick={() => set("genre", g.slug)}>
                {g.name} <span className="chip-count">{g.count}</span>
              </span>
            ))}
            {genres.length > GENRE_PREVIEW && (
              <button className="chip chip-more" onClick={() => setGenresOpen((o) => !o)}>
                {genresOpen ? "Show less" : `+${genres.length - GENRE_PREVIEW} more`}
                <Icon name={genresOpen ? "chevronUp" : "chevronDown"} size={13} />
              </button>
            )}
          </div>
        )}

        <p className="faint" style={{ marginBottom: 14 }}>{total} manga found</p>

        {adultHidden > 0 && (
          <div className="adult-hint">
            <Icon name="warning" size={16} />
            <span>{adultHidden} more 18+ {adultHidden === 1 ? "result is" : "results are"} hidden.</span>
            <button className="link-btn" onClick={enableAdult}>Enable 18+</button>
          </div>
        )}

        {loading ? (
          <MangaGrid items={[]} loading empty="" />
        ) : items.length === 0 ? (
          <div className="center-state">
            {f.q ? `No titles match “${f.q}”.` : "No titles match these filters."}
            {adultHidden > 0 && (
              <div style={{ marginTop: 12 }}>
                <button className="btn btn-primary" onClick={enableAdult}>Show {adultHidden} 18+ result{adultHidden === 1 ? "" : "s"}</button>
              </div>
            )}
          </div>
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

export default function BrowsePage() {
  return (
    <Suspense fallback={<><SiteNav /><div className="center-state">Loading…</div></>}>
      <BrowseInner />
    </Suspense>
  );
}
