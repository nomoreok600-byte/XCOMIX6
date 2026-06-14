"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchPopular, proxyImage } from "../lib/api";
import Icon from "../components/Icon";

const FEATURES = [
  ["book", "Manga", "Classic Japanese series — action, shonen, seinen, romance and slice-of-life, updated daily."],
  ["globe", "Manhwa", "Full-color Korean webtoons built for scrolling: cultivation, regression and revenge epics."],
  ["collection", "Manhua", "Chinese comics and donghua-style adventures, from martial arts to modern fantasy."],
  ["moon", "Read your way", "A smooth dark-mode reader with fit modes, keyboard navigation and a bookmarkable library."],
];

export default function Landing() {
  const { user } = useAuth();
  const [covers, setCovers] = useState([]);

  useEffect(() => {
    fetchPopular(12).then((d) => setCovers((d.data || []).slice(0, 12))).catch(() => {});
  }, []);

  return (
    <main className="landing">
      <div className="landing-poster-wall" aria-hidden="true">
        {covers.map((m) => (
          // eslint-disable-next-line @next/next/no-img-element
          <img key={m.id} src={proxyImage(m.cover_url)} alt="" />
        ))}
      </div>
      <div className="landing-inner">
        <h1>
          <span className="x">X</span>COMIX
        </h1>
        <p className="tagline">
          Read manga, manhwa and manhua online — free. Thousands of titles across every genre,
          updated with the latest chapters and built for a clean, mobile-first reading experience.
        </p>
        <div className="landing-actions">
          <Link href="/home" className="btn btn-primary">Start reading ›</Link>
          {!user && <Link href="/register" className="btn btn-ghost">Create account</Link>}
          <Link href="/browse" className="btn btn-ghost">Browse library</Link>
        </div>
        <div className="landing-features">
          {FEATURES.map(([icon, h, p]) => (
            <div key={h} className="landing-feature">
              <div className="landing-feature-ico"><Icon name={icon} size={26} /></div>
              <h3>{h}</h3>
              <p>{p}</p>
            </div>
          ))}
        </div>
        <p className="faint" style={{ marginTop: 40, maxWidth: 560 }}>
          XCOMIX does not store any files on it&apos;s servers, it only links to the media which is
          hosted on 3rd party services.
        </p>
      </div>
    </main>
  );
}
