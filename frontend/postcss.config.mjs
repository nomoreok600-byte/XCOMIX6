// The XCOMIX static frontend uses hand-written CSS (no Tailwind). This empty
// PostCSS config stops postcss-load-config from walking up to the repo-root
// config (which requires `tailwindcss`), so `next dev`/`next build` run here
// standalone without the root app's build dependencies.
/** @type {import('postcss-load-config').Config} */
const config = {
  plugins: {},
};

export default config;
