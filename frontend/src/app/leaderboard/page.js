"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Avatar from "../../components/Avatar";
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
          <h2>🏆 Leaderboard</h2>
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
              <span className="faint" style={{ marginLeft: 12 }}>
                {u.comments}💬 {u.reviews}★ {u.followers}👥
              </span>
              <span className="lb-score">{u.score} pts</span>
            </div>
          ))
        )}
      </main>
      <footer className="footer"><div className="container">XCOMIX · leaderboard</div></footer>
    </>
  );
}
