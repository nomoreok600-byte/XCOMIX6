"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Footer from "../../components/Footer";
import { useAuth, THEME_NAMES, applyTheme } from "../../lib/auth";
import {
  fetchProfile,
  fetchHistory,
  updateMe,
  changePassword,
  deleteAccount,
  proxyImage,
} from "../../lib/api";

function StatCard({ icon, n, label }) {
  return (
    <div className="stat-card">
      <div className="ic">{icon}</div>
      <div className="n">{n}</div>
      <div className="l">{label}</div>
    </div>
  );
}

export default function ProfilePage() {
  const { user, loading, updateUser, logout } = useAuth();
  const [tab, setTab] = useState("dashboard");
  const [data, setData] = useState(null);
  const [history, setHistory] = useState([]);
  const [shareUrl, setShareUrl] = useState("");
  const [copied, setCopied] = useState(false);
  const [form, setForm] = useState({ username: "", bio: "", avatar_url: "", banner_url: "", theme: "aqua", profile_public: true });
  const [msg, setMsg] = useState(null);
  const [pw, setPw] = useState({ current_password: "", new_password: "" });

  useEffect(() => {
    if (!user) return;
    setForm({
      username: user.username,
      bio: user.bio || "",
      avatar_url: user.avatar_url || "",
      banner_url: user.banner_url || "",
      theme: user.theme || "aqua",
      profile_public: user.profile_public,
    });
    setShareUrl(`${window.location.origin}/u/?username=${encodeURIComponent(user.username)}`);
    fetchProfile(user.username).then(setData).catch(() => {});
    fetchHistory().then((d) => setHistory(d.data || [])).catch(() => {});
  }, [user]);

  if (!loading && !user) {
    return (
      <>
        <SiteNav />
        <div className="center-state">
          <p>Sign in to manage your profile.</p>
          <Link href="/login" className="btn btn-primary">Sign in</Link>
        </div>
      </>
    );
  }
  if (!user) return <><SiteNav /><div className="center-state">Loading…</div></>;

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const flash = (text, ok = true) => { setMsg({ text, ok }); setTimeout(() => setMsg(null), 4000); };
  const stats = data?.stats || { followers: 0, following: 0, comments: 0, reviews: 0, library: 0 };
  const library = data?.library || [];

  const saveProfile = async () => {
    try { const { user: u } = await updateMe(form); updateUser(u); flash("Profile saved."); }
    catch (e) { flash(e.message, false); }
  };
  const savePassword = async () => {
    try { await changePassword(pw); setPw({ current_password: "", new_password: "" }); flash("Password changed."); }
    catch (e) { flash(e.message, false); }
  };
  const remove = async () => {
    if (!window.confirm("Delete your account permanently? This cannot be undone.")) return;
    try { await deleteAccount(); logout(); window.location.href = "/home"; }
    catch (e) { flash(e.message, false); }
  };
  const copyShare = () => {
    navigator.clipboard?.writeText(shareUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };
  const avatarStyle = form.avatar_url ? { backgroundImage: `url(${proxyImage(form.avatar_url)})`, backgroundSize: "cover", color: "transparent" } : undefined;

  return (
    <>
      <SiteNav />
      <main className="container">
        <div className="pf-banner" style={form.banner_url ? { backgroundImage: `url(${proxyImage(form.banner_url)})` } : undefined} />
        <div className="pf-top">
          <div className="pf-avatar" style={avatarStyle}>{!form.avatar_url && form.username.charAt(0).toUpperCase()}</div>
          <div style={{ flex: 1 }}>
            <h1 className="pf-name">{form.username}</h1>
            <div className="pf-pills">
              <span className="pf-pill"><span className="k">★</span> {stats.comments * 2 + stats.reviews * 3} Karma</span>
              <span className="pf-pill">👥 {stats.followers} Followers</span>
            </div>
          </div>
        </div>

        <div className="pf-tabs">
          {[["dashboard", "📊 Dashboard"], ["collections", "📚 Collections"], ["activity", "⚡ Activity"], ["settings", "⚙ Settings"]].map(([v, l]) => (
            <button key={v} className={`pf-tab${tab === v ? " active" : ""}`} onClick={() => setTab(v)}>{l}</button>
          ))}
        </div>

        {msg && <div className={msg.ok ? "form-ok" : "form-error"}>{msg.text}</div>}

        {tab === "dashboard" && (
          <>
            <div className="stat-cards">
              <StatCard icon="🔖" n={stats.library} label="Bookmarks" />
              <StatCard icon="📖" n={history.length} label="Reading" />
              <StatCard icon="⭐" n={stats.reviews} label="Reviews" />
              <StatCard icon="💬" n={stats.comments} label="Comments" />
            </div>

            <div className="panel-box" style={{ marginTop: 18 }}>
              <div className="row" style={{ justifyContent: "space-between" }}>
                <h3 style={{ margin: 0 }}>🔖 Bookmarks</h3>
                <Link href="/library" className="head-link">View All →</Link>
              </div>
              {library.length === 0 ? <p className="faint">No bookmarks yet.</p> : (
                <div className="slider" style={{ marginTop: 12 }}>
                  {library.map((l) => (
                    <Link key={l.manga.id} href={`/manga/?slug=${encodeURIComponent(l.manga.slug)}`} style={{ width: 110, flex: "0 0 auto" }}>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={proxyImage(l.manga.cover_url)} alt="" style={{ width: "100%", aspectRatio: "2/3", objectFit: "cover", borderRadius: 10 }} />
                    </Link>
                  ))}
                </div>
              )}
            </div>

            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>🕘 Reading History</h3>
              {history.length === 0 ? <p className="faint">No reading history yet. Start reading to see your progress here!</p> : (
                history.slice(0, 6).map((h) => (
                  <Link key={h.manga.id} href={`/manga/?slug=${encodeURIComponent(h.manga.slug)}`} className="list-row" style={{ textDecoration: "none", padding: 8 }}>
                    <div className="cover" style={{ width: 40, height: 56 }}>
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={proxyImage(h.manga.cover_url)} alt="" />
                    </div>
                    <div className="info">
                      <h3 style={{ fontSize: 14 }}>{h.manga.title}</h3>
                      <div className="meta-line">{h.chapter_number ? `Chapter ${h.chapter_number}` : "—"} · {new Date(h.updated_at).toLocaleDateString()}</div>
                    </div>
                  </Link>
                ))
              )}
            </div>

            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>🔗 Share Profile</h3>
              <p className="faint" style={{ marginTop: 0 }}>Your public profile URL</p>
              <div className="share-box">
                <input className="input" readOnly value={shareUrl} onFocus={(e) => e.target.select()} />
                <button className="btn btn-primary" onClick={copyShare}>{copied ? "Copied ✓" : "Copy"}</button>
              </div>
            </div>

            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>👤 Account</h3>
              <p className="faint" style={{ margin: "2px 0" }}>Username</p>
              <p style={{ margin: "0 0 10px", fontWeight: 700 }}>{user.username}</p>
              <p className="faint" style={{ margin: "2px 0" }}>Email</p>
              <p style={{ margin: "0 0 10px", fontWeight: 700 }}>{user.email}</p>
              <p className="faint" style={{ margin: "2px 0" }}>Member Since</p>
              <p style={{ margin: 0, fontWeight: 700 }}>{new Date(user.created_at).toLocaleDateString()}</p>
            </div>
          </>
        )}

        {tab === "collections" && (
          <div className="panel-box">
            <div className="row" style={{ justifyContent: "space-between" }}>
              <h3 style={{ margin: 0 }}>📚 Your Collections</h3>
              <Link href="/library" className="btn btn-ghost">＋ Manage folders</Link>
            </div>
            <p className="faint">Organize and share your favorite manga in custom folders. Manage them in your Library.</p>
            <Link href="/library" className="btn btn-primary">Open Library →</Link>
          </div>
        )}

        {tab === "activity" && (
          <>
            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>💬 My Comments</h3>
              <p className="faint">{stats.comments} comments · {stats.reviews} reviews posted.</p>
            </div>
            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>🔔 Notifications</h3>
              <Link href="/notifications" className="btn btn-ghost">View notifications →</Link>
            </div>
            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>📈 Activity Overview</h3>
              <div className="stat-cards">
                <StatCard icon="💬" n={stats.comments} label="Comments" />
                <StatCard icon="🔖" n={stats.library} label="Bookmarks" />
                <StatCard icon="👥" n={stats.following} label="Following" />
                <StatCard icon="⭐" n={stats.reviews} label="Reviews" />
              </div>
            </div>
          </>
        )}

        {tab === "settings" && (
          <>
            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>👤 Personal Information</h3>
              <div className="field"><label>Username</label><input className="input" value={form.username} onChange={(e) => set("username", e.target.value)} /></div>
              <div className="field"><label>Bio</label><textarea className="input" rows={3} placeholder="Tell others about yourself…" value={form.bio} onChange={(e) => set("bio", e.target.value)} /></div>
              <div className="field"><label>Avatar image URL</label><input className="input" value={form.avatar_url} onChange={(e) => set("avatar_url", e.target.value)} placeholder="https://…" /></div>
              <div className="field"><label>Banner image URL</label><input className="input" value={form.banner_url} onChange={(e) => set("banner_url", e.target.value)} placeholder="https://…" /></div>
              <div className="field">
                <label>Theme</label>
                <select className="input" value={form.theme} onChange={(e) => { set("theme", e.target.value); applyTheme(e.target.value); }}>
                  {THEME_NAMES.map((t) => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <label className="row" style={{ gap: 8 }}>
                <input type="checkbox" checked={form.profile_public} onChange={(e) => set("profile_public", e.target.checked)} />
                <span>Public profile (others can see your library)</span>
              </label>
              <div style={{ marginTop: 16 }}><button className="btn btn-primary" onClick={saveProfile}>Save changes</button></div>
            </div>

            <div className="panel-box">
              <h3 style={{ marginTop: 0 }}>🔑 Security</h3>
              <div className="field"><label>Current password</label><input className="input" type="password" value={pw.current_password} onChange={(e) => setPw((p) => ({ ...p, current_password: e.target.value }))} /></div>
              <div className="field"><label>New password</label><input className="input" type="password" value={pw.new_password} onChange={(e) => setPw((p) => ({ ...p, new_password: e.target.value }))} /></div>
              <button className="btn btn-ghost" onClick={savePassword}>Update password</button>
            </div>

            <div className="panel-box" style={{ borderColor: "rgba(255,0,68,0.4)" }}>
              <h3 style={{ marginTop: 0, color: "#ff7a9c" }}>⚠ Danger Zone</h3>
              <p className="faint">Permanently delete your account and all data. This cannot be undone.</p>
              <button className="btn btn-ghost" style={{ borderColor: "rgba(255,0,68,0.5)", color: "#ff7a9c" }} onClick={remove}>Delete My Account</button>
            </div>
          </>
        )}
      </main>
      <Footer />
    </>
  );
}
