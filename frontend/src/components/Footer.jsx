"use client";

import Link from "next/link";
import AdSlot from "./AdSlot";
import Logo from "./Logo";

export default function Footer() {
  return (
    <footer className="site-footer">
      <AdSlot slot="footer" className="ad-slot-footer" />
      <div className="foot-brand"><Logo size={26} /></div>
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
