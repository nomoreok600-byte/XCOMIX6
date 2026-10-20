"use strict";

/**
 * Site settings store (key/value in `site_settings`) powering the admin-managed
 * ad slots and the site-wide announcement banner. Values are plain strings;
 * "on" flags are stored as "1"/"0".
 */

const { query, pool } = require("../db");

// Ad slots rendered on the frontend.
const AD_SLOTS = ["header", "footer", "manga", "chapter"];

// Every settings key we recognise (anything else is ignored on write).
const KEYS = [
  ...AD_SLOTS.map((s) => `ad_${s}`),
  ...AD_SLOTS.map((s) => `ad_${s}_on`),
  "announcement_text",
  "announcement_on",
  // Click-triggered redirect ("pop-under") ad — no on-page banner; an eligible
  // click opens this URL in a new tab, then a cooldown blocks the next one.
  "ad_redirect_on",
  "ad_redirect_url",
  "ad_redirect_cooldown",
];

// Clamp the per-visitor cooldown to a sane range (minutes).
function cooldownMinutes(raw) {
  const n = Number.parseInt(raw, 10);
  if (Number.isNaN(n)) return 2;
  return Math.min(Math.max(n, 1), 60);
}

async function getAll() {
  const rows = await query("SELECT `key`, `value` FROM site_settings");
  const map = {};
  for (const r of rows) map[r.key] = r.value;
  return map;
}

async function setMany(values) {
  for (const [key, value] of Object.entries(values || {})) {
    if (!KEYS.includes(key)) continue;
    await pool.execute(
      "INSERT INTO site_settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
      [key, value == null ? null : String(value)]
    );
  }
}

const isOn = (v) => String(v) === "1" || String(v).toLowerCase() === "true";

// Split a multi-line textarea value into trimmed, non-empty lines.
const splitLines = (s) =>
  String(s || "")
    .split(/\r?\n/)
    .map((x) => x.trim())
    .filter(Boolean);

/** Admin view: raw settings normalized into a structured object. */
async function adminConfig() {
  const m = await getAll();
  const ads = {};
  for (const slot of AD_SLOTS) {
    ads[slot] = { html: m[`ad_${slot}`] || "", enabled: isOn(m[`ad_${slot}_on`]) };
  }
  return {
    ads,
    announcement: { text: m.announcement_text || "", enabled: isOn(m.announcement_on) },
    redirect: {
      enabled: isOn(m.ad_redirect_on),
      url: m.ad_redirect_url || "",
      cooldownMin: cooldownMinutes(m.ad_redirect_cooldown),
    },
  };
}

/** Public view: only ships ENABLED ad HTML + the announcement when it's on. */
async function publicConfig() {
  const m = await getAll();
  const ads = {};
  for (const slot of AD_SLOTS) {
    ads[slot] = isOn(m[`ad_${slot}_on`]) ? m[`ad_${slot}`] || "" : "";
  }
  // Announcements: one per line → multiple closable, sliding pop banners.
  const annItems = splitLines(m.announcement_text);
  const annOn = isOn(m.announcement_on) && annItems.length > 0;
  // Redirect ("pop-under") ads: one direct link per line, rotated client-side.
  // Only http(s) links are exposed so the frontend never opens junk.
  const urls = splitLines(m.ad_redirect_url).filter((u) => /^https?:\/\//i.test(u));
  const redirectOn = isOn(m.ad_redirect_on) && urls.length > 0;
  return {
    ads,
    announcement: {
      enabled: annOn,
      items: annOn ? annItems : [],
      // Back-compat for older frontends that read a single string.
      text: annOn ? annItems[0] || "" : "",
    },
    redirect: {
      enabled: redirectOn,
      urls: redirectOn ? urls : [],
      url: redirectOn ? urls[0] || "" : "",
      cooldownMin: cooldownMinutes(m.ad_redirect_cooldown),
    },
  };
}

module.exports = { AD_SLOTS, getAll, setMany, adminConfig, publicConfig };
