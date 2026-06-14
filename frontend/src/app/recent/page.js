"use client";

import { useEffect, useState } from "react";
import SiteNav from "../../components/SiteNav";
import MangaGrid from "../../components/MangaGrid";
import { fetchRecent, fetchPopular } from "../../lib/api";

export default function RecentPage() {
  const [recent, setRecent] = useState([]);
  const [popular, setPopular] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([fetchRecent(48), fetchPopular(12)])
      .then(([r, p]) => {
        setRecent(r.data || []);
        setPopular(p.data || []);
      })
      .finally(() => setLoading(false));
  }, []);

  return (
    <>
      <SiteNav />
      <main className="container">
        <section className="section" style={{ marginTop: 24 }}>
          <div className="section-head">
            <span className="bar" />
            <h2>⚡ Recently Updated</h2>
          </div>
          <MangaGrid items={recent} loading={loading} empty="No recent updates yet." />
        </section>

        <section className="section">
          <div className="section-head">
            <span className="bar" />
            <h2>🔥 Popular Right Now</h2>
          </div>
          <MangaGrid items={popular} loading={loading} />
        </section>
      </main>
      <footer className="footer"><div className="container">XCOMIX · recent</div></footer>
    </>
  );
}
