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
    "Read thousands of manga, manhwa and manhua online for free on XCOMIX — a fast, mobile-first reader with bookmarks, reviews, comments and a community.",
  keywords: ["manga", "manhwa", "manhua", "read manga online", "webtoon", "manga reader", "XCOMIX"],
  applicationName: "XCOMIX",
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
  themeColor: "#0d9488",
  width: "device-width",
  initialScale: 1,
};

const JSON_LD = {
  "@context": "https://schema.org",
  "@type": "WebSite",
  name: "XCOMIX",
  url: SITE_URL,
  potentialAction: {
    "@type": "SearchAction",
    target: `${SITE_URL}/browse/?q={search_term_string}`,
    "query-input": "required name=search_term_string",
  },
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <head>
        {/* Warm up the connection to the image proxy / API origin so the first
            covers and pages start downloading sooner. */}
        {API_ORIGIN && <link rel="preconnect" href={API_ORIGIN} crossOrigin="" />}
        {API_ORIGIN && <link rel="dns-prefetch" href={API_ORIGIN} />}
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(JSON_LD) }} />
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
