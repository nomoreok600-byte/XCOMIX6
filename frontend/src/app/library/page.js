"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Footer from "../../components/Footer";
import Icon from "../../components/Icon";
import Select from "../../components/Select";
import { useAuth } from "../../lib/auth";
import {
  fetchLibrary,
  fetchFolders,
  createFolder,
  deleteFolder,
  fetchHistory,
  removeLibrary,
  setLibrary,
  clearHistory,
  removeHistoryItem,
  proxyImage,
  decodeEntities,
} from "../../lib/api";

const STATUS_TABS = [
  ["", "All"],
  ["reading", "Reading"],
  ["plan", "Plan to read"],
  ["completed", "Completed"],
  ["on_hold", "On hold"],
  ["dropped", "Dropped"],
];

// A bookmark tile: cover links to the title, with inline "move to folder" and
// "remove" controls so users can actually manage their library.
function BookmarkTile({ item, folders, onRemove, onMove }) {
  const m = item.manga;
  return (
    <div className="lib-tile">
      <Link href={`/manga/?slug=${encodeURIComponent(m.slug)}`} className="lib-tile-cover">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={proxyImage(m.cover_url)} alt={decodeEntities(m.title)} loading="lazy" />
        <div className="tag-row">
          {m.is_18_plus && <span className="tag adult">18+</span>}
          <span className="tag type">{m.type || "Manga"}</span>
        </div>
      </Link>
      <div className="lib-tile-body">
        <Link href={`/manga/?slug=${encodeURIComponent(m.slug)}`} className="lib-tile-title">
          {decodeEntities(m.title)}
        </Link>
        <div className="lib-tile-actions">
          <Select
            className="lib-select"
            ariaLabel="Move to folder"
            value={item.folder_id || ""}
            onChange={(v) => onMove(item, v)}
            options={[{ value: "", label: "No folder" }, ...folders.map((f) => ({ value: String(f.id), label: f.name }))]}
          />
          <button className="icon-btn danger" onClick={() => onRemove(m.id)} title="Remove bookmark" aria-label="Remove bookmark">
            <Icon name="trash" size={16} />
          </button>
        </div>
      </div>
    </div>
  );
}

export default function LibraryPage() {
  const { user, loading: authLoading } = useAuth();
  const [tab, setTab] = useState("");
  const [view, setView] = useState("library"); // library | history | folders
  const [items, setItems] = useState([]);
  const [folders, setFolders] = useState([]);
  const [history, setHistory] = useState([]);
  const [newFolder, setNewFolder] = useState("");
  const [openFolder, setOpenFolder] = useState(null); // { id, name }
  const [folderItems, setFolderItems] = useState([]);

  const loadLib = useCallback(() => {
    fetchLibrary({ status: tab }).then((d) => setItems(d.data || [])).catch(() => {});
  }, [tab]);

  const loadFolders = useCallback(() => {
    fetchFolders().then((d) => setFolders(d.data || [])).catch(() => {});
  }, []);

  useEffect(() => {
    if (!user) return;
    loadFolders();
    if (view === "library") loadLib();
    if (view === "history") fetchHistory().then((d) => setHistory(d.data || [])).catch(() => {});
  }, [user, view, loadLib, loadFolders]);

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
    loadFolders();
  };

  const removeBookmark = async (mangaId) => {
    await removeLibrary(mangaId);
    setItems((prev) => prev.filter((it) => it.manga.id !== mangaId));
    setFolderItems((prev) => prev.filter((it) => it.manga.id !== mangaId));
    loadFolders();
  };

  const moveToFolder = async (item, folderId) => {
    await setLibrary({ manga_id: item.manga.id, status: item.status || "plan", folder_id: folderId || null });
    setItems((prev) => prev.map((it) => (it.id === item.id ? { ...it, folder_id: folderId || null } : it)));
    loadFolders();
  };

  const viewFolder = async (f) => {
    setOpenFolder(f);
    const d = await fetchLibrary({ folder: f.id });
    setFolderItems(d.data || []);
  };

  const clearAllHistory = async () => {
    await clearHistory();
    setHistory([]);
  };
  const deleteOneHistory = async (mangaId) => {
    await removeHistoryItem(mangaId);
    setHistory((prev) => prev.filter((h) => h.manga.id !== mangaId));
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
            <button key={v} className={`tab${view === v ? " active" : ""}`} onClick={() => { setView(v); setOpenFolder(null); }}>
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
                  <BookmarkTile key={it.id} item={it} folders={folders} onRemove={removeBookmark} onMove={moveToFolder} />
                ))}
              </div>
            )}
          </>
        )}

        {view === "history" && (
          <>
            {history.length > 0 && (
              <div className="row" style={{ justifyContent: "flex-end", marginBottom: 12 }}>
                <button className="btn btn-ghost" onClick={clearAllHistory}>
                  <Icon name="trash" size={16} /> Clear all history
                </button>
              </div>
            )}
            {history.length === 0 ? (
              <div className="center-state">No reading history yet.</div>
            ) : (
              history.map((h) => (
                <div key={h.manga.id} className="lb-row">
                  <Link href={`/manga/?slug=${encodeURIComponent(h.manga.slug)}`} className="lb-row-main" style={{ textDecoration: "none" }}>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img src={proxyImage(h.manga.cover_url)} alt="" style={{ width: 40, height: 56, objectFit: "cover", borderRadius: 6 }} />
                    <div>
                      <div style={{ fontWeight: 800 }}>{decodeEntities(h.manga.title)}</div>
                      <div className="faint">
                        {h.chapter_number ? `Chapter ${h.chapter_number}` : "—"} · {new Date(h.updated_at).toLocaleString()}
                      </div>
                    </div>
                  </Link>
                  <button className="icon-btn danger" onClick={() => deleteOneHistory(h.manga.id)} title="Remove from history" aria-label="Remove from history">
                    <Icon name="trash" size={16} />
                  </button>
                </div>
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
                onKeyDown={(e) => e.key === "Enter" && addFolder()}
              />
              <button className="btn btn-primary" onClick={addFolder}><Icon name="plus" size={16} /> Create</button>
            </div>

            {openFolder ? (
              <>
                <div className="row" style={{ margin: "16px 0", gap: 10 }}>
                  <button className="btn btn-ghost" onClick={() => setOpenFolder(null)}>
                    <Icon name="chevronLeft" size={16} /> All folders
                  </button>
                  <h3 style={{ margin: 0, display: "flex", alignItems: "center", gap: 8 }}>
                    <Icon name="folder" size={18} /> {openFolder.name}
                  </h3>
                </div>
                {folderItems.length === 0 ? (
                  <div className="center-state">
                    This folder is empty. Open the <b>Bookmarks</b> tab and pick this folder from a title&apos;s
                    folder menu to add it here.
                  </div>
                ) : (
                  <div className="grid">
                    {folderItems.map((it) => (
                      <BookmarkTile key={it.id} item={it} folders={folders} onRemove={removeBookmark} onMove={(item, fid) => { moveToFolder(item, fid); setFolderItems((p) => p.filter((x) => x.id !== item.id || String(fid) === String(openFolder.id))); }} />
                    ))}
                  </div>
                )}
              </>
            ) : folders.length === 0 ? (
              <div className="center-state">No custom folders yet. Create one above.</div>
            ) : (
              folders.map((f) => (
                <div key={f.id} className="lb-row">
                  <button className="lb-row-main" onClick={() => viewFolder(f)} style={{ background: "none", border: 0, cursor: "pointer", color: "inherit", textAlign: "left" }}>
                    <span className="lib-folder-ico"><Icon name="folder" size={18} /></span>
                    <div>
                      <div style={{ fontWeight: 800 }}>{f.name}</div>
                      <div className="faint">{f.count} titles</div>
                    </div>
                  </button>
                  <button className="btn btn-ghost" onClick={() => viewFolder(f)}>Open</button>
                  <button
                    className="icon-btn danger"
                    title="Delete folder"
                    aria-label="Delete folder"
                    onClick={async () => { await deleteFolder(f.id); loadFolders(); }}
                  >
                    <Icon name="trash" size={16} />
                  </button>
                </div>
              ))
            )}
          </>
        )}
      </main>
      <Footer />
    </>
  );
}
