"use client";

import { proxyImage } from "../lib/api";

export default function Avatar({ user, size }) {
  const cls = `avatar${size === "lg" ? " lg" : ""}`;
  const url = user?.avatar_url ? proxyImage(user.avatar_url) : "";
  if (url) {
    // eslint-disable-next-line @next/next/no-img-element
    return <img className={cls} src={url} alt={user.username} />;
  }
  const letter = (user?.username || "?").charAt(0).toUpperCase();
  return <span className={cls}>{letter}</span>;
}
