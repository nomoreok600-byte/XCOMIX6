"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import MangaCard from "../../components/MangaCard";
import { useAuth } from "../../lib/auth";
import {
  fetchLibrary,
  fetchFolders,
  createFolder,
  deleteFolder,
  fetchHistory,
  proxyImage,
} from "../../lib/api";

const STATUS_TABS = [
  ["", "All"],
  ["reading", "Reading"],
  ["plan", "Plan to read"],
  ["completed", "Completed"],
  ["on_hold", "On hold"],
  ["dropped", "Dropped"],
];

export default function LibraryPage() {
  const { user, loading: authLoading } = useAuth();
  const [tab, setTab] = useState("");
  const [view, setView] = useState("library"); // library | history | folders
  const [items, setItems] = useState([]);
  const [folders, setFolders] = useState([]);
  const [history, setHistory] = useState([]);
  const [newFolder, setNewFolder] = useState("");

  const loadLib = useCallback(() => {
    fetchLibrary({ status: tab }).then((d) => setItems(d.data || [])).catch(() => {});
  }, [tab]);

  useEffect(() => {
    if (!user) return;
    if (view === "library") loadLib();
    if (view === "folders") fetchFolders().then((d) => setFolders(d.data || [])).catch(() => {});
    if (view === "history") fetchHistory().then((d) => setHistory(d.data || [])).catch(() => {});
  }, [user, view, loadLib]);

  if (!authLoading && !user) {
    return (
      <>
        <SiteNav />
        <div className="center-state">
          <p>Sign in to access your library.</p>
          <Link href="/login" className="btn btn-primary">Sign in</Link>
        </div>
      </>
    );
  }

  const addFolder = async () => {
    if (!newFolder.trim()) return;
    await createFolder(newFolder.trim());
    setNewFolder("");
    fetchFolders().then((d) => setFolders(d.data || []));
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="section-head" style={{ marginTop: 24 }}>
          <span className="bar" />
          <h2>My Library</h2>
        </div>

        <div className="subnav">
          {[["library", "Bookmarks"], ["history", "Reading history"], ["folders", "Folders"]].map(([v, l]) => (
            <button key={v} className={`tab${view === v ? " active" : ""}`} onClick={() => setView(v)}>
              {l}
            </button>
          ))}
        </div>

        {view === "library" && (
          <>
            <div className="tabs">
              {STATUS_TABS.map(([v, l]) => (
                <button key={v} className={`tab${tab === v ? " active" : ""}`} onClick={() => setTab(v)}>
                  {l}
                </button>
              ))}
            </div>
            {items.length === 0 ? (
              <div className="center-state">No titles here yet.</div>
            ) : (
              <div className="grid">
                {items.map((it) => (
                  <MangaCard key={it.id} manga={{ ...it.manga, status: it.manga.status }} />
                ))}
              </div>
            )}
          </>
        )}

        {view === "history" && (
          <>
            {history.length === 0 ? (
              <div className="center-state">No reading history yet.</div>
            ) : (
              history.map((h) => (
                <Link
                  key={h.manga.id}
                  href={`/manga/?slug=${encodeURIComponent(h.manga.slug)}`}
                  className="lb-row"
                  style={{ textDecoration: "none" }}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={proxyImage(h.manga.cover_url)} alt="" style={{ width: 40, height: 56, objectFit: "cover", borderRadius: 6 }} />
                  <div>
                    <div style={{ fontWeight: 800 }}>{h.manga.title}</div>
                    <div className="faint">
                      {h.chapter_number ? `Chapter ${h.chapter_number}` : "—"} · {new Date(h.updated_at).toLocaleString()}
                    </div>
                  </div>
                </Link>
              ))
            )}
          </>
        )}

        {view === "folders" && (
          <>
            <div className="panel-box row">
              <input
                className="input"
                style={{ flex: 1 }}
                placeholder="New folder name…"
                value={newFolder}
                onChange={(e) => setNewFolder(e.target.value)}
              />
              <button className="btn btn-primary" onClick={addFolder}>Create</button>
            </div>
            {folders.length === 0 ? (
              <div className="center-state">No custom folders yet.</div>
            ) : (
              folders.map((f) => (
                <div key={f.id} className="lb-row">
                  <span style={{ fontWeight: 800 }}>📁 {f.name}</span>
                  <span className="faint">{f.count} titles</span>
                  <button
                    className="link-btn"
                    style={{ marginLeft: "auto" }}
                    onClick={async () => { await deleteFolder(f.id); fetchFolders().then((d) => setFolders(d.data || [])); }}
                  >
                    Delete
                  </button>
                </div>
              ))
            )}
          </>
        )}
      </main>
      <footer className="footer"><div className="container">XCOMIX · library</div></footer>
    </>
  );
}
