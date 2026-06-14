"use client";

import { useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import { useAuth } from "../../lib/auth";

export default function RegisterPage() {
  const { register } = useAuth();
  const [form, setForm] = useState({ username: "", email: "", password: "" });
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await register(form);
      window.location.href = "/home";
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <SiteNav />
      <div className="auth-wrap">
        <form className="auth-card" onSubmit={submit}>
          <h1>Join XCOMIX</h1>
          <p className="sub">Create an account to bookmark, review and chat.</p>
          {error && <div className="form-error">{error}</div>}
          <div className="field">
            <label>Username</label>
            <input className="input" value={form.username} onChange={(e) => set("username", e.target.value)} required />
          </div>
          <div className="field">
            <label>Email</label>
            <input className="input" type="email" value={form.email} onChange={(e) => set("email", e.target.value)} required />
          </div>
          <div className="field">
            <label>Password</label>
            <input className="input" type="password" value={form.password} onChange={(e) => set("password", e.target.value)} required />
          </div>
          <button className="btn btn-primary btn-full" disabled={busy}>
            {busy ? "Creating…" : "Create account"}
          </button>
          <div className="auth-foot">
            Already have an account? <Link href="/login">Sign in</Link>
          </div>
        </form>
      </div>
    </>
  );
}
