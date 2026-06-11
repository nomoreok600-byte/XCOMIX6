"use client";

import Link from "next/link";
import MangaCard from "./MangaCard";
import { proxiedImageUrl } from "@/lib/headless/wordpress";
import { useMangaLibraryState } from "@/hooks/headless/useMangaLibraryState";

export default function MangaInfoClient({ manga }) {
  const { history, isBookmarked, toggleBookmark } = useMangaLibraryState();
  const lastRead = history[manga.id];
  const firstChapter = manga.chapters?.[manga.chapters.length - 1];
  const continueHref = lastRead?.href || (firstChapter ? `/read/${firstChapter.id}` : "#");

  return (
    <main className="min-h-screen bg-[#09090b] pb-20 pt-20 text-white">
      <section className="relative overflow-hidden">
        <img src={proxiedImageUrl(manga.banner || manga.cover)} alt="" className="absolute inset-0 h-[520px] w-full object-cover opacity-30 blur-2xl scale-110" />
        <div className="absolute inset-0 bg-gradient-to-b from-[#09090b]/40 via-[#09090b]/90 to-[#09090b]" />
        <div className="relative z-10 mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 py-10 md:grid-cols-[260px_1fr]">
          <img src={proxiedImageUrl(manga.cover)} alt={manga.title} className="mx-auto aspect-[2/3] w-56 rounded-3xl border border-white/10 object-cover shadow-2xl md:w-full" />
          <div className="flex flex-col justify-end text-center md:text-left">
            <div className="mb-3 flex flex-wrap justify-center gap-2 md:justify-start">
              <span className="rounded-full bg-orange-500 px-3 py-1 text-[11px] font-black uppercase tracking-widest">{manga.type}</span>
              <span className="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold text-gray-200">{manga.status}</span>
              {manga.isAdult && <span className="rounded-full bg-red-600 px-3 py-1 text-[11px] font-black uppercase tracking-widest">18+</span>}
            </div>
            <h1 className="mb-2 text-4xl font-black leading-tight md:text-6xl">{manga.title}</h1>
            {manga.altTitle && <p className="mb-3 text-sm italic text-orange-300">{manga.altTitle}</p>}
            <p className="mb-5 max-w-3xl text-sm leading-6 text-gray-300">{manga.description}</p>
            <div className="mb-6 grid max-w-2xl grid-cols-2 gap-3 sm:grid-cols-4">
              <Stat label="Score" value={manga.score} />
              <Stat label="Chapters" value={manga.chapters?.length || 0} />
              <Stat label="Author" value={manga.author} />
              <Stat label="Status" value={manga.status} />
            </div>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Link href={continueHref} className="rounded-2xl bg-orange-500 px-6 py-4 text-center text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600">
                {lastRead ? `Resume Ch. ${lastRead.chapterNumber}` : "Read First"}
              </Link>
              <button
                onClick={() => toggleBookmark({ mangaId: manga.id, title: manga.title, cover: manga.cover })}
                className={`rounded-2xl border px-6 py-4 text-sm font-black uppercase tracking-widest transition ${
                  isBookmarked(manga.id)
                    ? "border-orange-500 bg-orange-500/10 text-orange-300"
                    : "border-white/10 bg-white/5 text-gray-200 hover:border-orange-500"
                }`}
              >
                {isBookmarked(manga.id) ? "In Library" : "Save Manga"}
              </button>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 lg:grid-cols-[1fr_340px]">
        <div>
          <div className="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
            <h2 className="flex items-center gap-2 text-xl font-black uppercase tracking-tight">
              <span className="h-6 w-1.5 rounded-full bg-orange-500" />
              Chapters
            </h2>
            <span className="text-xs font-bold text-gray-500">{manga.chapters?.length || 0} indexed</span>
          </div>
          <div className="max-h-[650px] overflow-y-auto rounded-3xl border border-white/5 bg-zinc-900">
            {manga.chapters?.map((chapter) => (
              <Link key={chapter.id} href={`/read/${chapter.id}`} className="flex items-center justify-between border-b border-white/5 px-5 py-4 transition last:border-0 hover:bg-white/5">
                <div>
                  <p className="text-sm font-bold">Chapter {chapter.number || "?"}</p>
                  <p className="text-xs text-gray-500">{chapter.title}</p>
                </div>
                <span className="text-[11px] font-bold uppercase tracking-widest text-orange-400">Read</span>
              </Link>
            ))}
          </div>
        </div>
        <aside>
          <div className="rounded-3xl border border-white/5 bg-zinc-900 p-5">
            <h3 className="mb-3 text-sm font-black uppercase tracking-widest text-gray-300">Genres</h3>
            <div className="flex flex-wrap gap-2">
              {manga.genres?.map((genre) => (
                <span key={genre} className="rounded-xl bg-white/5 px-3 py-2 text-xs font-bold text-gray-300">{genre}</span>
              ))}
            </div>
          </div>
        </aside>
      </section>

      {manga.related?.length > 0 && (
        <section className="mx-auto mt-12 max-w-6xl px-4">
          <h2 className="mb-5 flex items-center gap-2 text-xl font-black">
            <span className="h-6 w-1.5 rounded-full bg-orange-500" />
            Similar Series
          </h2>
          <div className="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-6">
            {manga.related.map((item) => <MangaCard key={item.id} manga={item} compact />)}
          </div>
        </section>
      )}
    </main>
  );
}

function Stat({ label, value }) {
  return (
    <div className="rounded-2xl border border-white/5 bg-white/5 p-3">
      <p className="mb-1 text-[10px] font-black uppercase tracking-widest text-gray-500">{label}</p>
      <p className="truncate text-sm font-bold">{value}</p>
    </div>
  );
}
