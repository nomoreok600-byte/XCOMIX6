"use client";

import MangaCard from "./MangaCard";
import { GridSkeleton } from "./Skeletons";

export default function MangaGrid({ items, loading, empty = "Nothing here yet.", showUpdated = false }) {
  if (loading) return <GridSkeleton count={12} />;
  if (!items || items.length === 0) return <div className="center-state">{empty}</div>;
  return (
    <div className="grid">
      {items.map((m) => (
        <MangaCard key={m.id} manga={m} showUpdated={showUpdated} />
      ))}
    </div>
  );
}
