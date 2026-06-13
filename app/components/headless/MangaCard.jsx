import Link from "next/link";
import { proxiedImageUrl } from "@/lib/headless/wordpress";

export default function MangaCard({ manga, compact = false }) {
  const latest = manga.latestChapter;
  return (
    <Link href={`/manga/${manga.id}`} className="group block">
      <div className="relative mb-2 aspect-[2/3] overflow-hidden rounded-2xl border border-white/5 bg-zinc-900 shadow-lg">
        <img
          src={proxiedImageUrl(manga.cover)}
          alt={manga.title}
          className="h-full w-full object-cover transition duration-500 group-hover:scale-110"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/10 to-transparent opacity-75" />
        <div className="absolute left-2 top-2 flex flex-col gap-1">
          <span className="rounded-md border border-white/10 bg-black/70 px-2 py-1 text-[9px] font-black uppercase tracking-widest text-white backdrop-blur">
            {manga.type}
          </span>
          {manga.isAdult && (
            <span className="rounded-md bg-red-600/90 px-2 py-1 text-[9px] font-black uppercase tracking-widest text-white">18+</span>
          )}
        </div>
        <div className="absolute right-2 top-2 rounded-full bg-orange-500 px-2 py-1 text-[10px] font-black text-white">
          {manga.score}
        </div>
        <div className="absolute bottom-2 left-2 right-2 flex items-center justify-between gap-2 text-[10px] font-bold text-gray-200">
          <span className="truncate">{latest?.number ? `Ch. ${latest.number}` : "Start"}</span>
          <span className="flex items-center gap-1 uppercase tracking-widest">
            <span className={`h-1.5 w-1.5 rounded-full ${manga.status?.toLowerCase().includes("complete") ? "bg-blue-500" : "bg-green-500"}`} />
            {compact ? "" : manga.status}
          </span>
        </div>
      </div>
      <h3 className="line-clamp-2 text-sm font-bold leading-tight text-gray-100 transition group-hover:text-orange-400">{manga.title}</h3>
      {!compact && manga.genres?.length > 0 && (
        <p className="mt-0.5 truncate text-[11px] text-gray-500">{manga.genres.slice(0, 2).join(", ")}</p>
      )}
    </Link>
  );
}
