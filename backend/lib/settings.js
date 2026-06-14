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
];

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
  };
}

/** Public view: only ships ENABLED ad HTML + the announcement when it's on. */
async function publicConfig() {
  const m = await getAll();
  const ads = {};
  for (const slot of AD_SLOTS) {
    ads[slot] = isOn(m[`ad_${slot}_on`]) ? m[`ad_${slot}`] || "" : "";
  }
  const annOn = isOn(m.announcement_on);
  return {
    ads,
    announcement: { enabled: annOn, text: annOn ? m.announcement_text || "" : "" },
  };
}

module.exports = { AD_SLOTS, getAll, setMany, adminConfig, publicConfig };
