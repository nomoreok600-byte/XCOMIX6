"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Footer from "../../components/Footer";
import Avatar from "../../components/Avatar";
import Icon from "../../components/Icon";
import { fetchLeaderboard } from "../../lib/api";

export default function LeaderboardPage() {
  const [rows, setRows] = useState([]);

  useEffect(() => {
    fetchLeaderboard().then((d) => setRows(d.data || [])).catch(() => {});
  }, []);

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2><Icon name="trophy" size={20} className="head-ico" /> Leaderboard</h2>
        </div>
        <p className="faint" style={{ marginBottom: 18 }}>
          Ranked by activity: comments ×2 · reviews ×3 · followers ×5 · library ×1.
        </p>
        {rows.length === 0 ? (
          <div className="center-state">No ranked users yet.</div>
        ) : (
          rows.map((u) => (
            <div key={u.id} className="lb-row">
              <span className={`lb-rank${u.rank <= 3 ? " top" : ""}`}>#{u.rank}</span>
              <Avatar user={u} />
              <Link href={`/u/?username=${encodeURIComponent(u.username)}`} style={{ fontWeight: 800 }}>
                @{u.username}
              </Link>
              <span className="faint lb-stats" style={{ marginLeft: 12 }}>
                <span><Icon name="comment" size={13} /> {u.comments}</span>
                <span><Icon name="star" size={13} /> {u.reviews}</span>
                <span><Icon name="users" size={13} /> {u.followers}</span>
              </span>
              <span className="lb-score">{u.score} pts</span>
            </div>
          ))
        )}
      </main>
      <Footer />
    </>
  );
}
