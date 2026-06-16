import "./globals.css";
import { AuthProvider } from "../lib/auth";
import { SiteConfigProvider } from "../lib/siteConfig";
import Announcement from "../components/Announcement";
import AdSlot from "../components/AdSlot";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "https://www.xcomix.top";
const API_ORIGIN = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_API_BASE || "https://www.a3555bet.com").origin;
  } catch {
    return "";
  }
})();

export const metadata = {
  metadataBase: new URL(SITE_URL),
  title: {
    default: "XCOMIX — Read Manga, Manhwa & Manhua Online Free",
    template: "%s · XCOMIX",
  },
  description:
    "Read manga, manhwa and manhua online for free on XCOMIX. Thousands of titles updated daily with the latest chapters — a fast, mobile-first reader with bookmarks, reviews, comments and a community.",
  keywords: [
    "manga", "manhwa", "manhua", "read manga online", "read manga online free",
    "read manhwa online", "read manhua online", "webtoon", "manga reader",
    "free manga", "latest manga chapters", "manga site", "XCOMIX",
  ],
  applicationName: "XCOMIX",
  category: "entertainment",
  manifest: "/manifest.webmanifest",
  icons: {
    icon: [
      { url: "/favicon.png", sizes: "48x48", type: "image/png" },
      { url: "/icon-192.png", sizes: "192x192", type: "image/png" },
    ],
    apple: [{ url: "/icon-192.png" }],
  },
  alternates: { canonical: "/" },
  openGraph: {
    type: "website",
    siteName: "XCOMIX",
    title: "XCOMIX — Read Manga, Manhwa & Manhua Online Free",
    description:
      "A fast, mobile-first manga, manhwa and manhua reader with bookmarks, reviews, comments and a community.",
    url: SITE_URL,
  },
  twitter: {
    card: "summary_large_image",
    title: "XCOMIX — Read Manga, Manhwa & Manhua Online Free",
    description: "A fast, mobile-first manga/manhwa/manhua reader with a community.",
  },
  robots: { index: true, follow: true },
};

export const viewport = {
  themeColor: "#ff2a5f",
  width: "device-width",
  initialScale: 1,
  maximumScale: 5,
};

// Applied synchronously in <head> before first paint so a saved palette never
// flashes the default red on reload (fixes the color flicker).
const THEME_BOOTSTRAP = `
(function(){try{
  var M={crimson:['#ff2a5f','#ff0044','rgba(255,42,95,0.45)'],aqua:['#14b8a6','#0d9488','rgba(20,184,166,0.4)'],cyber:['#06b6d4','#3b82f6','rgba(6,182,212,0.45)'],violet:['#a855f7','#7c3aed','rgba(168,85,247,0.45)'],toxic:['#22c55e','#16a34a','rgba(34,197,94,0.45)'],amber:['#ffb020','#f97316','rgba(255,176,32,0.45)'],neon:['#ff2a5f','#ff0044','rgba(255,42,95,0.45)']};
  var t=localStorage.getItem('xcomix_theme'); var c=M[t]||M.crimson; var r=document.documentElement.style;
  r.setProperty('--crimson',c[0]); r.setProperty('--crimson-2',c[1]); r.setProperty('--glow','0 0 22px '+c[2]); r.setProperty('--border-strong',c[0]);
}catch(e){}})();
`;

// Register the service worker for installable PWA / offline shell.
const SW_REGISTER = `
if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}
`;

const JSON_LD = [
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: "XCOMIX",
    alternateName: "XCOMIX — Read Manga, Manhwa & Manhua Online",
    url: SITE_URL,
    description:
      "Read manga, manhwa and manhua online for free. Thousands of titles updated daily.",
    inLanguage: "en",
    potentialAction: {
      "@type": "SearchAction",
      target: `${SITE_URL}/browse/?q={search_term_string}`,
      "query-input": "required name=search_term_string",
    },
  },
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: "XCOMIX",
    url: SITE_URL,
    logo: `${SITE_URL}/icon-512.png`,
  },
];

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <head>
        {/* Pre-apply the saved theme before paint (no color flicker). */}
        <script dangerouslySetInnerHTML={{ __html: THEME_BOOTSTRAP }} />
        {/* Warm up the connection to the image proxy / API origin so the first
            covers and pages start downloading sooner. */}
        {API_ORIGIN && <link rel="preconnect" href={API_ORIGIN} crossOrigin="" />}
        {API_ORIGIN && <link rel="dns-prefetch" href={API_ORIGIN} />}
        <link rel="manifest" href="/manifest.webmanifest" />
        <link rel="apple-touch-icon" href="/icon-192.png" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
        <meta name="apple-mobile-web-app-title" content="XCOMIX" />
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(JSON_LD) }} />
        <script dangerouslySetInnerHTML={{ __html: SW_REGISTER }} />
      </head>
      <body>
        <AuthProvider>
          <SiteConfigProvider>
            <Announcement />
            <AdSlot slot="header" className="ad-slot-top" />
            {children}
          </SiteConfigProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
