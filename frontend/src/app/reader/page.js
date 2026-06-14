"use client";

import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import ReaderView from "../../components/ReaderView";

// The chapter id is read reactively from the query string (e.g. /reader/?id=123)
// via useSearchParams, so the in-reader Prev/Next buttons (which only change the
// query) re-render with the new chapter instead of being ignored. Any chapter
// served by the API works immediately with no per-chapter build step.
function ReaderRoute() {
  const params = useSearchParams();
  const id = params.get("id") || "";
  return <ReaderView id={id} />;
}

export default function ReaderPage() {
  return (
    <Suspense fallback={<div className="center-state">Streaming pages…</div>}>
      <ReaderRoute />
    </Suspense>
  );
}
