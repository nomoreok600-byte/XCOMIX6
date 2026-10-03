"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useAuth } from "../lib/auth";
import Avatar from "./Avatar";
import Icon from "./Icon";
import {
  fetchComments,
  postComment,
  deleteComment,
  reactComment,
} from "../lib/api";

// Real-icon reactions (no emoji). The stored value is a short key (≤16 chars,
// fits the comment_reactions.emoji column); legacy emoji rows still render via
// the fallback below so old reactions don't disappear.
const REACTIONS = [
  { key: "like", label: "Like", icon: "thumbsUp" },
  { key: "love", label: "Love", icon: "heart" },
  { key: "fire", label: "Fire", icon: "flame" },
  { key: "laugh", label: "Haha", icon: "laugh" },
  { key: "wow", label: "Wow", icon: "wow" },
  { key: "sad", label: "Sad", icon: "sad" },
  { key: "skull", label: "Dead", icon: "skull" },
];
const REACTION_MAP = Object.fromEntries(REACTIONS.map((r) => [r.key, r]));

function Spoiler({ children }) {
  const [show, setShow] = useState(false);
  return (
    <span className={`spoiler-mask${show ? " revealed" : ""}`} onClick={() => setShow(true)}>
      {show ? children : "spoiler — click to reveal"}
    </span>
  );
}

function CommentNode({ c, onReply, onChanged, depth = 0 }) {
  const { user } = useAuth();
  const [showPicker, setShowPicker] = useState(false);

  const react = async (key) => {
    await reactComment(c.id, key);
    setShowPicker(false);
    onChanged();
  };
  const remove = async () => {
    await deleteComment(c.id);
    onChanged();
  };

  const reactions = (c.reactions || []).filter((r) => r.count > 0);

  return (
    <div className={`comment${depth > 0 ? " reply" : ""}`}>
      <div className="comment-head">
        <Avatar user={c.user} />
        <Link href={`/u/?username=${encodeURIComponent(c.user.username)}`} className="name">
          @{c.user.username}
        </Link>
        <span className="when"><Icon name="clock" size={12} /> {new Date(c.created_at).toLocaleString()}</span>
      </div>
      <div className="comment-body">
        {c.is_spoiler ? <Spoiler>{c.body}</Spoiler> : c.body}
      </div>
      {c.image_url && (
        // eslint-disable-next-line @next/next/no-img-element
        <img className="comment-img" src={c.image_url} alt="attachment" />
      )}
      <div className="comment-actions">
        {reactions.map((r) => {
          const meta = REACTION_MAP[r.emoji];
          return (
            <button
              key={r.emoji}
              className={`react-pill${r.mine ? " mine" : ""}`}
              onClick={() => react(r.emoji)}
              disabled={!user}
              title={meta ? meta.label : r.emoji}
            >
              {meta ? <Icon name={meta.icon} size={14} /> : <span className="react-legacy">{r.emoji}</span>}
              <span className="react-count">{r.count}</span>
            </button>
          );
        })}
        {user && (
          <div className="comment-tools">
            <div className="react-wrap">
              <button
                className={`comment-tool${showPicker ? " active" : ""}`}
                onClick={() => setShowPicker((s) => !s)}
                aria-label="Add reaction"
              >
                <Icon name="heart" size={15} /> React
              </button>
              {showPicker && (
                <div className="reaction-picker" role="menu">
                  {REACTIONS.map((rx) => (
                    <button
                      key={rx.key}
                      className="reaction-opt"
                      title={rx.label}
                      aria-label={rx.label}
                      onClick={() => react(rx.key)}
                    >
                      <Icon name={rx.icon} size={18} />
                    </button>
                  ))}
                </div>
              )}
            </div>
            {depth === 0 && (
              <button className="comment-tool" onClick={() => onReply(c)}>
                <Icon name="comment" size={15} /> Reply
              </button>
            )}
            {(user.id === c.user.id || user.role === "admin") && (
              <button className="comment-tool danger" onClick={remove}>
                <Icon name="trash" size={15} /> Delete
              </button>
            )}
          </div>
        )}
      </div>
      {(c.replies || []).length > 0 && (
        <div className="comment-replies">
          {c.replies.map((r) => (
            <CommentNode key={r.id} c={r} onReply={onReply} onChanged={onChanged} depth={depth + 1} />
          ))}
        </div>
      )}
    </div>
  );
}

function reactionTotal(c) {
  return (c.reactions || []).reduce((n, r) => n + r.count, 0);
}

export default function Comments({ mangaId, chapterId }) {
  const { user, loading: authLoading } = useAuth();
  const [list, setList] = useState([]);
  const [body, setBody] = useState("");
  const [spoiler, setSpoiler] = useState(false);
  const [image, setImage] = useState("");
  const [replyTo, setReplyTo] = useState(null);
  const [busy, setBusy] = useState(false);
  const [sort, setSort] = useState("top");

  const load = useCallback(() => {
    fetchComments({ manga_id: mangaId, chapter_id: chapterId })
      .then((d) => setList(d.data || []))
      .catch(() => {});
  }, [mangaId, chapterId]);

  useEffect(() => {
    load();
  }, [load]);

  const sorted = [...list].sort((a, b) => {
    if (sort === "new") return new Date(b.created_at) - new Date(a.created_at);
    if (sort === "old") return new Date(a.created_at) - new Date(b.created_at);
    return reactionTotal(b) - reactionTotal(a) || new Date(b.created_at) - new Date(a.created_at);
  });

  const onFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => setImage(reader.result);
    reader.readAsDataURL(file);
  };

  const submit = async () => {
    if (!body.trim() && !image) return;
    setBusy(true);
    try {
      await postComment({
        manga_id: mangaId,
        chapter_id: chapterId,
        parent_id: replyTo?.id || null,
        body,
        is_spoiler: spoiler,
        image_url: image || null,
      });
      setBody("");
      setSpoiler(false);
      setImage("");
      setReplyTo(null);
      load();
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <div className="row" style={{ justifyContent: "space-between", marginBottom: 12 }}>
        <span className="muted" style={{ fontWeight: 700, display: "inline-flex", alignItems: "center", gap: 6 }}><Icon name="comment" size={15} /> {list.length} comments</span>
        <div className="pill-tabs" style={{ margin: 0 }}>
          {[["top", "Top"], ["new", "New"], ["old", "Old"]].map(([v, l]) => (
            <button key={v} className={`pill-tab${sort === v ? " active" : ""}`} onClick={() => setSort(v)}>{l}</button>
          ))}
        </div>
      </div>

      {user ? (
        <div className="composer">
          <Avatar user={user} />
          <div className="grow">
            {replyTo && (
              <div className="faint" style={{ marginBottom: 8 }}>
                Replying to @{replyTo.user.username}{" "}
                <button className="link-btn" onClick={() => setReplyTo(null)}>cancel</button>
              </div>
            )}
            <textarea rows={3} placeholder="Share your thoughts…" value={body} onChange={(e) => setBody(e.target.value)} />
            {image && (
              // eslint-disable-next-line @next/next/no-img-element
              <img className="comment-img" src={image} alt="preview" />
            )}
            <div className="comment-actions" style={{ marginTop: 8 }}>
              <label className="link-btn" style={{ cursor: "pointer", display: "inline-flex", alignItems: "center", gap: 6 }}>
                <Icon name="image" size={15} /> Image
                <input type="file" accept="image/*" hidden onChange={onFile} />
              </label>
              <label className="faint" style={{ display: "flex", gap: 6, alignItems: "center" }}>
                <input type="checkbox" checked={spoiler} onChange={(e) => setSpoiler(e.target.checked)} />
                <Icon name="warning" size={14} /> Spoiler
              </label>
              <span style={{ flex: 1 }} />
              <button className="btn btn-primary" disabled={busy} onClick={submit}>Post</button>
            </div>
          </div>
        </div>
      ) : authLoading ? null : (
        <p className="muted">
          <Link href="/login" style={{ color: "var(--crimson)" }}>Sign in</Link> to join the discussion.
        </p>
      )}

      {sorted.length === 0 ? (
        <div className="center-state">No comments yet — be the first to share your thoughts!</div>
      ) : (
        sorted.map((c) => <CommentNode key={c.id} c={c} onReply={setReplyTo} onChanged={load} />)
      )}
    </div>
  );
}
