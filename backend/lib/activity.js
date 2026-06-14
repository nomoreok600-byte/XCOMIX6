"use strict";

/**
 * Frontend-traffic-driven auto importer.
 *
 * Every page view on the static frontend pings `GET /api/activity/ping`. That
 * ping (cheap, debounced) decides whether enough time has passed to kick off
 * background work — with NO human and NO cron:
 *
 *   • LATEST  every 15 min  → import + update the newest titles (new chapters).
 *   • BACKLOG every 60 min  → crawl 5 more catalog "pages" to backfill the
 *                             library, REMEMBERING the page it stopped on so the
 *                             next run continues instead of re-fetching.
 *
 * The backlog cursor + last-run timestamps are persisted to a small JSON file so
 * progress survives restarts. All timing/sizes are env-tunable.
 */

const fs = require("node:fs");
const path = require("node:path");
const importers = require("./importers");
const scheduler = require("./scheduler");

const STATE_FILE = path.join(__dirname, "..", ".import-state.json");
const MIN = 60 * 1000;

function cfg() {
  return {
    enabled: String(process.env.ACTIVITY_IMPORT_ENABLED || "true").toLowerCase() === "true",
    latestIntervalMin: Number(process.env.ACTIVITY_LATEST_INTERVAL_MIN || 15),
    backlogIntervalMin: Number(process.env.ACTIVITY_BACKLOG_INTERVAL_MIN || 60),
    backlogPages: Math.max(1, Number(process.env.ACTIVITY_BACKLOG_PAGES || 5)),
    sources: String(process.env.AUTO_IMPORT_SOURCES || "katana,buddy")
      .split(",")
      .map((s) => s.trim().toLowerCase())
      .filter(Boolean),
    limit: Number(process.env.AUTO_IMPORT_LIMIT || 12),
    maxChapters: Number(process.env.AUTO_IMPORT_MAX_CHAPTERS || 3),
  };
}

const DEFAULT_STATE = {
  lastLatestAt: 0,
  lastBacklogAt: 0,
  // Per-source "next page to crawl" cursor (the bit we must remember).
  backlog: { katana: { nextPage: 1 }, buddy: { nextPage: 1 } },
  history: [],
};

function loadState() {
  try {
    const raw = JSON.parse(fs.readFileSync(STATE_FILE, "utf8"));
    return {
      ...DEFAULT_STATE,
      ...raw,
      backlog: { ...DEFAULT_STATE.backlog, ...(raw.backlog || {}) },
    };
  } catch {
    return { ...DEFAULT_STATE, backlog: { katana: { nextPage: 1 }, buddy: { nextPage: 1 } } };
  }
}

const state = loadState();
const running = { latest: false, backlog: false };

function persist() {
  try {
    fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2));
  } catch (err) {
    console.warn("[activity] could not persist import state:", err.message);
  }
}

function note(entry) {
  state.history.unshift({ at: new Date().toISOString(), ...entry });
  state.history = state.history.slice(0, 20);
}

/** Run one "latest" crawl (new chapters for the freshest titles). */
async function runLatest() {
  running.latest = true;
  const c = cfg();
  try {
    const summary = await scheduler.runOnce({
      sources: c.sources,
      limit: c.limit,
      maxChapters: c.maxChapters,
    });
    note({ kind: "latest", sources: summary.sources });
  } catch (err) {
    note({ kind: "latest", error: err.message });
  } finally {
    running.latest = false;
    persist();
  }
}

/**
 * Crawl the next `backlogPages` catalog pages per source, then advance the saved
 * cursor so the following run continues from where this one stopped. If a source
 * runs dry (no results) we wrap its cursor back to page 1 to keep refreshing.
 */
async function runBacklog() {
  running.backlog = true;
  const c = cfg();
  try {
    for (const src of c.sources) {
      const cursor = state.backlog[src] || (state.backlog[src] = { nextPage: 1 });
      const startPage = Math.max(1, Number(cursor.nextPage) || 1);
      const endPage = startPage + c.backlogPages - 1;
      let results = [];
      try {
        if (src === "katana") {
          results = await importers.importKatanaBacklog({ startPage, endPage, limit: c.limit });
        } else if (src === "buddy") {
          results = await importers.importBuddyBacklog({ startPage, endPage, limit: c.limit });
        }
      } catch (err) {
        note({ kind: "backlog", source: src, startPage, endPage, error: err.message });
        continue;
      }
      // Remember where to resume; wrap to the start when the source runs dry.
      cursor.nextPage = results.length === 0 ? 1 : endPage + 1;
      note({
        kind: "backlog",
        source: src,
        crawled: `${startPage}-${endPage}`,
        imported: results.length,
        resumeAt: cursor.nextPage,
      });
      persist();
    }
  } finally {
    running.backlog = false;
    persist();
  }
}

/**
 * Called on every frontend page view. Cheap + non-blocking: it only flips the
 * last-run timestamps (persisted immediately so concurrent visits don't double
 * fire) and launches the heavy crawls in the background.
 */
function ping() {
  const c = cfg();
  if (!c.enabled) return { enabled: false };
  const now = Date.now();
  const triggered = {};

  if (!running.latest && now - state.lastLatestAt >= c.latestIntervalMin * MIN) {
    state.lastLatestAt = now;
    persist();
    triggered.latest = true;
    runLatest().catch((err) => console.warn("[activity] latest run failed:", err.message));
  }

  if (!running.backlog && now - state.lastBacklogAt >= c.backlogIntervalMin * MIN) {
    state.lastBacklogAt = now;
    persist();
    triggered.backlog = true;
    runBacklog().catch((err) => console.warn("[activity] backlog run failed:", err.message));
  }

  return {
    enabled: true,
    triggered,
    running: { ...running },
    nextLatestInSec: Math.max(0, Math.round((state.lastLatestAt + c.latestIntervalMin * MIN - now) / 1000)),
    nextBacklogInSec: Math.max(0, Math.round((state.lastBacklogAt + c.backlogIntervalMin * MIN - now) / 1000)),
    backlogCursor: state.backlog,
  };
}

function status() {
  return {
    config: cfg(),
    running: { ...running },
    lastLatestAt: state.lastLatestAt ? new Date(state.lastLatestAt).toISOString() : null,
    lastBacklogAt: state.lastBacklogAt ? new Date(state.lastBacklogAt).toISOString() : null,
    backlogCursor: state.backlog,
    history: state.history,
  };
}

module.exports = { ping, status, runLatest, runBacklog };
