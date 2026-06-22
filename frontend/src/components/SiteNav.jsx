"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "../lib/auth";
import { fetchNotifications, fetchRandom, getAdult, setAdult } from "../lib/api";
import Icon from "./Icon";
import Logo from "./Logo";

export default function SiteNav({ query, onQuery }) {
  const { user, loading: authLoading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [unread, setUnread] = useState(0);
  const [open, setOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [adult, setAdultState] = useState(false);
  // Local search text for pages that don't drive a live filter (onQuery absent).
  const [term, setTerm] = useState("");
  const searchInputRef = useRef(null);

  useEffect(() => {
    setAdultState(getAdult());
  }, []);

  // Focus the field the moment the search bar opens; close it on Escape.
  useEffect(() => {
    if (!searchOpen) return undefined;
    const t = setTimeout(() => searchInputRef.current?.focus(), 20);
    const onKey = (e) => e.key === "Escape" && setSearchOpen(false);
    document.addEventListener("keydown", onKey);
    return () => {
      clearTimeout(t);
      document.removeEventListener("keydown", onKey);
    };
  }, [searchOpen]);

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
    setSearchOpen(false);
    if (!q) return;
    router.push(`/browse/?q=${encodeURIComponent(q)}`);
  };

  const isActive = (href) => pathname === href || pathname?.startsWith(`${href}/`);

  return (
    <>
    <nav className="nav">
      <div className="container nav-inner">
        <Link href="/home" className="brand" aria-label="XCOMIX home">
          <Logo size={30} />
        </Link>

        <span className="nav-spacer" />

        <button
          className={`nav-icon-btn nav-search-btn${searchOpen ? " active" : ""}`}
          onClick={() => setSearchOpen((s) => !s)}
          aria-label="Search"
          aria-expanded={searchOpen}
        >
          <Icon name={searchOpen ? "close" : "search"} size={20} />
        </button>

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
          ) : authLoading ? (
            // Auth not resolved yet — don't flash a "Sign in" button at a user
            // who is actually logged in. Show a tiny placeholder instead.
            <span className="nav-auth-pending" aria-hidden="true" />
          ) : (
            <Link href="/login" className="btn btn-primary nav-login">
              Sign in
            </Link>
          )}
        </div>
      </div>

      <div className={`nav-search-panel${searchOpen ? " open" : ""}`}>
        <div className="container">
          <form className="nav-search" onSubmit={onSubmit} role="search">
            <Icon name="search" size={18} className="nav-search-icon" />
            <input
              ref={searchInputRef}
              type="search"
              value={value}
              onChange={(e) => onChange(e.target.value)}
              placeholder="Search manga, manhwa, manhua…"
              aria-label="Search manga"
            />
            {value && (
              <button
                type="button"
                className="nav-search-clear"
                aria-label="Clear search"
                onClick={() => onChange("")}
              >
                <Icon name="close" size={16} />
              </button>
            )}
          </form>
        </div>
      </div>
    </nav>

    {/* App-style bottom tab bar (mobile only). */}
    <nav className="bottom-nav" aria-label="Primary">
      <Link href="/home" className={`bn-item${isActive("/home") ? " active" : ""}`}>
        <Icon name="home" size={21} /><span>Home</span>
      </Link>
      <Link href="/browse" className={`bn-item${isActive("/browse") ? " active" : ""}`}>
        <Icon name="book" size={21} /><span>Browse</span>
      </Link>
      <button
        type="button"
        className={`bn-item${searchOpen ? " active" : ""}`}
        onClick={() => setSearchOpen((s) => !s)}
        aria-label="Search"
      >
        <Icon name="search" size={21} /><span>Search</span>
      </button>
      <Link
        href={user ? "/library" : "/login"}
        className={`bn-item${isActive("/library") ? " active" : ""}`}
      >
        <Icon name="bookmark" size={21} /><span>Library</span>
      </Link>
      <Link
        href={user ? "/profile" : "/login"}
        className={`bn-item${isActive(user ? "/profile" : "/login") ? " active" : ""}`}
      >
        <Icon name="user" size={21} /><span>{user ? "You" : "Sign in"}</span>
      </Link>
    </nav>
    </>
  );
}
