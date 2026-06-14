"use client";

import { useEffect, useState } from "react";
import MangaDetail from "../../components/MangaDetail";

// Static single page. The series slug is read from the query string at runtime
// (e.g. /manga/?slug=some-series), so no per-series build step is ever needed —
// newly imported titles work immediately with no rebuild.
export default function MangaPage() {
  const [slug, setSlug] = useState(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setSlug(params.get("slug") || "");
  }, []);

  return <MangaDetail slug={slug} />;
}
