# Manga Headless Frontend + WordPress API Theme

This repository now contains only the manga-related headless stack:

- **Next.js App Router frontend** for the public site.
- **WordPress theme package** for the hidden backend/API and scraper engine.

## Architecture

```text
External manga source sites
        ↓
WordPress backend on private subdomain
        ↓ /wp-json/xcomix/v1/*
Next.js frontend on main domain
        ↓ /api/proxy-image?url=...
Vercel edge/cache + native reader UI
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

## Development

```bash
npm install
npm run dev
```

## Deployment

1. Put WordPress on a private subdomain, for example `db.example.com`.
2. Install/activate `wordpress-themes/mangaverse-xcomix-theme.zip`.
3. Point `NEXT_PUBLIC_WORDPRESS_API_URL` and `WORDPRESS_API_URL` to that WordPress origin.
4. Deploy the Next.js app to Vercel on the main domain.
