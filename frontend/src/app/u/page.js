"use client";

import { Suspense, useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Footer from "../../components/Footer";
import Avatar from "../../components/Avatar";
import MangaCard from "../../components/MangaCard";
import Icon from "../../components/Icon";
import { useAuth } from "../../lib/auth";
import { fetchProfile, follow, unfollow, proxyImage } from "../../lib/api";

export default function PublicProfilePage() {
  return (
    <Suspense fallback={<><SiteNav /><div className="center-state">Loading…</div></>}>
      <PublicProfile />
    </Suspense>
  );
}

function PublicProfile() {
  const { user } = useAuth();
  // Read the username reactively from the query string so navigating from one
  // profile to another (same route, query-only change) re-fetches the new user
  // instead of leaving the previous profile's data on screen.
  const params = useSearchParams();
  const username = params.get("username") || "";
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback((name) => {
    fetchProfile(name)
      .then((d) => {
        setData(d);
        setError(null);
      })
      .catch((e) => setError(e.message));
  }, []);
  useEffect(() => {
    // Clear stale data immediately on a profile switch so we never flash the
    // previous user's followers/library while the new request is in flight.
    setData(null);
    setError(null);
    if (username) load(username);
  }, [username, load]);

  if (error) return <><SiteNav /><div className="center-state">{error}</div></>;
  if (!data) return <><SiteNav /><div className="center-state">Loading…</div></>;

  const { profile, stats, library, is_self, is_following, profile_visible } = data;

  const toggleFollow = async () => {
    setBusy(true);
    try {
      if (is_following) await unfollow(profile.id);
      else await follow(profile.id);
      load(username);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div
          className="profile-banner"
          style={profile.banner_url ? { backgroundImage: `url(${proxyImage(profile.banner_url)})` } : undefined}
        />
        <div className="profile-head">
          <Avatar user={profile} size="lg" />
          <div style={{ flex: 1 }}>
            <h1 style={{ margin: 0 }}>@{profile.username}</h1>
            {profile.bio && <p className="muted" style={{ margin: "4px 0" }}>{profile.bio}</p>}
          </div>
          {user && !is_self && (
            <div className="row">
              <button className={`btn ${is_following ? "btn-ghost" : "btn-primary"}`} disabled={busy} onClick={toggleFollow}>
                {is_following ? <><Icon name="check" size={15} /> Following</> : <><Icon name="plus" size={15} /> Follow</>}
              </button>
              <Link href={`/messages/?to=${profile.id}`} className="btn btn-ghost">Message</Link>
            </div>
          )}
        </div>

        <div className="profile-stats">
          <div className="s"><b>{stats.followers}</b><span>Followers</span></div>
          <div className="s"><b>{stats.following}</b><span>Following</span></div>
          <div className="s"><b>{stats.library}</b><span>Library</span></div>
          <div className="s"><b>{stats.reviews}</b><span>Reviews</span></div>
          <div className="s"><b>{stats.comments}</b><span>Comments</span></div>
        </div>

        <div className="section">
          <div className="section-head">
            <span className="bar" />
            <h2>Library</h2>
          </div>
          {!profile_visible ? (
            <div className="center-state">This user&apos;s library is private.</div>
          ) : library.length === 0 ? (
            <div className="center-state">No bookmarks yet.</div>
          ) : (
            <div className="grid">
              {library.map((l) => <MangaCard key={l.manga.id} manga={l.manga} />)}
            </div>
          )}
        </div>
      </main>
      <Footer />
    </>
  );
}
