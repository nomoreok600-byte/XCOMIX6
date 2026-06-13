# AGENTS.md

## Project

Manga Headless Frontend: a **Next.js 15 (App Router)** site that reads from a
headless **WordPress XCOMIX REST API**. See `README.md` for routes, architecture,
the WordPress theme package, and full cPanel deployment steps.

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

### cPanel / production build (no Vercel)
- `next.config.mjs` sets `output: 'standalone'`. `npm run build` emits
  `.next/standalone/` AND runs `scripts/copy-standalone-assets.mjs` to copy
  `.next/static` and `public/` into the bundle (standalone does not include them).
- Root `server.js` is the cPanel **Application Startup File**: it chdirs into
  `.next/standalone`, honors cPanel's injected `PORT` (defaults 3000), and boots
  the generated server. `npm start` === `node server.js` and requires a build first.
- When verifying the prod bundle locally, static assets are served under
  `/_next/static/...` (not `/.next/...`).
