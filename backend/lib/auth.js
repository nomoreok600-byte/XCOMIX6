"use strict";

const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");
const { query } = require("../db");

const JWT_SECRET = process.env.JWT_SECRET || "dev-secret-change-me";
const JWT_EXPIRES = process.env.JWT_EXPIRES || "30d";

async function hashPassword(plain) {
  return bcrypt.hash(plain, 10);
}

async function verifyPassword(plain, hash) {
  return bcrypt.compare(plain, hash);
}

function signToken(user) {
  return jwt.sign({ uid: String(user.id), username: user.username, role: user.role }, JWT_SECRET, {
    expiresIn: JWT_EXPIRES,
  });
}

function publicUser(row) {
  if (!row) return null;
  return {
    id: String(row.id),
    username: row.username,
    email: row.email,
    role: row.role,
    avatar_url: row.avatar_url || "",
    banner_url: row.banner_url || "",
    bio: row.bio || "",
    theme: row.theme || "neon",
    profile_public: Boolean(row.profile_public),
    created_at: row.created_at,
  };
}

/** Decode the Bearer token (if any) and attach req.user. Never throws. */
async function attachUser(req, _res, next) {
  const header = req.headers.authorization || "";
  const token = header.startsWith("Bearer ") ? header.slice(7) : null;
  if (token) {
    try {
      const payload = jwt.verify(token, JWT_SECRET);
      const rows = await query("SELECT * FROM users WHERE id = :id LIMIT 1", { id: payload.uid });
      if (rows[0]) req.user = rows[0];
    } catch {
      /* invalid/expired token → treat as anonymous */
    }
  }
  next();
}

/** Require an authenticated user. */
function requireAuth(req, res, next) {
  if (!req.user) return res.status(401).json({ error: "Authentication required" });
  next();
}

/** Require an admin (JWT admin role OR the X-Admin-Token header). */
function requireAdmin(req, res, next) {
  const adminToken = process.env.ADMIN_TOKEN || "";
  const headerToken = req.headers["x-admin-token"] || req.query.admin_token;
  if (adminToken && headerToken === adminToken) return next();
  if (req.user && req.user.role === "admin") return next();
  return res.status(403).json({ error: "Admin access required" });
}

module.exports = {
  JWT_SECRET,
  hashPassword,
  verifyPassword,
  signToken,
  publicUser,
  attachUser,
  requireAuth,
  requireAdmin,
};
