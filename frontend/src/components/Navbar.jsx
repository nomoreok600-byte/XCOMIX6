"use client";

import Link from "next/link";

export default function Navbar({ query, onQuery }) {
  return (
    <nav className="nav">
      <div className="container nav-inner">
        <Link href="/" className="brand">
          <span className="x">X</span>COMIX
        </Link>
        {onQuery ? (
          <div className="nav-search">
            <input
              type="search"
              value={query || ""}
              onChange={(e) => onQuery(e.target.value)}
              placeholder="Search the matrix for titles…"
              aria-label="Search manga"
            />
          </div>
        ) : (
          <div className="nav-search" />
        )}
        <div className="nav-links">
          <Link href="/">Home</Link>
          <a href="#latest">Latest</a>
          <a href="#hot">Hot</a>
        </div>
      </div>
    </nav>
  );
}
