import "./globals.css";

export const metadata = {
  title: "XCOMIX — Neon Manga & Webtoon Engine",
  description:
    "A futuristic, dark-mode-first manga and webtoon reader. Glassmorphism UI, cascade webtoon viewer, and a hyper-fast headless engine.",
};

export const viewport = {
  themeColor: "#08080c",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
