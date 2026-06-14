"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import { useAuth } from "../../lib/auth";
import { fetchNotifications, markNotificationsRead } from "../../lib/api";

export default function NotificationsPage() {
  const { user, loading } = useAuth();
  const [items, setItems] = useState([]);

  useEffect(() => {
    if (!user) return;
    fetchNotifications().then((d) => setItems(d.data || [])).catch(() => {});
  }, [user]);

  if (!loading && !user) {
    return <><SiteNav /><div className="center-state"><p>Sign in to see notifications.</p><Link href="/login" className="btn btn-primary">Sign in</Link></div></>;
  }

  const markAll = async () => {
    await markNotificationsRead();
    setItems((prev) => prev.map((n) => ({ ...n, is_read: true })));
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>Notifications</h2>
          <button className="tab" style={{ marginLeft: "auto" }} onClick={markAll}>Mark all read</button>
        </div>
        {items.length === 0 ? (
          <div className="center-state">No notifications yet. Bookmark a manga to get new-chapter alerts.</div>
        ) : (
          items.map((n) => {
            const inner = (
              <>
                {!n.is_read && <span className="dot" />}
                <div>
                  <div style={{ fontWeight: n.is_read ? 500 : 800 }}>{n.message}</div>
                  <div className="faint">{new Date(n.created_at).toLocaleString()}</div>
                </div>
              </>
            );
            return n.manga_slug ? (
              <Link key={n.id} href={`/manga/?slug=${encodeURIComponent(n.manga_slug)}`} className={`notif${n.is_read ? "" : " unread"}`} style={{ textDecoration: "none" }}>
                {inner}
              </Link>
            ) : (
              <div key={n.id} className={`notif${n.is_read ? "" : " unread"}`}>{inner}</div>
            );
          })
        )}
      </main>
      <footer className="footer"><div className="container">XCOMIX · notifications</div></footer>
    </>
  );
}
