"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchPopular, fetchMangaList, fetchGenres, proxyImage } from "../lib/api";
import Icon from "../components/Icon";
import Logo from "../components/Logo";

const TYPES = [
  ["book", "Manga", "Classic Japanese comics — action, shounen, seinen, romance and slice-of-life."],
  ["globe", "Manhwa", "Full-color Korean webtoons built for scrolling: cultivation, regression and revenge epics."],
  ["collection", "Manhua", "Chinese comics and donghua-style adventures, from martial arts to modern fantasy."],
];

const PERKS = [
  "100% Free",
  "No Intrusive Ads",
  "HD Quality",
  "Daily Updates",
  "Mobile Friendly",
  "PWA Support",
];

function Stat({ n, label }) {
  return (
    <div className="lp-stat">
      <div className="lp-stat-n">{n}</div>
      <div className="lp-stat-l">{label}</div>
    </div>
  );
}

export default function Landing() {
  const { user } = useAuth();
  const [covers, setCovers] = useState([]);
  const [stats, setStats] = useState({ titles: null, genres: null });

  useEffect(() => {
    fetchPopular(15).then((d) => setCovers((d.data || []).slice(0, 15))).catch(() => {});
    fetchMangaList({ limit: 1 }).then((d) => setStats((s) => ({ ...s, titles: d.total }))).catch(() => {});
    fetchGenres().then((d) => setStats((s) => ({ ...s, genres: (d.data || []).length }))).catch(() => {});
  }, []);

  const fmt = (n) => (n == null ? "—" : Number(n).toLocaleString());

  return (
    <main className="lp">
      {/* Hero */}
      <section className="lp-hero">
        <div className="landing-poster-wall" aria-hidden="true">
          {covers.map((m) => (
            // eslint-disable-next-line @next/next/no-img-element
            <img key={m.id} src={proxyImage(m.cover_url)} alt="" />
          ))}
        </div>
        <div className="lp-hero-veil" />
        <div className="lp-hero-inner">
          <div className="lp-brand"><Logo size={40} /></div>
          <h1 className="lp-title">
            Read <span className="x">Manga</span>, <span className="x">Manhwa</span> &amp; <span className="x">Manhua</span> Online
          </h1>
          <p className="lp-sub">
            Follow your favorite series, track new chapters, and dive into thousands of titles —
            free, fast, and without interruptions.
          </p>
          <div className="lp-actions">
            <Link href="/home" className="btn btn-primary btn-lg">Start Reading</Link>
            <Link href="/browse" className="btn btn-ghost btn-lg">Browse library</Link>
            {!user && <Link href="/register" className="btn btn-ghost btn-lg">Create account</Link>}
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="lp-stats">
        <Stat n={fmt(stats.titles)} label="Titles" />
        <Stat n={fmt(stats.genres)} label="Genres" />
        <Stat n="Daily" label="Updates" />
      </section>

      {/* Types */}
      <section className="lp-section">
        <div className="lp-types">
          {TYPES.map(([icon, h, p]) => (
            <div key={h} className="lp-type">
              <div className="lp-type-ico"><Icon name={icon} size={26} /></div>
              <h3>{h}</h3>
              <p>{p}</p>
            </div>
          ))}
        </div>
      </section>

      {/* What / Why */}
      <section className="lp-section lp-prose">
        <h2>What is XCOMIX?</h2>
        <p>
          XCOMIX is a modern manga, manhwa and manhua reading platform designed to bring fans closer
          to their favorite stories. Reading should be simple, fast and enjoyable — so we give you
          thousands of titles across every genre, reading-progress tracking, and new series tailored
          to your taste.
        </p>
        <h2>Why Choose XCOMIX?</h2>
        <p>
          Full control over your experience: advanced filters, a personalized library, and
          lightning-fast performance. Save the stories you love, explore hidden gems, and stay
          up to date with the latest chapters the moment they drop.
        </p>
        <div className="lp-perks">
          {PERKS.map((p) => (
            <div key={p} className="lp-perk"><Icon name="check" size={16} /> {p}</div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="lp-cta">
        <h2>Ready to start reading?</h2>
        <p>Join thousands of readers already enjoying their favorite manga.</p>
        <Link href="/home" className="btn btn-primary btn-lg">Enter XCOMIX</Link>
      </section>

      <footer className="lp-foot">
        <Logo size={26} />
        <p className="faint">
          © {new Date().getFullYear()} XCOMIX does not store any files on its servers, it only links
          to media hosted on 3rd-party services.
        </p>
      </footer>
    </main>
  );
}
