"use client";

import { useState } from "react";
import Link from "next/link";
import SiteNav from "../../components/SiteNav";
import { useAuth } from "../../lib/auth";

export default function LoginPage() {
  const { login } = useAuth();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await login({ username, password });
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
          <h1>Welcome back</h1>
          <p className="sub">Sign in to your XCOMIX account.</p>
          {error && <div className="form-error">{error}</div>}
          <div className="field">
            <label>Username or email</label>
            <input className="input" value={username} onChange={(e) => setUsername(e.target.value)} required />
          </div>
          <div className="field">
            <label>Password</label>
            <input
              className="input"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <button className="btn btn-primary btn-full" disabled={busy}>
            {busy ? "Signing in…" : "Sign in"}
          </button>
          <div className="auth-foot">
            New here? <Link href="/register">Create an account</Link>
          </div>
        </form>
      </div>
    </>
  );
}
