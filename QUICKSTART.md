# XCOMIX — Go Live in 3 Uploads (cPanel)

Three ready-to-upload files (download them from the chat/artifacts panel):

| File | What it is | Where it goes |
| --- | --- | --- |
| `xcomix-database.sql` | Tables + demo data | phpMyAdmin import |
| `xcomix-backend.zip` | API engine (Node) | `www.a3555bet.com` Node app |
| `xcomix-frontend.zip` | The website (static) | `xcomix.top` `public_html` |

> The frontend is pre-built to talk to `https://www.a3555bet.com`. Keep the API on
> that domain and there is nothing to edit or rebuild.

---

## 1) Database (2 min)
1. cPanel → **MySQL® Databases** → create a database (e.g. `xcomix`) and a user, then
   **add the user to the database** with *All Privileges*. Note the final names —
   cPanel prefixes them, e.g. `cpuser_xcomix`.
2. cPanel → **phpMyAdmin** → click your database → **Import** → choose
   `xcomix-database.sql` → **Go**.

## 2) Backend API (5 min)
1. cPanel → **File Manager** → make a folder like `xcomix-api` in your home dir →
   upload `xcomix-backend.zip` there → **Extract**.
2. cPanel → **Setup Node.js App** → **Create Application**:
   - Node version **18+**
   - **Application root**: `xcomix-api`
   - **Application URL**: your API domain (`www.a3555bet.com`)
   - **Application startup file**: `server.js`
3. In that screen, add **Environment Variables**:
   - `DB_HOST` = `localhost`
   - `DB_NAME` = your DB name (e.g. `cpuser_xcomix`)
   - `DB_USER` = your DB user (e.g. `cpuser_xcomix`)
   - `DB_PASSWORD` = the password you set
   - `FRONTEND_ORIGIN` = `https://www.xcomix.top,https://xcomix.top`
4. Click **Run NPM Install**, then **Start/Restart**.
5. Test: open `https://www.a3555bet.com/api/health` → should say `{"status":"ok"}`.

## 3) Frontend website (2 min)
1. cPanel → **File Manager** → open the `xcomix.top` document root (`public_html`).
2. Upload `xcomix-frontend.zip` → **Extract**. Done — visit `https://www.xcomix.top`.

---

## Add manga (optional, anytime — no rebuild needed)
In **Setup Node.js App**, open the app's terminal (or cPanel Terminal in the app
folder) and run:

```bash
# newest 18+ titles
node scripts/buddy-import.mjs --latest --limit=10

# one specific series
node scripts/buddy-import.mjs --url=https://manhwabuddy.com/manhwa/<slug>/
```

New titles appear on the site immediately — the frontend reads them live, so you
never rebuild or re-upload. (Pages for a chapter are fetched the first time
someone opens it; to pre-warm them: `node scripts/read-chapters.mjs --limit=100`.)

## If something looks empty
- `…/api/health` not OK → re-check the 4 `DB_*` env vars, then Restart the app.
- Site loads but no covers/images → make sure `FRONTEND_ORIGIN` exactly matches
  your site URL, and that the API domain is `https://www.a3555bet.com`.

Full details for advanced setup live in `SETUP.md`.
