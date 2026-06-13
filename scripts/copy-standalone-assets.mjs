/**
 * Next.js `output: 'standalone'` emits .next/standalone with the server and a
 * minimal node_modules, but intentionally does NOT copy the static assets or
 * the public/ folder. For a self-hosted target like cPanel (where the root
 * server.js runs the standalone bundle directly), those assets must sit next to
 * the generated server. This script copies them after `next build`.
 */
import { cp, access } from 'node:fs/promises';
import { constants } from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const standaloneDir = path.join(root, '.next', 'standalone');

async function exists(p) {
  try {
    await access(p, constants.F_OK);
    return true;
  } catch {
    return false;
  }
}

async function copyDir(from, to, label) {
  if (!(await exists(from))) {
    console.warn(`[copy-standalone-assets] skip ${label}: ${from} does not exist`);
    return;
  }
  await cp(from, to, { recursive: true });
  console.log(`[copy-standalone-assets] copied ${label} -> ${to}`);
}

async function main() {
  if (!(await exists(standaloneDir))) {
    console.error(
      '[copy-standalone-assets] .next/standalone not found. Ensure next.config.mjs sets output: "standalone".'
    );
    process.exit(1);
  }

  await copyDir(
    path.join(root, '.next', 'static'),
    path.join(standaloneDir, '.next', 'static'),
    '.next/static'
  );
  await copyDir(path.join(root, 'public'), path.join(standaloneDir, 'public'), 'public');

  console.log('[copy-standalone-assets] done. Run "npm start" (node server.js) to serve the bundle.');
}

main().catch((err) => {
  console.error('[copy-standalone-assets] failed:', err);
  process.exit(1);
});
