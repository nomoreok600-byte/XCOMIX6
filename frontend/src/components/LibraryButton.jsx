"use client";

import { useEffect, useState } from "react";
import { useAuth } from "../lib/auth";
import { libraryStatus, setLibrary, removeLibrary } from "../lib/api";
import Icon from "./Icon";
import Select from "./Select";

const STATUSES = [
  ["reading", "Reading"],
  ["plan", "Plan to read"],
  ["completed", "Completed"],
  ["on_hold", "On hold"],
  ["dropped", "Dropped"],
];

export default function LibraryButton({ mangaId }) {
  const { user, loading } = useAuth();
  const [entry, setEntry] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user || !mangaId) return;
    libraryStatus(mangaId)
      .then((d) => setEntry(d.entry))
      .catch(() => {});
  }, [user, mangaId]);

  // While auth is still resolving, show a neutral placeholder rather than
  // flashing "Sign in to bookmark" to a user who is actually logged in.
  if (loading) {
    return (
      <button className="btn btn-ghost" disabled aria-busy="true">
        <Icon name="bookmark" size={16} /> Library
      </button>
    );
  }

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
      <Select
        className="librarybtn-select"
        ariaLabel="Add to library"
        value={entry?.status || ""}
        onChange={(v) => choose(v)}
        placeholder="＋ Add to library"
        options={STATUSES.map(([v, l]) => ({ value: v, label: l }))}
      />
      {entry && (
        <button className="btn btn-ghost" disabled={busy} onClick={remove}>
          <Icon name="trash" size={15} /> Remove
        </button>
      )}
    </div>
  );
}
