"use client";

import { useEffect, useState } from "react";
import ReaderView from "../../components/ReaderView";

// Static single page. The chapter id is read from the query string at runtime
// (e.g. /reader/?id=123), so any chapter served by the API works immediately
// with no per-chapter build step.
export default function ReaderPage() {
  const [id, setId] = useState(null);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setId(params.get("id") || "");
  }, []);

  return <ReaderView id={id} />;
}
