"use client";

import { useEffect, useState, useRef, useCallback } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Avatar from "../../components/Avatar";
import { useAuth } from "../../lib/auth";
import { fetchConversations, fetchThread, sendMessage } from "../../lib/api";

export default function MessagesPage() {
  const { user, loading } = useAuth();
  const [convs, setConvs] = useState([]);
  const [active, setActive] = useState(null);
  const [thread, setThread] = useState([]);
  const [text, setText] = useState("");
  const bodyRef = useRef(null);

  useEffect(() => {
    if (!user) return;
    fetchConversations().then((d) => setConvs(d.data || [])).catch(() => {});
    const p = new URLSearchParams(window.location.search);
    const to = p.get("to");
    if (to) setActive({ id: to, username: "" });
  }, [user]);

  const loadThread = useCallback((peerId) => {
    fetchThread(peerId).then((d) => {
      setThread(d.data || []);
      setTimeout(() => bodyRef.current?.scrollTo(0, bodyRef.current.scrollHeight), 50);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    if (active?.id) loadThread(active.id);
  }, [active, loadThread]);

  if (!loading && !user) {
    return <><SiteNav /><div className="center-state"><p>Sign in to view messages.</p><Link href="/login" className="btn btn-primary">Sign in</Link></div></>;
  }

  const send = async () => {
    if (!text.trim() || !active?.id) return;
    await sendMessage(active.id, text.trim());
    setText("");
    loadThread(active.id);
    fetchConversations().then((d) => setConvs(d.data || []));
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>Messages</h2>
        </div>
        <div className="msg-layout">
          <div className="conv-list">
            {convs.length === 0 ? (
              <p className="faint">No conversations yet. Open a user&apos;s profile to message them.</p>
            ) : (
              convs.map((c) => (
                <div
                  key={c.peer.id}
                  className={`conv${active?.id === c.peer.id ? " active" : ""}`}
                  onClick={() => setActive(c.peer)}
                >
                  <Avatar user={c.peer} />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 700 }}>@{c.peer.username}</div>
                    <div className="faint" style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>
                      {c.outgoing ? "You: " : ""}{c.last_message}
                    </div>
                  </div>
                  {c.unread > 0 && <span className="notif-badge">{c.unread}</span>}
                </div>
              ))
            )}
          </div>

          <div className="thread">
            {!active ? (
              <div className="center-state">Select a conversation.</div>
            ) : (
              <>
                <div className="thread-body" ref={bodyRef}>
                  {thread.length === 0 ? (
                    <div className="faint" style={{ textAlign: "center" }}>No messages yet — say hi 👋</div>
                  ) : (
                    thread.map((m) => (
                      <div key={m.id} className={`bubble ${m.outgoing ? "out" : "in"}`}>
                        {m.body}
                      </div>
                    ))
                  )}
                </div>
                <div className="thread-input">
                  <input
                    className="input"
                    placeholder="Type a message…"
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && send()}
                  />
                  <button className="btn btn-primary" onClick={send}>Send</button>
                </div>
              </>
            )}
          </div>
        </div>
      </main>
    </>
  );
}
