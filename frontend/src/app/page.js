"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchPopular, proxyImage } from "../lib/api";

const FEATURES = [
  ["⚡", "Auto engine", "MangaKatana + ManhwaBuddy import on autopilot — new chapters appear with zero clicks."],
  ["📚", "Your library", "Bookmark with statuses, custom collections, reading history and new-chapter alerts."],
  ["💬", "Community", "Reviews, threaded comments with emoji & spoilers, a global wall, DMs and a leaderboard."],
  ["📱", "Cascade reader", "A buttery, mobile-first webtoon reader that streams artwork — never stored, never hotlinked."],
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
          A neon, dark-mode manga &amp; webtoon universe. Read thousands of auto-synced titles, build
          your library, and join the community — built mobile-first.
        </p>
        <div className="landing-actions">
          <Link href="/home" className="btn btn-primary">Enter XCOMIX ›</Link>
          {!user && <Link href="/register" className="btn btn-ghost">Create account</Link>}
          <Link href="/browse" className="btn btn-ghost">Browse catalog</Link>
        </div>
        <div className="landing-features">
          {FEATURES.map(([icon, h, p]) => (
            <div key={h} className="landing-feature">
              <div style={{ fontSize: 26, marginBottom: 8 }}>{icon}</div>
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
