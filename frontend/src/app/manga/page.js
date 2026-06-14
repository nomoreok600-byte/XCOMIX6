"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import MangaDetail from "../../components/MangaDetail";

// The series slug is read reactively from the query string at runtime
// (e.g. /manga/?slug=some-series), so no per-series build step is ever needed —
// newly imported titles work immediately with no rebuild.
function MangaRoute() {
  const params = useSearchParams();
  const slug = params.get("slug") || "";
  return <MangaDetail slug={slug} />;
}

export default function MangaPage() {
  return (
    <Suspense fallback={<div className="center-state">Loading…</div>}>
      <MangaRoute />
    </Suspense>
  );
}
