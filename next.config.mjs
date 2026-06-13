import nextPWA from 'next-pwa';

/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Bundle a self-contained Node.js server into .next/standalone so the app can
  // run on cPanel's Node.js Application Manager without Vercel (see README).
  output: 'standalone',
};

const withPWA = nextPWA({
  dest: 'public',
  register: true,
  skipWaiting: true,
});

export default withPWA(nextConfig);
