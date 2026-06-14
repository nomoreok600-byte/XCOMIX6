# XCOMIX — Step‑by‑Step Setup Guide

A decoupled manga/webtoon engine in three independent layers:

| Layer | Folder | Runs on | Tech |
| --- | --- | --- | --- |
| Database | `database/` | MySQL server | MySQL/MariaDB |
| Backend API + image proxy | `backend/` | `www.a3555bet.com` (cPanel Node app) | Express + MySQL2 + CORS |
| Static frontend | `frontend/` | `https://www.xcomix.top` (cPanel static) | Next.js `output: 'export'` |

Data flow: importers scrape source sites → MySQL → Express API → static frontend → all
artwork streamed through `…/api/proxy/image` (nothing stored on disk).

---

## 0. Prerequisites

- **Node.js ≥ 18** (`node -v`)
- **MySQL or MariaDB** (local for dev, or cPanel “MySQL Databases”)
- Network egress to the source sites for the importers (ManhwaBuddy / MangaKatana)

---

## 1. Database

```bash
# Create the database + a user (adjust names/passwords)
mysql -u root -p -e "CREATE DATABASE xcomix CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'xcomix'@'localhost' IDENTIFIED BY 'xcomix_pw'; \
  GRANT ALL PRIVILEGES ON xcomix.* TO 'xcomix'@'localhost'; FLUSH PRIVILEGES;"

# Create the tables (catalog first, then the accounts/social schema)
mysql -u xcomix -p xcomix < database/schema.sql
mysql -u xcomix -p xcomix < database/app-schema.sql   # users, library, comments, reviews, …

# (Optional) load the bundled demo rows
mysql -u xcomix -p xcomix < database/seed.sql
```

In **cPanel** instead: create the DB + user under *MySQL® Databases*, then paste
`database/schema.sql` into *phpMyAdmin → SQL*.

Tables: `mangas`, `chapters` (unique per `manga_id`+`chapter_number`), `pages`
(unique per `chapter_id`+`page_number`). `mangas.source_url` and
`chapters.source_url` store the origin URLs used by the importers/reader.

---

## 2. Backend API (`www.a3555bet.com`)

```bash
cd backend
cp .env.example .env          # then edit DB_* + FRONTEND_ORIGIN
npm install
npm start                     # http://localhost:4000  (cPanel injects PORT)
```

Key `.env` values:

```env
PORT=4000
FRONTEND_ORIGIN=https://www.xcomix.top,https://xcomix.top   # add http://localhost:3100 for local dev
DB_HOST=127.0.0.1
DB_USER=xcomix
DB_PASSWORD=xcomix_pw
DB_NAME=xcomix
PROXY_CF_COOKIE=                # optional cf_clearance cookie for gated sources
JWT_SECRET=change-me            # signs user login tokens
ADMIN_TOKEN=change-me           # unlocks the /admin dashboard
AUTO_IMPORT_ENABLED=false       # true = auto-crawl katana+buddy on a timer
```

Endpoints (catalog + accounts/social):

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/health` | DB connectivity check |
| GET | `/api/manga?limit=&offset=&q=&type=&status=` | Paginated catalog |
| GET | `/api/catalog/{genres,browse,popular,recent,completed,random}` | Browse + filters |
| GET | `/api/catalog/manga/:slug` | One series + chapters + genres + rating |
| GET | `/api/chapters/:id/pages` | Page list + prev/next (lazy-resolves pages on first hit) |
| GET | `/api/proxy/image?url=…` | Hardened streaming image proxy |
| POST | `/api/auth/{register,login}` · GET `/api/auth/me` | Accounts (JWT) |
| * | `/api/library/*` · `/api/social/*` · `/api/community/*` | Bookmarks, comments, reviews, DMs, leaderboard |
| GET/POST | `/admin` + `/admin/*` | Admin dashboard + import controls (X-Admin-Token) |

Add the MangaKatana importer alongside the ManhwaBuddy one:

```bash
node scripts/katana-import.mjs --url=https://mangakatana.com/manga/<slug>.<id>
node scripts/katana-import.mjs --latest --limit=15        # crawl /latest (no human)
```

Quick check:

```bash
curl localhost:4000/api/health
curl "localhost:4000/api/manga?limit=3"
```

### Deploy on cPanel (Node.js App Manager)
1. Upload `backend/` (exclude `node_modules`).
2. *Setup Node.js App* → **Application Root** = the backend folder, **Startup File** = `server.js`.
3. Add the env vars in the cPanel UI, click **Run NPM Install**, then **Restart**.

---

## 3. Importers & the chapter reader

Run all of these **from the `backend/` folder** (they read `backend/.env`).

### ManhwaBuddy importer (`xcomix-buddy-engine.php` → JS)
Scrapes manga metadata + chapter lists (forces 18+) and, by default, runs the
chapter reader for the latest chapters.

```bash
# A single series
node scripts/buddy-import.mjs --url=https://manhwabuddy.com/manhwa/<slug>/

# Crawl a category across pages (8 per page, resolve pages for last 3 chapters each)
node scripts/buddy-import.mjs --category=https://manhwabuddy.com/only-18-webtoons \
  --start=1 --end=2 --limit=8 --max-chapters=3

# Shorthand for the default 18+ latest feed
node scripts/buddy-import.mjs --latest --limit=10
# Add --no-read to import metadata + chapter list only.
```

### Chapter reader (resolve page images)
The reader runs three ways:
1. **Inline** with the importer (default, capped by `--max-chapters`).
2. **Lazily** the first time `GET /api/chapters/:id/pages` is hit for a chapter
   that has a `source_url` but no pages yet — it scrapes, caches, and returns them.
3. **In bulk** to backfill everything:

```bash
node scripts/read-chapters.mjs --limit=100      # add --force to re-resolve existing
```

### Demo importer (no scraping)
Bootstraps from an existing XCOMIX REST source instead of scraping:

```bash
node scripts/import-demo-data.mjs --manga=12 --chapters=5 --pages=12
```

---

## 4. Frontend (`https://www.xcomix.top`)

```bash
cd frontend
cp .env.example .env.local     # set NEXT_PUBLIC_API_BASE
npm install
npm run build                  # emits ./out (static site)
npx serve out                  # or any static host; locally try port 8080
```

`.env.local`:

```env
NEXT_PUBLIC_API_BASE=http://localhost:4000     # production: https://www.a3555bet.com
```

> The dynamic routes (`/manga/[slug]`, `/reader/[id]`) are pre-rendered at build
> time via `generateStaticParams`, so **the backend must be reachable during
> `npm run build`**. Re-run the build after importing new content so its pages
> are emitted. The homepage fetches `/api/manga` at runtime, so it always
> reflects the live catalog.

### Deploy on cPanel (static)
1. `npm run build` (with `NEXT_PUBLIC_API_BASE` pointing at the API domain).
2. Upload the contents of `out/` to the `xcomix.top` document root (`public_html`).
   No Node runtime is required on the frontend domain.

---

## 5. End-to-end smoke test

```bash
# 1. DB up + schema/seed loaded
# 2. backend running on :4000
# 3. import one series
cd backend && node scripts/buddy-import.mjs --url=https://manhwabuddy.com/manhwa/<slug>/ --max-chapters=2
# 4. confirm it surfaces through the API
curl "localhost:4000/api/manga?q=<word>"
curl "localhost:4000/api/chapters/<id>/pages"   # lazily resolves pages
# 5. build + serve the frontend, browse /, open the series, open the reader
```
