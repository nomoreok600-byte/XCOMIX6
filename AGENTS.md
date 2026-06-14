# AGENTS.md

## Project

Manga Headless Frontend: a **Next.js 15 (App Router)** site that reads from a
headless **WordPress XCOMIX REST API**. See `README.md` for routes, architecture,
the WordPress theme package, and full cPanel deployment steps.

## XCOMIX decoupled engine (database/ + backend/ + frontend/)

A from-scratch alternative stack lives in three top-level folders and is fully
independent of the WordPress-backed manga frontend above. This is the **active
product** on this branch (a full manga platform: catalog + accounts + social).
- `database/` — MySQL `schema.sql` (tables `mangas`, `chapters`, `pages`) plus
 `app-schema.sql` (additive: `genres`, `users`, `library`/`folders`,
 `reading_history`, `reviews`, `comments`/`comment_reactions`, `notifications`,
 `messages`, `follows`, and extra `mangas`/`chapters` columns) + `seed.sql`.
- `backend/` — Express + MySQL2 + CORS API for `www.a3555bet.com`. Catalog:
 `/api/manga`, `/api/chapters/:id/pages`, `/api/proxy/image`, plus
 `/api/catalog/*` (genres/browse/popular/recent/completed/random/rich detail).
 Social/user: `/api/auth/*` (JWT), `/api/library/*`, `/api/social/*`
 (comments+reviews), `/api/community/*` (profiles/follow/messages/notifications/
 leaderboard). Admin dashboard at **`/admin`** (stats + import controls).
 Run: `cd backend && cp .env.example .env` (set DB creds), `npm install`, `npm start`.
- `frontend/` — Next.js `output: 'export'` static site for `https://www.xcomix.top`.
 Pages: landing `/` → `/home`, `/browse`, `/recent`, `/manga`, `/reader`,
 `/login`, `/register`, `/library`, `/profile`, `/u`, `/community`,
 `/leaderboard`, `/messages`, `/notifications`.
 Run: `cd frontend && npm install`, set `NEXT_PUBLIC_API_BASE`, `npm run build` (emits `out/`).

### Importers (fully automatic, no human)
- `backend/scripts/katana-import.mjs` — **MangaKatana** importer (JS port of
 `wordpress-themes/mangaverse-xcomix-theme/scraper.php`). `--url=` or `--latest`.
- `backend/scripts/buddy-import.mjs` — **ManhwaBuddy** importer (`--url=`/`--latest`).
- `backend/lib/importers.js` — shared engine both scripts + the scheduler + admin
 use; links genres and emits new-chapter notifications to bookmarkers.
- `backend/lib/scheduler.js` — background auto-import loop. Enable with env
 `AUTO_IMPORT_ENABLED=true` (interval/sources/limit via `AUTO_IMPORT_*`). Can also
 be triggered on demand from the admin dashboard ("Run latest crawl now") or
 `POST /admin/import/run`. No human interaction needed.

Non-obvious caveats:
- The backend needs a running **MySQL/MariaDB** (a system dependency, not in the
 update script). Load `database/schema.sql`, then `database/app-schema.sql`, then
 (optionally) `database/seed.sql`. `app-schema.sql` is idempotent.
- The frontend uses **query-param SPA routing** (e.g. `/manga/?slug=...`,
 `/reader/?id=...`), so there is **no** `generateStaticParams` build-time API
 dependency — newly imported titles work immediately with no rebuild.
- All artwork renders through `${API_BASE}/api/proxy/image?url=...`; never hotlink
 source images directly (they 403). The proxy spoofs headers + host-based Referer.
- The first registered user automatically becomes `role=admin`.
- **18+ titles are hidden by default.** Catalog list endpoints exclude
  `is_18_plus` unless called with `?adult=1`; the frontend sends that only when
  the nav "18+" toggle is on (persisted in `localStorage`).
- Auto-import is **off by default**. Enable it persistently with
  `AUTO_IMPORT_ENABLED=true`, or at runtime from `/admin` (or
  `POST /admin/scheduler/start`); when enabled it runs a first pass shortly
  after boot then every `AUTO_IMPORT_INTERVAL_MIN`. `POST /admin/import/backlog`
  deep-crawls multiple "latest" pages to backfill the catalog.
- The frontend is teal by default (theme `aqua`); `theme="neon"` from older
  accounts is aliased to the same teal palette. The community page is a global
  forum "wall" (`/api/community/feed`).
- SEO note: it's a static export with **query-param** title routes
  (`/manga/?slug=`), so per-title pages share one HTML shell — `robots.txt` +
  `sitemap.xml` + per-route metadata exist, but true per-title SSR/SSG is not
  possible without changing the routing model.
- `backend/scripts/import-demo-data.mjs` re-populates MySQL from a source API
 (the JS port of the legacy scraper sync stage).

## Cursor Cloud specific instructions

### What this repo is
- The `main` branch is empty (its files were deleted). The active application
  code lives on the manga `merge-theme` branch and is what we develop here. Do
  not assume the app is "AniTeams" from older history — the current product is
  the manga headless frontend.
- Standard commands live in `package.json` scripts and `README.md`; use those
  rather than reinventing them.

### Running it (non-obvious caveats)
- `npm run dev` serves on `http://localhost:3000`; `/` redirects to `/manga`.
- Environment values go in `.env.local` (gitignored). The key vars are
  `WORDPRESS_API_URL` / `NEXT_PUBLIC_WORDPRESS_API_URL`,
  `WORDPRESS_API_PREFIX` (default `/wp-json/xcomix/v1`), and `WORDPRESS_SYNC_TOKEN`.
- IMPORTANT: `WORDPRESS_API_URL` must be the WordPress **origin root** (e.g.
  `https://www.a3555bet.com`). `app/lib/headless/wordpress.js` appends the
  prefix itself, so a value ending in a page path like `/home/` yields 404s.
- If the WordPress API is unreachable, the frontend silently falls back to a
  built-in **demo payload** (`demoHomePayload()` etc.). Real-vs-demo data is the
  quickest signal of whether the backend env is wired correctly — look for real
  titles (e.g. "Study Group") and `mangakatana` image URLs.
- Remote cover/page images are always rendered through `/api/proxy-image?url=...`
  (it injects desktop headers + a per-source Referer); hotlinking source images
  directly will usually 403.

### XCOMIX decoupled engine — local dev (database/ + backend/ + frontend/)
- **MariaDB** is required and is NOT in the update script (system dependency).
 Start it (no systemd in the container) and create the `xcomix` DB + user, then
 load `database/schema.sql` → `database/app-schema.sql` → `database/seed.sql`.
 The backend reads `backend/.env` (copy from `.env.example`); for local dev set
 `DB_HOST=127.0.0.1`, real `DB_*` creds, `JWT_SECRET`, `ADMIN_TOKEN`, and add the
 dev frontend origin to `FRONTEND_ORIGIN` (e.g. `http://localhost:3100`).
- Run order: backend first (`cd backend && npm start`, serves `:4000`), then the
 frontend (`cd frontend && npx next dev -p 3100`) with
 `NEXT_PUBLIC_API_BASE=http://localhost:4000` in `frontend/.env.local`. Use a
 non-3000 port so it never collides with the root WordPress-frontend dev server.
- Admin dashboard: `http://localhost:4000/admin`, unlock with the `ADMIN_TOKEN`
 value. It shows live stats and can trigger Katana/Buddy imports on demand.
- CORS: the API allow-lists `FRONTEND_ORIGIN` for cross-origin browsers but also
 always allows **same-origin** requests (so the first-party `/admin` page works
 on any deployment) and permits `GET/POST/PATCH/DELETE`.
- Importers + the lazy chapter reader need network egress to mangakatana.com /
 manhwabuddy.com. A chapter with a `source_url` but no `pages` resolves its
 page images on first `GET /api/chapters/:id/pages` hit (cached thereafter).
- `frontend/` and the root app each have their own `.next/` — building one does
 not affect the other, but (as with any Next app) don't run `next build` and
 `next dev` against the same `.next/` simultaneously.

### XCOMIX engine — non-obvious gotchas (database/ + backend/ + frontend/)
- The `frontend/` carries its own `frontend/postcss.config.mjs` (empty plugins).
  Without it, `next dev`/`next build` in `frontend/` walk up and load the repo
  root `postcss.config.mjs` (which needs `tailwindcss`, a root-app-only dep) and
  crash with `Cannot find module 'tailwindcss'`. Do not delete that file.
- Reader/manga pages read their `?id=`/`?slug=` via `useSearchParams()` (wrapped
  in `<Suspense>`), so in-page query-only navigation (e.g. the reader Prev/Next
  buttons) actually re-renders. Reading the query once in a mount `useEffect`
  silently breaks those buttons.
- Frontend-traffic-driven auto importer: every page view pings
  `GET /api/activity/ping` (wired in `frontend/src/lib/auth.jsx`). The backend
  (`backend/lib/activity.js`) debounces it to a "latest" crawl every 15 min and a
  5-page backlog crawl every 60 min, persisting the per-source backlog page
  cursor to `backend/.import-state.json` so it resumes instead of re-fetching.
  Tune with `ACTIVITY_*` env (defaults match 15 min / 60 min / 5 pages); inspect
  via `GET /api/activity/status`.
- The image proxy (`GET /api/proxy/image`) has an in-memory LRU cache; repeat
  hits return `X-Proxy-Cache: HIT` instantly. It is per-process and bounded
  (`PROXY_CACHE_MAX_BYTES`/`_ENTRIES`/`_ITEM`); restarting the backend clears it.
- Importers filter chapter links to the series' own slug, so MangaKatana
  "you may also like" rails no longer leak foreign chapters into a title.

### cPanel / production build (no Vercel)
- `next.config.mjs` sets `output: 'standalone'`. `npm run build` emits
  `.next/standalone/` AND runs `scripts/copy-standalone-assets.mjs` to copy
  `.next/static` and `public/` into the bundle (standalone does not include them).
- Root `server.js` is the cPanel **Application Startup File**: it chdirs into
  `.next/standalone`, honors cPanel's injected `PORT` (defaults 3000), and boots
  the generated server. `npm start` === `node server.js` and requires a build first.
- When verifying the prod bundle locally, static assets are served under
  `/_next/static/...` (not `/.next/...`).
- `npm run dev` and `npm run build` share the same `.next/` directory. Running a
  build while a dev server is up corrupts the dev server's chunks (it starts
  returning 500 `MODULE_NOT_FOUND`). Stop one before running the other, or build
  in a separate checkout.
