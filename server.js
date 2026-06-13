/**
 * Production entry point for cPanel's Node.js Application Manager.
 *
 * `next build` (with `output: 'standalone'` in next.config.mjs) emits a fully
 * self-contained server at .next/standalone/server.js. That generated server
 * reads PORT/HOSTNAME from the environment and starts listening on require.
 *
 * cPanel boots the app by running this single root file ("Application Startup
 * File"), injecting its own PORT. We chdir into the standalone bundle so its
 * relative asset/runtime lookups resolve, default the bind address, then hand
 * off to the generated server.
 *
 * Note: `npm run build` copies .next/static and public/ into the standalone
 * bundle (see scripts/copy-standalone-assets.mjs) so static assets are served.
 */
const path = require('path');
const fs = require('fs');

const standaloneDir = path.join(__dirname, '.next', 'standalone');
const standaloneServer = path.join(standaloneDir, 'server.js');

if (!fs.existsSync(standaloneServer)) {
  console.error(
    '[server.js] .next/standalone/server.js not found. Run "npm run build" first ' +
      '(requires output: "standalone" in next.config.mjs).'
  );
  process.exit(1);
}

process.env.PORT = process.env.PORT || '3000';
process.env.HOSTNAME = process.env.HOSTNAME || '0.0.0.0';
process.env.NODE_ENV = process.env.NODE_ENV || 'production';

process.chdir(standaloneDir);

console.log(
  `> Starting Next.js standalone server for cPanel on ${process.env.HOSTNAME}:${process.env.PORT}`
);

require(standaloneServer);
