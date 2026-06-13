# Manga Headless Frontend + WordPress API Theme

This repository now contains only the manga-related headless stack:

- **Next.js App Router frontend** for the public site.
- **WordPress theme package** for the hidden backend/API and scraper engine.

## Architecture

```text
External manga source sites
        ↓
WordPress backend on private subdomain (cPanel Apache/PHP-FPM)
        ↓ /wp-json/xcomix/v1/*
Next.js standalone server on main domain (cPanel Node.js App, ports 3000+)
        ↓ /api/proxy-image?url=...
Native scrolling reader UI
```

## Frontend routes

- `/` redirects to `/manga`
- `/manga` renders the headless home feed
- `/manga/[id]` renders manga details and chapter list
- `/read/[chapterId]` renders the native-scrolling reader
- `/api/proxy-image?url=...` streams remote image URLs with desktop headers
- `/api/headless/sync` is an auth-adapter-ready state sync bridge

## WordPress API theme

Source:

```text
wordpress-themes/mangaverse-xcomix-theme/
```

Installable zip:

```text
wordpress-themes/mangaverse-xcomix-theme.zip
```

The theme exposes:

- `GET /wp-json/xcomix/v1/home`
- `GET /wp-json/xcomix/v1/manga/:id`
- `GET /wp-json/xcomix/v1/chapter/:id`
- `POST /wp-json/xcomix/v1/user-state`

It also keeps the XCOMIX scraper, image extraction, proxy helpers, and WordPress admin tools.

## Environment

```env
NEXT_PUBLIC_WORDPRESS_API_URL=https://db.example.com
WORDPRESS_API_URL=https://db.example.com
WORDPRESS_API_PREFIX=/wp-json/xcomix/v1
WORDPRESS_SYNC_TOKEN=
NODE_ENV=production
```

> `WORDPRESS_API_URL` / `NEXT_PUBLIC_WORDPRESS_API_URL` must be the WordPress
> **origin root** (e.g. `https://db.example.com`). The app appends
> `WORDPRESS_API_PREFIX` (`/wp-json/xcomix/v1`) itself, so do not include a
> trailing path such as `/home/`.

## Development

```bash
npm install
npm run dev
```

The dev server runs at `http://localhost:3000` and redirects `/` to `/manga`.
If the WordPress API is unreachable, the frontend renders a built-in demo
payload so the UI still works.

## Production build (cPanel Node.js, no Vercel)

This app is configured to run on **cPanel's Node.js Application Manager** — no
Vercel required.

- `next.config.mjs` sets `output: 'standalone'`, so `next build` emits a
  self-contained server at `.next/standalone/`.
- `npm run build` also copies `.next/static` and `public/` into the standalone
  bundle (`scripts/copy-standalone-assets.mjs`) so assets are served correctly.
- The root `server.js` is the cPanel **Application Startup File**: it chdirs
  into `.next/standalone`, honors cPanel's injected `PORT`, and boots the
  generated server. `npm start` runs `node server.js`.

```bash
npm install
npm run build   # generates .next/standalone with static assets copied in
npm start       # node server.js  (PORT defaults to 3000)
```

### Deploy to cPanel

1. Put WordPress on a private subdomain (e.g. `db.example.com`) via cPanel
   Apache/PHP-FPM, then install/activate
   `wordpress-themes/mangaverse-xcomix-theme.zip`.
2. Zip the project (exclude `node_modules` and `.next`) and upload it to a
   directory in your cPanel account, e.g. `/home/<user>/manga-frontend`.
3. cPanel → **Setup Node.js App** → **Create Application**:
   - **Application URL**: your main domain.
   - **Application Root**: `manga-frontend`.
   - **Application Startup File**: `server.js`.
4. Add Environment Variables in the cPanel UI (`NEXT_PUBLIC_WORDPRESS_API_URL`,
   `WORDPRESS_API_URL`, `WORDPRESS_API_PREFIX`, `WORDPRESS_SYNC_TOKEN`), then
   click **Run NPM Install**.
5. From the app directory run `npm run build` (cPanel terminal or a build step),
   then **Restart** the application. cPanel proxies your domain to the Node
   process on its assigned port.
