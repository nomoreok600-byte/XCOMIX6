"use strict";

/**
 * Auto-import scheduler — runs the MangaKatana + ManhwaBuddy "latest" crawlers
 * on a fixed interval with no human interaction. Configured entirely via env:
 *
 *   AUTO_IMPORT_ENABLED       "true" to start the loop on boot (default false)
 *   AUTO_IMPORT_INTERVAL_MIN  minutes between runs (default 180)
 *   AUTO_IMPORT_SOURCES       comma list: katana,buddy (default "katana,buddy")
 *   AUTO_IMPORT_LIMIT         manga per source per run (default 12)
 *   AUTO_IMPORT_MAX_CHAPTERS  recent chapters to resolve pages for (default 3)
 *
 * A run can also be triggered manually through the admin API.
 */

const importers = require("./importers");

const state = {
  running: false,
  timer: null,
  lastRun: null,
  lastResult: null,
  runs: 0,
};

function cfg() {
  return {
    enabled: String(process.env.AUTO_IMPORT_ENABLED || "false").toLowerCase() === "true",
    intervalMin: Number(process.env.AUTO_IMPORT_INTERVAL_MIN || 180),
    sources: String(process.env.AUTO_IMPORT_SOURCES || "katana,buddy")
      .split(",")
      .map((s) => s.trim().toLowerCase())
      .filter(Boolean),
    limit: Number(process.env.AUTO_IMPORT_LIMIT || 12),
    maxChapters: Number(process.env.AUTO_IMPORT_MAX_CHAPTERS || 3),
  };
}

/** Run one import pass across the configured sources. Safe to call manually. */
async function runOnce(overrides = {}) {
  if (state.running) return { skipped: true, reason: "already running" };
  const c = { ...cfg(), ...overrides };
  state.running = true;
  const started = new Date();
  const summary = { startedAt: started.toISOString(), sources: {} };
  try {
    for (const src of c.sources) {
      try {
        const results =
          src === "katana"
            ? await importers.importKatanaLatest({ limit: c.limit, maxChapters: c.maxChapters })
            : src === "buddy"
            ? await importers.importBuddyLatest({ limit: c.limit, maxChapters: c.maxChapters })
            : null;
        if (!results) continue;
        const ok = results.filter((r) => !r.error);
        summary.sources[src] = {
          imported: ok.length,
          newChapters: ok.reduce((n, r) => n + (r.newChapters || 0), 0),
          errors: results.length - ok.length,
        };
      } catch (err) {
        summary.sources[src] = { error: err.message };
      }
    }
  } finally {
    summary.finishedAt = new Date().toISOString();
    state.running = false;
    state.lastRun = summary.finishedAt;
    state.lastResult = summary;
    state.runs += 1;
  }
  return summary;
}

function schedule() {
  const c = cfg();
  const ms = Math.max(1, c.intervalMin) * 60 * 1000;
  state.timer = setTimeout(async function loop() {
    try {
      console.log("[scheduler] auto-import run starting…");
      const s = await runOnce();
      console.log("[scheduler] auto-import done:", JSON.stringify(s.sources));
    } catch (err) {
      console.warn("[scheduler] auto-import failed:", err.message);
    }
    state.timer = setTimeout(loop, ms);
  }, ms);
}

function start() {
  const c = cfg();
  if (!c.enabled) {
    console.log("[scheduler] auto-import disabled (set AUTO_IMPORT_ENABLED=true to enable).");
    return;
  }
  console.log(
    `[scheduler] auto-import enabled: every ${c.intervalMin}m, sources=${c.sources.join(",")}, limit=${c.limit}`
  );
  schedule();
}

function status() {
  return { ...cfg(), ...state, timer: undefined };
}

module.exports = { start, runOnce, status };
