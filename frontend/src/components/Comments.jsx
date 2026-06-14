"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useAuth } from "../lib/auth";
import Avatar from "./Avatar";
import {
  fetchComments,
  postComment,
  deleteComment,
  reactComment,
} from "../lib/api";

const EMOJIS = ["👍", "❤️", "🔥", "😂", "😮", "😢", "💀"];

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
  const [showEmoji, setShowEmoji] = useState(false);

  const react = async (emoji) => {
    await reactComment(c.id, emoji);
    setShowEmoji(false);
    onChanged();
  };
  const remove = async () => {
    await deleteComment(c.id);
    onChanged();
  };

  return (
    <div className={`comment${depth > 0 ? " reply" : ""}`}>
      <div className="comment-head">
        <Avatar user={c.user} />
        <Link href={`/u/?username=${encodeURIComponent(c.user.username)}`} className="name">
          @{c.user.username}
        </Link>
        <span className="when">{new Date(c.created_at).toLocaleString()}</span>
      </div>
      <div className="comment-body">
        {c.is_spoiler ? <Spoiler>{c.body}</Spoiler> : c.body}
      </div>
      {c.image_url && (
        // eslint-disable-next-line @next/next/no-img-element
        <img className="comment-img" src={c.image_url} alt="attachment" />
      )}
      <div className="comment-actions">
        {(c.reactions || []).map((r) => (
          <button
            key={r.emoji}
            className={`react-pill${r.mine ? " mine" : ""}`}
            onClick={() => react(r.emoji)}
            disabled={!user}
          >
            {r.emoji} {r.count}
          </button>
        ))}
        {user && (
          <>
            <button className="link-btn" onClick={() => setShowEmoji((s) => !s)}>
              React
            </button>
            {depth === 0 && (
              <button className="link-btn" onClick={() => onReply(c)}>
                Reply
              </button>
            )}
            {(user.id === c.user.id || user.role === "admin") && (
              <button className="link-btn" onClick={remove}>
                Delete
              </button>
            )}
          </>
        )}
      </div>
      {showEmoji && (
        <div className="emoji-row">
          {EMOJIS.map((e) => (
            <button key={e} onClick={() => react(e)}>
              {e}
            </button>
          ))}
        </div>
      )}
      {(c.replies || []).map((r) => (
        <CommentNode key={r.id} c={r} onReply={onReply} onChanged={onChanged} depth={depth + 1} />
      ))}
    </div>
  );
}

export default function Comments({ mangaId, chapterId }) {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [body, setBody] = useState("");
  const [spoiler, setSpoiler] = useState(false);
  const [image, setImage] = useState("");
  const [replyTo, setReplyTo] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    fetchComments({ manga_id: mangaId, chapter_id: chapterId })
      .then((d) => setList(d.data || []))
      .catch(() => {});
  }, [mangaId, chapterId]);

  useEffect(() => {
    load();
  }, [load]);

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
      {user ? (
        <div className="panel-box">
          {replyTo && (
            <div className="faint" style={{ marginBottom: 8 }}>
              Replying to @{replyTo.user.username}{" "}
              <button className="link-btn" onClick={() => setReplyTo(null)}>
                cancel
              </button>
            </div>
          )}
          <textarea
            className="input"
            rows={3}
            placeholder="Share your thoughts…"
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
          {image && (
            // eslint-disable-next-line @next/next/no-img-element
            <img className="comment-img" src={image} alt="preview" />
          )}
          <div className="comment-actions" style={{ marginTop: 10 }}>
            <label className="link-btn" style={{ cursor: "pointer" }}>
              📷 Image
              <input type="file" accept="image/*" hidden onChange={onFile} />
            </label>
            <label className="faint" style={{ display: "flex", gap: 6, alignItems: "center" }}>
              <input type="checkbox" checked={spoiler} onChange={(e) => setSpoiler(e.target.checked)} />
              Spoiler
            </label>
            <span style={{ flex: 1 }} />
            <button className="btn btn-primary" disabled={busy} onClick={submit}>
              Post
            </button>
          </div>
        </div>
      ) : (
        <p className="muted">
          <Link href="/login" style={{ color: "var(--crimson)" }}>
            Sign in
          </Link>{" "}
          to join the discussion.
        </p>
      )}

      {list.length === 0 ? (
        <div className="center-state">No comments yet — be the first.</div>
      ) : (
        list.map((c) => <CommentNode key={c.id} c={c} onReply={setReplyTo} onChanged={load} />)
      )}
    </div>
  );
}
