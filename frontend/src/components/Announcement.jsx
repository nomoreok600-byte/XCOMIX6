"use client";

import { useEffect, useMemo, useState } from "react";
import { useSiteConfig } from "../lib/siteConfig";
import Icon from "./Icon";

const DISMISS_KEY = "xcomix_ann_dismissed";

// Site announcements shown as a closable, sliding pop banner (NOT a top header
// bar). Supports multiple announcements that auto-rotate ("slide"); the visitor
// can dismiss them and they stay dismissed until the admin changes the set.
export default function Announcement() {
  const config = useSiteConfig();
  const ann = config?.announcement;
  // New `items` array, falling back to the legacy single `text`.
  const items = useMemo(() => {
    if (ann?.items && ann.items.length) return ann.items;
    return ann?.text ? [ann.text] : [];
  }, [ann?.items, ann?.text]);
  const enabled = Boolean(ann?.enabled && items.length);
  const key = useMemo(() => items.join("||"), [items]);

  const [dismissed, setDismissed] = useState(true); // hidden until we confirm
  const [idx, setIdx] = useState(0);

  useEffect(() => {
    if (!enabled) return;
    try {
      setDismissed(window.localStorage.getItem(DISMISS_KEY) === key);
    } catch {
      setDismissed(false);
    }
    setIdx(0);
  }, [enabled, key]);

  // Auto-rotate ("slide") through multiple announcements.
  useEffect(() => {
    if (!enabled || dismissed || items.length < 2) return undefined;
    const t = setInterval(() => setIdx((i) => (i + 1) % items.length), 5000);
    return () => clearInterval(t);
  }, [enabled, dismissed, items.length]);

  if (!enabled || dismissed) return null;

  const dismiss = () => {
    setDismissed(true);
    try {
      window.localStorage.setItem(DISMISS_KEY, key);
    } catch {
      /* ignore */
    }
  };

  return (
    <div className="announce-pop" role="status" aria-live="polite">
      <div className="announce-pop-body">
        <Icon name="bell" size={16} className="announce-pop-icon" />
        <div className="announce-pop-track">
          {/* key={idx} remounts the line so the slide animation replays. */}
          <p key={idx} className="announce-pop-text">{items[idx]}</p>
        </div>
        <button className="announce-pop-close" onClick={dismiss} aria-label="Close announcement">
          <Icon name="close" size={15} />
        </button>
      </div>
      {items.length > 1 && (
        <div className="announce-pop-dots">
          {items.map((_, i) => (
            <button
              key={i}
              className={`announce-dot${i === idx ? " active" : ""}`}
              onClick={() => setIdx(i)}
              aria-label={`Announcement ${i + 1}`}
            />
          ))}
        </div>
      )}
    </div>
  );
}
