const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL || "https://www.xcomix.top";

export const dynamic = "force-static";

export default function robots() {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Personal/account areas have no SEO value and need auth.
        disallow: ["/profile", "/library", "/messages", "/notifications", "/login", "/register"],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}
