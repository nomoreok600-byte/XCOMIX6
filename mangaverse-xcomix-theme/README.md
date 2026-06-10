# MangaVerse XCOMIX Theme

Merged WordPress theme built from:

- **MangaVerse Pro** for the visual design, navigation, profile, messages, leaderboard, SEO, ads, and admin settings.
- **XCOMIX** for the working MangaKatana scraper/admin engine, MangaKatana chapter image extraction, ManhwaBuddy/Mgeko reader routing, reading history compatibility, and remote image proxying.

## Install

Copy the `mangaverse-xcomix-theme` folder into `wp-content/themes/`, then activate **MangaVerse XCOMIX** in WordPress.

## Included merge points

- `scraper.php` is the XCOMIX MangaKatana engine and writes both XCOMIX metadata (`_manga_*`, `_chapter_number`, `_katana_url`) and MangaVerse metadata (`_mv_*`) for template compatibility.
- `single-chapter.php` routes chapters by source:
  - `_katana_url` -> `single-chapter2.php`
  - `_mgeko_url` -> `single-chapter3.php`
  - `_buddy_url` -> bundled ManhwaBuddy reader fallback
- `inc/xcomix-integration.php` provides:
  - XCOMIX/MangaVerse metadata compatibility helpers
  - working image proxy endpoint via `?mv_proxy_image=...`
  - XCOMIX AJAX compatibility for bookmarks, history, reading plans, and Mgeko image cache saves
  - chapter source URL fields in the WordPress chapter editor

## Admin

The XCOMIX scraper appears in the WordPress admin as **Katana Engine**. MangaVerse settings remain under **MangaVerse**.
