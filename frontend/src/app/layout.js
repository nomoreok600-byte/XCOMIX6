import "./globals.css";
import { AuthProvider } from "../lib/auth";

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "https://www.xcomix.top";

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
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(JSON_LD) }} />
      </head>
      <body>
        <AuthProvider>{children}</AuthProvider>
      </body>
    </html>
  );
}
