"use client";

import Link from "next/link";

export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="mascot">🐕</div>
      <p className="disclaimer">
        © {new Date().getFullYear()} XCOMIX does not store any files on it&apos;s servers, it only
        links to the media which is hosted on 3rd party services.
      </p>
      <div className="foot-links">
        <Link href="/home">Home</Link>
        <Link href="/browse">Browse</Link>
        <Link href="/recent">Recent</Link>
        <Link href="/community">Community</Link>
        <Link href="/leaderboard">Ranking</Link>
      </div>
    </footer>
  );
}
