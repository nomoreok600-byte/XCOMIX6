"use client";

import { useSiteConfig } from "../lib/siteConfig";

// Renders the admin-configured HTML for an ad slot (header/footer/manga/chapter).
// The backend only ships HTML for slots that are toggled on, so an empty/absent
// value renders nothing.
export default function AdSlot({ slot, className = "" }) {
  const config = useSiteConfig();
  const html = config?.ads?.[slot];
  if (!html || !html.trim()) return null;
  return (
    <div
      className={`ad-slot ad-slot-${slot} ${className}`}
      data-ad-slot={slot}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
