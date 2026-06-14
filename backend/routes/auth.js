"use strict";

const express = require("express");
const { query, pool } = require("../db");
const {
  hashPassword,
  verifyPassword,
  signToken,
  publicUser,
  requireAuth,
} = require("../lib/auth");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

const USERNAME_RE = /^[a-zA-Z0-9_]{3,30}$/;
const EMAIL_RE = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;

router.post("/register", wrap(async (req, res) => {
  const username = String(req.body.username || "").trim();
  const email = String(req.body.email || "").trim().toLowerCase();
  const password = String(req.body.password || "");

  if (!USERNAME_RE.test(username)) {
    return res.status(400).json({ error: "Username must be 3-30 chars (letters, numbers, _)" });
  }
  if (!EMAIL_RE.test(email)) return res.status(400).json({ error: "Invalid email" });
  if (password.length < 6) return res.status(400).json({ error: "Password must be 6+ characters" });

  const dupe = await query(
    "SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1",
    { u: username, e: email }
  );
  if (dupe.length) return res.status(409).json({ error: "Username or email already taken" });

  const hash = await hashPassword(password);
  // First registered user becomes the admin.
  const countRows = await query("SELECT COUNT(*) AS c FROM users");
  const role = Number(countRows[0].c) === 0 ? "admin" : "user";

  const [ins] = await pool.execute(
    "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
    [username, email, hash, role]
  );
  const rows = await query("SELECT * FROM users WHERE id = :id", { id: ins.insertId });
  res.status(201).json({ token: signToken(rows[0]), user: publicUser(rows[0]) });
}));

router.post("/login", wrap(async (req, res) => {
  const identifier = String(req.body.username || req.body.email || "").trim().toLowerCase();
  const password = String(req.body.password || "");
  const rows = await query(
    "SELECT * FROM users WHERE LOWER(username) = :id OR email = :id LIMIT 1",
    { id: identifier }
  );
  if (!rows.length || !(await verifyPassword(password, rows[0].password_hash))) {
    return res.status(401).json({ error: "Invalid credentials" });
  }
  res.json({ token: signToken(rows[0]), user: publicUser(rows[0]) });
}));

router.get("/me", requireAuth, (req, res) => {
  res.json({ user: publicUser(req.user) });
});

router.patch("/me", requireAuth, wrap(async (req, res) => {
  const fields = [];
  const params = { id: req.user.id };
  const allow = {
    avatar_url: "avatar_url",
    banner_url: "banner_url",
    bio: "bio",
    theme: "theme",
  };
  for (const [key, col] of Object.entries(allow)) {
    if (req.body[key] !== undefined) {
      fields.push(`${col} = :${col}`);
      params[col] = String(req.body[key]).slice(0, key === "bio" ? 500 : 1000);
    }
  }
  if (req.body.profile_public !== undefined) {
    fields.push("profile_public = :pp");
    params.pp = req.body.profile_public ? 1 : 0;
  }
  if (req.body.username !== undefined) {
    const username = String(req.body.username).trim();
    if (!USERNAME_RE.test(username)) return res.status(400).json({ error: "Invalid username" });
    const dupe = await query(
      "SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1",
      { u: username, id: req.user.id }
    );
    if (dupe.length) return res.status(409).json({ error: "Username already taken" });
    fields.push("username = :username");
    params.username = username;
  }
  if (!fields.length) return res.status(400).json({ error: "No updatable fields provided" });

  await query(`UPDATE users SET ${fields.join(", ")} WHERE id = :id`, params);
  const rows = await query("SELECT * FROM users WHERE id = :id", { id: req.user.id });
  res.json({ user: publicUser(rows[0]) });
}));

router.post("/change-password", requireAuth, wrap(async (req, res) => {
  const current = String(req.body.current_password || "");
  const next = String(req.body.new_password || "");
  if (next.length < 6) return res.status(400).json({ error: "New password must be 6+ characters" });
  if (!(await verifyPassword(current, req.user.password_hash))) {
    return res.status(401).json({ error: "Current password is incorrect" });
  }
  await query("UPDATE users SET password_hash = :h WHERE id = :id", {
    h: await hashPassword(next),
    id: req.user.id,
  });
  res.json({ ok: true });
}));

router.delete("/me", requireAuth, wrap(async (req, res) => {
  await query("DELETE FROM users WHERE id = :id", { id: req.user.id });
  res.json({ ok: true });
}));

module.exports = router;
