/** @type {import('next').NextConfig} */
const nextConfig = {
  // Compile to a fully static site (HTML/CSS/JS in ./out) for cPanel hosting at
  // https://www.xcomix.top — no Node runtime needed on the frontend domain.
  output: "export",
  reactStrictMode: true,
  trailingSlash: true,
  images: {
    // Static export cannot use the Next image optimizer; all artwork is served
    // through the backend proxy at www.a3555bet.com instead.
    unoptimized: true,
  },
};

module.exports = nextConfig;
