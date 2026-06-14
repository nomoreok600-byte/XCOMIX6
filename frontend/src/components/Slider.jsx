"use client";

import MangaCard from "./MangaCard";

// Horizontal, scroll-snapping carousel of manga cards (mobile-friendly).
export default function Slider({ items, empty = "Nothing here yet." }) {
  if (!items || items.length === 0) return <div className="center-state">{empty}</div>;
  return (
    <div className="slider">
      {items.map((m) => (
        <MangaCard key={m.id} manga={m} />
      ))}
    </div>
  );
}
