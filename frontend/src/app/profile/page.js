"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import Avatar from "../../components/Avatar";
import { useAuth, THEME_NAMES, applyTheme } from "../../lib/auth";
import { updateMe, changePassword, deleteAccount, proxyImage } from "../../lib/api";

export default function ProfilePage() {
  const { user, loading, updateUser, logout } = useAuth();
  const [form, setForm] = useState({ username: "", bio: "", avatar_url: "", banner_url: "", theme: "neon", profile_public: true });
  const [msg, setMsg] = useState(null);
  const [pw, setPw] = useState({ current_password: "", new_password: "" });

  useEffect(() => {
    if (user) {
      setForm({
        username: user.username,
        bio: user.bio || "",
        avatar_url: user.avatar_url || "",
        banner_url: user.banner_url || "",
        theme: user.theme || "neon",
        profile_public: user.profile_public,
      });
    }
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

  const saveProfile = async () => {
    try {
      const { user: u } = await updateMe(form);
      updateUser(u);
      flash("Profile saved.");
    } catch (e) { flash(e.message, false); }
  };
  const savePassword = async () => {
    try {
      await changePassword(pw);
      setPw({ current_password: "", new_password: "" });
      flash("Password changed.");
    } catch (e) { flash(e.message, false); }
  };
  const remove = async () => {
    if (!window.confirm("Delete your account permanently? This cannot be undone.")) return;
    try { await deleteAccount(); logout(); window.location.href = "/home"; }
    catch (e) { flash(e.message, false); }
  };

  return (
    <>
      <SiteNav />
      <main className="container">
        <div
          className="profile-banner"
          style={form.banner_url ? { backgroundImage: `url(${proxyImage(form.banner_url)})` } : undefined}
        />
        <div className="profile-head">
          <Avatar user={form} size="lg" />
          <div>
            <h1 style={{ margin: 0 }}>@{form.username}</h1>
            <Link href={`/u/?username=${encodeURIComponent(user.username)}`} className="faint">
              View public profile →
            </Link>
          </div>
        </div>

        {msg && <div className={msg.ok ? "form-ok" : "form-error"} style={{ marginTop: 18 }}>{msg.text}</div>}

        <div className="panel-box" style={{ marginTop: 18 }}>
          <h3 style={{ marginTop: 0 }}>Profile</h3>
          <div className="field">
            <label>Username</label>
            <input className="input" value={form.username} onChange={(e) => set("username", e.target.value)} />
          </div>
          <div className="field">
            <label>Bio</label>
            <textarea className="input" rows={3} value={form.bio} onChange={(e) => set("bio", e.target.value)} />
          </div>
          <div className="field">
            <label>Avatar image URL</label>
            <input className="input" value={form.avatar_url} onChange={(e) => set("avatar_url", e.target.value)} placeholder="https://…" />
          </div>
          <div className="field">
            <label>Banner image URL</label>
            <input className="input" value={form.banner_url} onChange={(e) => set("banner_url", e.target.value)} placeholder="https://…" />
          </div>
          <div className="field">
            <label>Theme</label>
            <select
              className="input"
              value={form.theme}
              onChange={(e) => { set("theme", e.target.value); applyTheme(e.target.value); }}
            >
              {THEME_NAMES.map((t) => <option key={t} value={t}>{t}</option>)}
            </select>
          </div>
          <label className="row" style={{ gap: 8 }}>
            <input type="checkbox" checked={form.profile_public} onChange={(e) => set("profile_public", e.target.checked)} />
            <span>Public profile (others can see my library)</span>
          </label>
          <div style={{ marginTop: 16 }}>
            <button className="btn btn-primary" onClick={saveProfile}>Save profile</button>
          </div>
        </div>

        <div className="panel-box">
          <h3 style={{ marginTop: 0 }}>Change password</h3>
          <div className="field">
            <label>Current password</label>
            <input className="input" type="password" value={pw.current_password} onChange={(e) => setPw((p) => ({ ...p, current_password: e.target.value }))} />
          </div>
          <div className="field">
            <label>New password</label>
            <input className="input" type="password" value={pw.new_password} onChange={(e) => setPw((p) => ({ ...p, new_password: e.target.value }))} />
          </div>
          <button className="btn btn-ghost" onClick={savePassword}>Update password</button>
        </div>

        <div className="panel-box" style={{ borderColor: "rgba(255,0,68,0.4)" }}>
          <h3 style={{ marginTop: 0, color: "#ff7a9c" }}>Danger zone</h3>
          <p className="faint">Permanently delete your account, library, comments and messages.</p>
          <button className="btn btn-ghost" style={{ borderColor: "rgba(255,0,68,0.5)" }} onClick={remove}>
            Delete account
          </button>
        </div>
      </main>
      <footer className="footer"><div className="container">XCOMIX · profile</div></footer>
    </>
  );
}
