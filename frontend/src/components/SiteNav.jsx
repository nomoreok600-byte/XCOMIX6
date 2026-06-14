"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchNotifications, fetchRandom, getAdult, setAdult } from "../lib/api";
import Icon from "./Icon";
import Logo from "./Logo";

export default function SiteNav({ query, onQuery }) {
  const { user, logout } = useAuth();
  const router = useRouter();
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const [adult, setAdultState] = useState(false);
  // Local search text for pages that don't drive a live filter (onQuery absent).
  const [term, setTerm] = useState("");

  useEffect(() => {
    setAdultState(getAdult());
  }, []);

  const toggleAdult = () => {
    const next = !adult;
    setAdult(next);
    setAdultState(next);
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

  // Live filter when the page provides onQuery; otherwise navigate to /browse.
  const value = onQuery ? query || "" : term;
  const onChange = (v) => (onQuery ? onQuery(v) : setTerm(v));
  // Enter always runs a full catalog search on /browse (works from any page).
  // On pages with a live filter (onQuery), typing still filters in place.
  const onSubmit = (e) => {
    e.preventDefault();
    const q = (value || "").trim();
    if (!q) return;
    router.push(`/browse/?q=${encodeURIComponent(q)}`);
  };

  return (
    <nav className="nav">
      <div className="container nav-inner">
        <Link href="/home" className="brand" aria-label="XCOMIX home">
          <Logo size={30} />
        </Link>

        <form className="nav-search" onSubmit={onSubmit} role="search">
          <Icon name="search" size={18} className="nav-search-icon" />
          <input
            type="search"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder="Search manga, manhwa, manhua…"
            aria-label="Search manga"
          />
        </form>

        <button className="nav-burger" onClick={() => setOpen((o) => !o)} aria-label="Menu">
          <Icon name={open ? "close" : "menu"} size={22} />
        </button>

        <div className={`nav-links ${open ? "open" : ""}`}>
          <Link href="/home"><Icon name="home" size={16} /> Home</Link>
          <Link href="/browse"><Icon name="book" size={16} /> Browse</Link>
          <Link href="/recent"><Icon name="bolt" size={16} /> Recent</Link>
          <Link href="/community"><Icon name="users" size={16} /> Community</Link>
          <Link href="/leaderboard"><Icon name="trophy" size={16} /> Ranking</Link>
          <a href="#" onClick={goRandom}><Icon name="globe" size={16} /> Random</a>
          <span className="adult-toggle" onClick={toggleAdult} title="Show 18+ titles">
            <span className={`switch${adult ? " on" : ""}`} />
            18+
          </span>
          {user ? (
            <>
              <Link href="/library"><Icon name="bookmark" size={16} /> Library</Link>
              <Link href="/messages"><Icon name="comment" size={16} /> Messages</Link>
              <Link href="/notifications" className="notif-link">
                <Icon name="bell" size={16} /> Alerts
                {unread > 0 && <span className="notif-badge">{unread}</span>}
              </Link>
              <Link href="/profile" className="nav-user">
                <Icon name="user" size={16} /> @{user.username}
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
