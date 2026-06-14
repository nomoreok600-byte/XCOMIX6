"use client";

import Link from "next/link";
import { proxyImage, decodeEntities } from "../lib/api";

export default function MangaCard({ manga }) {
  const title = decodeEntities(manga.title);
  const isOngoing = /ongoing|releasing/i.test(manga.status || "");

  return (
    <Link href={`/manga/${manga.slug}/`} className="card" title={title}>
      <div className="card-cover">
        <div className="tag-row">
          {manga.is_18_plus && <span className="tag adult">18+</span>}
          <span className="tag type">{manga.type || "Manga"}</span>
        </div>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src={proxyImage(manga.cover_url)}
          alt={title}
          loading="lazy"
          onError={(e) => {
            e.currentTarget.style.visibility = "hidden";
          }}
        />
      </div>
      <div className="card-body">
        <div className="card-title">{title}</div>
        <div className="card-meta">
          <span className={isOngoing ? "" : ""}>{manga.status || "—"}</span>
          {manga.chapter_count != null && <span>{manga.chapter_count} ch</span>}
        </div>
      </div>
    </Link>
  );
}
