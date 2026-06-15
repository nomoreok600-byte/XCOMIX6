"use client";

import { useEffect, useState } from "react";
import SiteNav from "../../components/SiteNav";
import MangaGrid from "../../components/MangaGrid";
import Footer from "../../components/Footer";
import Icon from "../../components/Icon";
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
        <div className="page-head">
          <h1><Icon name="bolt" size={26} className="head-ico" /> Recently Updated</h1>
          <p className="muted">The latest manga, manhwa and manhua to get new chapters.</p>
        </div>

        <section className="section">
          <MangaGrid items={recent} loading={loading} showUpdated empty="No recent updates yet." />
        </section>

        <section className="section">
          <div className="section-head">
            <span className="bar" />
            <h2><Icon name="flame" size={20} className="head-ico" /> Popular Right Now</h2>
          </div>
          <MangaGrid items={popular} loading={loading} />
        </section>
      </main>
      <Footer />
    </>
  );
}
