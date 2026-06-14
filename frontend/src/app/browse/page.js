"use client";

import { useEffect, useState, useCallback } from "react";
import SiteNav from "../../components/SiteNav";
import MangaGrid from "../../components/MangaGrid";
import { browse, fetchGenres } from "../../lib/api";

const STATUSES = ["", "Ongoing", "Completed"];
const TYPES = ["", "Manga", "Manhwa", "Manhua"];
const ORDERS = [
  ["updated", "Recently updated"],
  ["popular", "Most popular"],
  ["newest", "Newest"],
  ["rating", "Top rated"],
  ["title", "A–Z"],
];

export default function BrowsePage() {
  const [genres, setGenres] = useState([]);
  const [items, setItems] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [f, setF] = useState({ genre: "", status: "", type: "", order: "updated", q: "" });
  const [offset, setOffset] = useState(0);

  useEffect(() => {
    // Hydrate from query string (e.g. /browse?status=Completed).
    const p = new URLSearchParams(window.location.search);
    setF((prev) => ({
      ...prev,
      genre: p.get("genre") || "",
      status: p.get("status") || "",
      type: p.get("type") || "",
    }));
    fetchGenres().then((d) => setGenres(d.data || [])).catch(() => {});
  }, []);

  const load = useCallback(
    (reset) => {
      setLoading(true);
      const off = reset ? 0 : offset;
      browse({ ...f, limit: 24, offset: off })
        .then((d) => {
          setItems((prev) => (reset ? d.data : [...prev, ...d.data]));
          setTotal(d.total);
          setOffset(off + (d.data?.length || 0));
        })
        .finally(() => setLoading(false));
    },
    [f, offset]
  );

  // Reload whenever a filter changes.
  useEffect(() => {
    setOffset(0);
    setLoading(true);
    browse({ ...f, limit: 24, offset: 0 })
      .then((d) => {
        setItems(d.data || []);
        setTotal(d.total);
        setOffset(d.data?.length || 0);
      })
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [f.genre, f.status, f.type, f.order, f.q]);

  const set = (k, v) => setF((prev) => ({ ...prev, [k]: v }));

  return (
    <>
      <SiteNav query={f.q} onQuery={(v) => set("q", v)} />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>Browse</h2>
          <span className="faint" style={{ marginLeft: "auto" }}>{total} titles</span>
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
        </div>

        {genres.length > 0 && (
          <div className="genre-chips">
            <span
              className={`chip${!f.genre ? " active" : ""}`}
              onClick={() => set("genre", "")}
            >
              All genres
            </span>
            {genres.map((g) => (
              <span
                key={g.id}
                className={`chip${f.genre === g.slug ? " active" : ""}`}
                onClick={() => set("genre", g.slug)}
              >
                {g.name} ({g.count})
              </span>
            ))}
          </div>
        )}

        <MangaGrid items={items} loading={loading && items.length === 0} empty="No titles match these filters." />

        {items.length < total && (
          <div style={{ textAlign: "center", margin: "28px 0" }}>
            <button className="btn btn-ghost" disabled={loading} onClick={() => load(false)}>
              {loading ? "Loading…" : "Load more"}
            </button>
          </div>
        )}
      </main>
      <footer className="footer"><div className="container">XCOMIX · browse</div></footer>
    </>
  );
}
