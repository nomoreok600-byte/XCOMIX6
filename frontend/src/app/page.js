"use client";

import Link from "next/link";
import { useAuth } from "../lib/auth";

const FEATURES = [
  ["Auto engine", "MangaKatana + ManhwaBuddy import on autopilot — new chapters appear with zero clicks."],
  ["Your library", "Bookmark with statuses, custom folders, reading history and new-chapter alerts."],
  ["Community", "Reviews, threaded comments with emoji & spoilers, DMs, profiles and a leaderboard."],
  ["Cascade reader", "A buttery webtoon reader that streams artwork — nothing stored, never hotlinked."],
];

export default function Landing() {
  const { user } = useAuth();
  return (
    <main className="landing">
      <h1>
        <span className="x">X</span>COMIX
      </h1>
      <p className="tagline">
        A neon, dark-mode manga & webtoon universe. Read thousands of auto-synced titles, build your
        library, and join the community.
      </p>
      <div className="landing-actions">
        <Link href="/home" className="btn btn-primary">
          Enter XCOMIX ›
        </Link>
        {!user && (
          <Link href="/register" className="btn btn-ghost">
            Create account
          </Link>
        )}
        <Link href="/browse" className="btn btn-ghost">
          Browse catalog
        </Link>
      </div>
      <div className="landing-features">
        {FEATURES.map(([h, p]) => (
          <div key={h} className="landing-feature">
            <h3>{h}</h3>
            <p>{p}</p>
          </div>
        ))}
      </div>
      <footer className="footer" style={{ marginTop: 60 }}>
        XCOMIX · neon manga engine · streamed, never stored
      </footer>
    </main>
  );
}
