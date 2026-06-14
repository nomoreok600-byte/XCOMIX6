"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { libraryStatus, setLibrary, removeLibrary } from "../lib/api";
import Icon from "./Icon";

const STATUSES = [
  ["reading", "Reading"],
  ["plan", "Plan to read"],
  ["completed", "Completed"],
  ["on_hold", "On hold"],
  ["dropped", "Dropped"],
];

export default function LibraryButton({ mangaId }) {
  const { user } = useAuth();
  const [entry, setEntry] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user || !mangaId) return;
    libraryStatus(mangaId)
      .then((d) => setEntry(d.entry))
      .catch(() => {});
  }, [user, mangaId]);

  if (!user) {
    return (
      <a href="/login" className="btn btn-ghost">
        <Icon name="bookmark" size={16} /> Sign in to bookmark
      </a>
    );
  }

  const choose = async (status) => {
    setBusy(true);
    try {
      await setLibrary({ manga_id: mangaId, status });
      setEntry({ status, folder_id: entry?.folder_id || null });
    } finally {
      setBusy(false);
    }
  };
  const remove = async () => {
    setBusy(true);
    try {
      await removeLibrary(mangaId);
      setEntry(null);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="row" style={{ gap: 8 }}>
      <select
        className="input"
        style={{ width: "auto" }}
        value={entry?.status || ""}
        disabled={busy}
        onChange={(e) => choose(e.target.value)}
      >
        <option value="" disabled>
          ＋ Add to library
        </option>
        {STATUSES.map(([v, l]) => (
          <option key={v} value={v}>
            {l}
          </option>
        ))}
      </select>
      {entry && (
        <button className="btn btn-ghost" disabled={busy} onClick={remove}>
          Remove
        </button>
      )}
    </div>
  );
}
