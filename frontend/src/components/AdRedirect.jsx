"use client";

import { useEffect } from "react";
import { useSiteConfig } from "../lib/siteConfig";

const LAST_KEY = "xcomix_ad_last";

/**
 * Click-triggered redirect ("pop-under") ads — the no-banner monetization model.
 *
 * When enabled in /admin, an eligible click anywhere on the site opens the
 * configured ad URL in a NEW tab while the original click still does its normal
 * thing (navigation/button continue to work). A per-visitor cooldown (minutes,
 * stored in localStorage) means a user only ever triggers one ad redirect per
 * cooldown window, so it never gets spammy.
 *
 * Renders nothing — it only installs a capture-phase click listener.
 */
export default function AdRedirect() {
  const { redirect } = useSiteConfig();
  const enabled = Boolean(redirect?.enabled && redirect?.url);
  const url = redirect?.url || "";
  const cooldownMs = Math.max(1, Number(redirect?.cooldownMin) || 2) * 60 * 1000;

  useEffect(() => {
    if (!enabled || typeof window === "undefined") return undefined;

    const onClick = (e) => {
      // Only react to genuine user clicks (ignore synthetic/programmatic ones).
      if (!e.isTrusted || e.button !== 0) return;

      // Trigger on meaningful interactions: links and buttons. This keeps the
      // ad tied to intentional taps rather than every stray click on the page.
      const target = e.target?.closest?.("a, button, [role='button']");
      if (!target) return;

      // Respect explicit opt-outs and links that already open elsewhere.
      if (target.closest("[data-no-ad]")) return;
      if (target.tagName === "A" && target.target && target.target !== "_self") return;

      // Cooldown: at most one redirect per window per visitor.
      let last = 0;
      try {
        last = Number(window.localStorage.getItem(LAST_KEY)) || 0;
      } catch {
        last = 0;
      }
      if (Date.now() - last < cooldownMs) return;

      try {
        window.localStorage.setItem(LAST_KEY, String(Date.now()));
      } catch {
        /* ignore quota/availability errors */
      }

      // Open the ad in a background tab so the main site keeps working. The
      // open() call is inside a trusted user gesture, so it isn't blocked.
      const win = window.open(url, "_blank", "noopener");
      if (win) {
        try {
          win.blur();
          window.focus();
        } catch {
          /* some browsers ignore refocus — harmless */
        }
      }
    };

    // Capture phase so we run alongside the click even if handlers stopPropagation.
    document.addEventListener("click", onClick, true);
    return () => document.removeEventListener("click", onClick, true);
  }, [enabled, url, cooldownMs]);

  return null;
}
