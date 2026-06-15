"use client";

import Link from "next/link";
import { proxyImage, decodeEntities, timeAgo, isFresh } from "../lib/api";

export default function MangaCard({ manga, showUpdated = false }) {
  const title = decodeEntities(manga.title);
  const updated = manga.last_chapter_at;

  return (
    <Link href={`/manga/?slug=${encodeURIComponent(manga.slug)}`} className="card" title={title}>
      <div className="card-cover">
        <div className="tag-row">
          {manga.is_18_plus && <span className="tag adult">18+</span>}
          <span className="tag type">{manga.type || "Manga"}</span>
          {showUpdated && isFresh(updated) && <span className="tag new-tag">NEW</span>}
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
          <span>{showUpdated && updated ? timeAgo(updated) : manga.status || "—"}</span>
          {manga.latest_chapter != null ? (
            <span className="ch" style={{ color: "var(--crimson)", fontWeight: 800 }}>
              Ch. {manga.latest_chapter}
            </span>
          ) : (
            manga.chapter_count != null && <span>{manga.chapter_count} ch</span>
          )}
        </div>
      </div>
    </Link>
  );
}
