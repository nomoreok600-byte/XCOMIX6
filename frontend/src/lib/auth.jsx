"use client";

import { createContext, useContext, useEffect, useState, useCallback } from "react";
import * as api from "./api";

const AuthContext = createContext(null);

const DEFAULT_THEME = "crimson";
const THEME_KEY = "xcomix_theme";

export const THEMES = {
  crimson: { crimson: "#ff2a5f", crimson2: "#ff0044", glow: "rgba(255,42,95,0.45)" },
  aqua: { crimson: "#14b8a6", crimson2: "#0d9488", glow: "rgba(20,184,166,0.4)" },
  cyber: { crimson: "#06b6d4", crimson2: "#3b82f6", glow: "rgba(6,182,212,0.45)" },
  violet: { crimson: "#a855f7", crimson2: "#7c3aed", glow: "rgba(168,85,247,0.45)" },
  toxic: { crimson: "#22c55e", crimson2: "#16a34a", glow: "rgba(34,197,94,0.45)" },
  amber: { crimson: "#ffb020", crimson2: "#f97316", glow: "rgba(255,176,32,0.45)" },
  // "neon" was the legacy auto-default users never explicitly picked, so it now
  // resolves to the new RED default (keeps the site red by default for existing
  // default accounts too; users who chose aqua/cyber/etc. keep their choice).
  neon: { crimson: "#ff2a5f", crimson2: "#ff0044", glow: "rgba(255,42,95,0.45)" },
};

export function applyTheme(name) {
  if (typeof document === "undefined") return;
  const key = THEMES[name] ? name : DEFAULT_THEME;
  const t = THEMES[key];
  const r = document.documentElement.style;
  r.setProperty("--crimson", t.crimson);
  r.setProperty("--crimson-2", t.crimson2);
  r.setProperty("--glow", `0 0 22px ${t.glow}`);
  r.setProperty("--border-strong", t.crimson);
  // Persist so the inline <head> script can pre-apply it on the next load
  // (prevents the color flash/flicker before React hydrates).
  try {
    window.localStorage.setItem(THEME_KEY, key);
  } catch {
    /* ignore */
  }
}
// Hide the internal back-compat alias from the theme picker.
export const THEME_NAMES = Object.keys(THEMES).filter((n) => n !== "neon");

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    if (!api.getToken()) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const { user } = await api.fetchMe();
      setUser(user);
      applyTheme(user.theme);
    } catch {
      api.setToken(null);
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  // Frontend-traffic-driven auto importer: ping the backend on load and then
  // periodically while a tab stays open. The backend gates the actual work to
  // every 15 min (latest) / 60 min (backlog), so this is safe to call often.
  useEffect(() => {
    api.activityPing();
    const t = setInterval(() => api.activityPing(), 5 * 60 * 1000);
    return () => clearInterval(t);
  }, []);

  const login = async (creds) => {
    const { token, user } = await api.login(creds);
    api.setToken(token);
    setUser(user);
    applyTheme(user.theme);
    return user;
  };
  const register = async (data) => {
    const { token, user } = await api.register(data);
    api.setToken(token);
    setUser(user);
    applyTheme(user.theme);
    return user;
  };
  const logout = () => {
    api.setToken(null);
    setUser(null);
    applyTheme(DEFAULT_THEME);
  };
  const updateUser = (u) => {
    setUser(u);
    if (u?.theme) applyTheme(u.theme);
  };

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, updateUser, refresh }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
