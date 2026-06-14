"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchNotifications, fetchRandom, getAdult, setAdult } from "../lib/api";

export default function SiteNav({ query, onQuery }) {
  const { user, logout } = useAuth();
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const [adult, setAdultState] = useState(false);

  useEffect(() => {
    setAdultState(getAdult());
  }, []);

  const toggleAdult = () => {
    const next = !adult;
    setAdult(next);
    setAdultState(next);
    // Re-fetch lists with the new preference.
    window.location.reload();
  };

  useEffect(() => {
    let active = true;
    if (user) {
      fetchNotifications()
        .then((d) => active && setUnread(d.unread || 0))
        .catch(() => {});
    } else {
      setUnread(0);
    }
    return () => {
      active = false;
    };
  }, [user]);

  const goRandom = async (e) => {
    e.preventDefault();
    try {
      const { data } = await fetchRandom();
      window.location.href = `/manga/?slug=${encodeURIComponent(data.slug)}`;
    } catch {
      /* no-op */
    }
  };

  return (
    <nav className="nav">
      <div className="container nav-inner">
        <Link href="/home" className="brand">
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
        <button className="nav-burger" onClick={() => setOpen((o) => !o)} aria-label="Menu">
          ☰
        </button>
        <div className={`nav-links ${open ? "open" : ""}`}>
          <Link href="/home">Home</Link>
          <Link href="/browse">Browse</Link>
          <Link href="/recent">Recent</Link>
          <Link href="/community">Community</Link>
          <Link href="/leaderboard">Ranking</Link>
          <a href="#" onClick={goRandom}>Random</a>
          <span className="adult-toggle" onClick={toggleAdult} title="Show 18+ titles">
            <span className={`switch${adult ? " on" : ""}`} />
            18+
          </span>
          {user ? (
            <>
              <Link href="/library">Library</Link>
              <Link href="/messages">Messages</Link>
              <Link href="/notifications" className="notif-link">
                Alerts{unread > 0 && <span className="notif-badge">{unread}</span>}
              </Link>
              <Link href="/profile" className="nav-user">
                @{user.username}
              </Link>
              <a href="#" onClick={(e) => { e.preventDefault(); logout(); window.location.href = "/home"; }}>
                Logout
              </a>
            </>
          ) : (
            <Link href="/login" className="btn btn-primary nav-login">
              Sign in
            </Link>
          )}
        </div>
      </div>
    </nav>
  );
}
