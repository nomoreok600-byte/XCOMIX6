"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Avatar from "../../components/Avatar";
import { fetchUsers } from "../../lib/api";

export default function CommunityPage() {
  const [users, setUsers] = useState([]);
  const [q, setQ] = useState("");

  useEffect(() => {
    const t = setTimeout(() => {
      fetchUsers(q).then((d) => setUsers(d.data || [])).catch(() => {});
    }, 250);
    return () => clearTimeout(t);
  }, [q]);

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>Community</h2>
          <Link href="/leaderboard" className="faint" style={{ marginLeft: "auto" }}>Leaderboard →</Link>
        </div>
        <div className="filter-bar">
          <input
            style={{ flex: 1 }}
            placeholder="Search users…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </div>
        {users.length === 0 ? (
          <div className="center-state">No users found.</div>
        ) : (
          users.map((u) => (
            <Link key={u.id} href={`/u/?username=${encodeURIComponent(u.username)}`} className="lb-row" style={{ textDecoration: "none" }}>
              <Avatar user={u} />
              <div>
                <div style={{ fontWeight: 800 }}>@{u.username}</div>
                {u.bio && <div className="faint">{u.bio.slice(0, 60)}</div>}
              </div>
              <span className="faint" style={{ marginLeft: "auto" }}>{u.followers} followers · {u.comments} comments</span>
            </Link>
          ))
        )}
      </main>
      <footer className="footer"><div className="container">XCOMIX · community</div></footer>
    </>
  );
}
