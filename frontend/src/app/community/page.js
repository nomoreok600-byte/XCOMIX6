"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Footer from "../../components/Footer";
import Avatar from "../../components/Avatar";
import { useAuth } from "../../lib/auth";
import {
  fetchFeed,
  postFeed,
  likeFeed,
  deleteFeed,
  fetchReplies,
  fetchUsers,
} from "../../lib/api";

function Post({ p, onChanged }) {
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const [replies, setReplies] = useState([]);
  const [reply, setReply] = useState("");

  const loadReplies = useCallback(() => {
    fetchReplies(p.id).then((d) => setReplies(d.data || [])).catch(() => {});
  }, [p.id]);

  const toggle = () => {
    const next = !open;
    setOpen(next);
    if (next) loadReplies();
  };
  const like = async () => { await likeFeed(p.id); onChanged(); };
  const sendReply = async () => {
    if (!reply.trim()) return;
    await postFeed({ parent_id: p.id, body: reply });
    setReply("");
    loadReplies();
    onChanged();
  };

  return (
    <div className="wall-post">
      <div className="wall-head">
        <Avatar user={p.user} />
        <Link href={`/u/?username=${encodeURIComponent(p.user.username)}`} className="name">@{p.user.username}</Link>
        <span className="when">{new Date(p.created_at).toLocaleString()}</span>
        {user && (user.id === p.user.id || user.role === "admin") && (
          <button className="link-btn" style={{ marginLeft: "auto" }} onClick={async () => { await deleteFeed(p.id); onChanged(); }}>Delete</button>
        )}
      </div>
      <div className="comment-body">{p.body}</div>
      {p.image_url && (
        // eslint-disable-next-line @next/next/no-img-element
        <img className="comment-img" src={p.image_url} alt="" />
      )}
      <div className="wall-actions">
        <button className={p.liked ? "liked" : ""} onClick={like} disabled={!user}>
          ♥ {p.likes}
        </button>
        <button onClick={toggle}>💬 {p.replies} {open ? "Hide" : "Reply"}</button>
      </div>
      {open && (
        <div style={{ marginTop: 10, marginLeft: 12, borderLeft: "1px solid var(--border)", paddingLeft: 12 }}>
          {replies.map((r) => (
            <div key={r.id} className="comment reply" style={{ marginLeft: 0 }}>
              <div className="comment-head">
                <Avatar user={r.user} />
                <span className="name">@{r.user.username}</span>
                <span className="when">{new Date(r.created_at).toLocaleString()}</span>
              </div>
              <div className="comment-body">{r.body}</div>
            </div>
          ))}
          {user && (
            <div className="row" style={{ marginTop: 8 }}>
              <input className="input" placeholder="Write a reply…" value={reply} onChange={(e) => setReply(e.target.value)} onKeyDown={(e) => e.key === "Enter" && sendReply()} />
              <button className="btn btn-primary" onClick={sendReply}>Reply</button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function CommunityPage() {
  const { user } = useAuth();
  const [tab, setTab] = useState("wall");
  const [sort, setSort] = useState("new");
  const [posts, setPosts] = useState([]);
  const [body, setBody] = useState("");
  const [users, setUsers] = useState([]);
  const [q, setQ] = useState("");

  const loadFeed = useCallback(() => {
    fetchFeed(sort).then((d) => setPosts(d.data || [])).catch(() => {});
  }, [sort]);

  useEffect(() => {
    if (tab === "wall") loadFeed();
  }, [tab, loadFeed]);

  useEffect(() => {
    if (tab !== "members") return;
    const t = setTimeout(() => fetchUsers(q).then((d) => setUsers(d.data || [])).catch(() => {}), 250);
    return () => clearTimeout(t);
  }, [tab, q]);

  const post = async () => {
    if (!body.trim()) return;
    await postFeed({ body });
    setBody("");
    loadFeed();
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>Community</h2>
          <Link href="/leaderboard" className="head-link">Leaderboard →</Link>
        </div>

        <div className="pill-tabs">
          <button className={`pill-tab${tab === "wall" ? " active" : ""}`} onClick={() => setTab("wall")}>The Wall</button>
          <button className={`pill-tab${tab === "members" ? " active" : ""}`} onClick={() => setTab("members")}>Members</button>
        </div>

        {tab === "wall" ? (
          <>
            {user ? (
              <div className="composer">
                <Avatar user={user} />
                <div className="grow">
                  <textarea rows={2} placeholder="Say something to the community…" value={body} onChange={(e) => setBody(e.target.value)} />
                  <div style={{ textAlign: "right" }}>
                    <button className="btn btn-primary" onClick={post}>Post</button>
                  </div>
                </div>
              </div>
            ) : (
              <p className="muted"><Link href="/login" style={{ color: "var(--crimson)" }}>Sign in</Link> to post on the wall.</p>
            )}

            <div className="pill-tabs">
              {[["new", "New"], ["top", "Top"], ["old", "Old"]].map(([v, l]) => (
                <button key={v} className={`pill-tab${sort === v ? " active" : ""}`} onClick={() => setSort(v)}>{l}</button>
              ))}
            </div>

            {posts.length === 0 ? (
              <div className="center-state">No posts yet — start the conversation!</div>
            ) : (
              posts.map((p) => <Post key={p.id} p={p} onChanged={loadFeed} />)
            )}
          </>
        ) : (
          <>
            <div className="filter-bar">
              <input style={{ flex: 1 }} placeholder="Search members…" value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            {users.length === 0 ? (
              <div className="center-state">No members found.</div>
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
          </>
        )}
      </main>
      <Footer />
    </>
  );
}
