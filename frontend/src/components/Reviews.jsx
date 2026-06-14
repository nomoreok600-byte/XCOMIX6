"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { useAuth } from "../lib/auth";
import Avatar from "./Avatar";
import Stars from "./Stars";
import { fetchReviews, postReview } from "../lib/api";

export default function Reviews({ mangaId }) {
  const { user } = useAuth();
  const [data, setData] = useState({ summary: { avg: null, count: 0 }, data: [] });
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    fetchReviews(mangaId)
      .then(setData)
      .catch(() => {});
  }, [mangaId]);

  useEffect(() => {
    load();
  }, [load]);

  const submit = async () => {
    if (!rating) return;
    setBusy(true);
    try {
      await postReview({ manga_id: mangaId, rating, body });
      setBody("");
      load();
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <div className="rating-line">
        <span className="big">{data.summary.avg != null ? data.summary.avg.toFixed(1) : "—"}</span>
        <Stars value={data.summary.avg || 0} />
        <span className="faint">{data.summary.count} reviews</span>
      </div>

      {user ? (
        <div className="panel-box">
          <div className="row" style={{ marginBottom: 10 }}>
            <span className="muted">Your rating:</span>
            <Stars value={rating} onChange={setRating} />
          </div>
          <textarea
            className="input"
            rows={2}
            placeholder="Write a review (optional)…"
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
          <div style={{ marginTop: 10, textAlign: "right" }}>
            <button className="btn btn-primary" disabled={busy || !rating} onClick={submit}>
              Submit review
            </button>
          </div>
        </div>
      ) : (
        <p className="muted">
          <Link href="/login" style={{ color: "var(--crimson)" }}>
            Sign in
          </Link>{" "}
          to rate this title.
        </p>
      )}

      {data.data.map((r) => (
        <div key={r.id} className="review">
          <div className="review-head">
            <Avatar user={r.user} />
            <Link href={`/u/?username=${encodeURIComponent(r.user.username)}`} className="name">
              @{r.user.username}
            </Link>
            <Stars value={r.rating} />
          </div>
          {r.body && <div className="comment-body">{r.body}</div>}
        </div>
      ))}
    </div>
  );
}
