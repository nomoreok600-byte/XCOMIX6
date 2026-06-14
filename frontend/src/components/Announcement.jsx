"use client";

import { useEffect, useState } from "react";
import { useSiteConfig } from "../lib/siteConfig";
import Icon from "./Icon";

// Site-wide announcement banner. Shows only when an admin enables it, and the
// visitor can dismiss it for the session (per announcement text).
export default function Announcement() {
  const config = useSiteConfig();
  const ann = config?.announcement;
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    if (!ann?.enabled || !ann.text) return;
    const key = `xcomix_ann_dismissed`;
    if (typeof window !== "undefined" && window.sessionStorage.getItem(key) === ann.text) {
      setDismissed(true);
    }
  }, [ann?.enabled, ann?.text]);

  if (!ann?.enabled || !ann.text || dismissed) return null;

  const dismiss = () => {
    setDismissed(true);
    if (typeof window !== "undefined") window.sessionStorage.setItem("xcomix_ann_dismissed", ann.text);
  };

  return (
    <div className="announcement" role="status">
      <span className="announcement-text">{ann.text}</span>
      <button className="announcement-close" onClick={dismiss} aria-label="Dismiss announcement">
        <Icon name="close" size={16} />
      </button>
    </div>
  );
}
