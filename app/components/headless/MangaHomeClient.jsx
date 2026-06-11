"use client";

import { useState } from "react";
import Link from "next/link";
import MangaCard from "./MangaCard";
import { proxiedImageUrl } from "@/lib/headless/wordpress";

const tabs = [
  ["day", "Day"],
  ["week", "Week"],
  ["month", "Month"],
];

export default function MangaHomeClient({ payload }) {
  const [activeTab, setActiveTab] = useState("day");
  const hero = payload.hero?.[0] || payload.hot?.[0];
  const trending = payload.trending?.[activeTab] || [];

  return (
    <main className="min-h-screen bg-[#09090b] pb-16 pt-20 text-white">
      <section className="mx-auto max-w-7xl px-4">
        <div className="relative overflow-hidden rounded-[2rem] border border-white/5 bg-zinc-950 shadow-2xl">
          {hero && (
            <>
              <img src={proxiedImageUrl(hero.banner || hero.cover)} alt="" className="absolute inset-0 h-full w-full object-cover opacity-45 blur-sm scale-105" />
              <div className="absolute inset-0 bg-gradient-to-r from-[#09090b] via-[#09090b]/80 to-transparent" />
              <div className="relative z-10 grid min-h-[430px] grid-cols-1 gap-8 p-6 md:grid-cols-[1fr_240px] md:p-10">
                <div className="flex max-w-3xl flex-col justify-end">
                  <div className="mb-4 flex flex-wrap gap-2">
                    <span className="rounded-full bg-orange-500 px-3 py-1 text-[11px] font-black uppercase tracking-widest">Headless</span>
                    {hero.genres?.slice(0, 4).map((genre) => (
                      <span key={genre} className="rounded-full bg-white/10 px-3 py-1 text-[11px] text-gray-200 backdrop-blur">
                        {genre}
                      </span>
                    ))}
                  </div>
                  <h1 className="mb-3 text-4xl font-black leading-tight md:text-6xl">{hero.title}</h1>
                  <p className="mb-6 line-clamp-3 text-sm leading-6 text-gray-300 md:text-base">{hero.description}</p>
                  <div className="flex flex-wrap gap-3">
                    <Link href={`/manga/${hero.id}`} className="rounded-full bg-orange-500 px-6 py-3 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600">
                      Start Reading
                    </Link>
                    <Link href="#hot" className="rounded-full bg-white/10 px-6 py-3 text-sm font-bold text-white transition hover:bg-white/20">
                      Browse Updates
                    </Link>
                  </div>
                </div>
                <div className="hidden items-end md:flex">
                  <img src={proxiedImageUrl(hero.cover)} alt={hero.title} className="aspect-[2/3] w-full rounded-3xl border border-white/10 object-cover shadow-2xl" />
                </div>
              </div>
            </>
          )}
        </div>
      </section>

      <section id="hot" className="mx-auto mt-12 max-w-7xl px-4">
        <SectionHeader title="Hot Updates" subtitle="Freshly indexed from your WordPress scraper API" />
        <div className="grid grid-cols-3 gap-4 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8">
          {payload.hot?.map((manga) => <MangaCard key={manga.id} manga={manga} />)}
        </div>
      </section>

      <section className="mx-auto mt-12 max-w-7xl px-4">
        <div className="mb-5 flex flex-wrap items-end justify-between gap-4">
          <SectionHeader title="Trending Ranked" subtitle="Day, week, and month ranking sets" compact />
          <div className="flex rounded-full border border-white/5 bg-zinc-900 p-1">
            {tabs.map(([key, label]) => (
              <button
                key={key}
                onClick={() => setActiveTab(key)}
                className={`rounded-full px-4 py-2 text-xs font-black uppercase tracking-widest transition ${
                  activeTab === key ? "bg-orange-500 text-white" : "text-gray-400 hover:text-white"
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
          {trending.map((manga, index) => (
            <Link key={manga.id} href={`/manga/${manga.id}`} className="flex items-center gap-3 rounded-2xl border border-white/5 bg-zinc-900 p-3 transition hover:border-orange-500/50 hover:bg-zinc-800">
              <span className="w-7 text-center text-xl font-black text-orange-400">{index + 1}</span>
              <img src={proxiedImageUrl(manga.cover)} alt="" className="h-16 w-12 rounded-lg object-cover" />
              <div className="min-w-0">
                <p className="truncate text-sm font-bold">{manga.title}</p>
                <p className="text-[11px] text-gray-500">{manga.type}</p>
              </div>
            </Link>
          ))}
        </div>
      </section>

      <section className="mx-auto mt-12 grid max-w-7xl grid-cols-1 gap-6 px-4 lg:grid-cols-[1fr_360px]">
        <div>
          <SectionHeader title="Recent Chapters" subtitle="Global chapter feed" />
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
            {payload.recentChapters?.map((chapter) => (
              <Link key={chapter.id} href={`/read/${chapter.id}`} className="flex items-center gap-3 rounded-2xl border border-white/5 bg-zinc-900 p-3 transition hover:border-orange-500/50 hover:bg-zinc-800">
                <img src={proxiedImageUrl(chapter.image)} alt="" className="h-16 w-12 rounded-xl object-cover" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-bold">{chapter.title}</p>
                  <p className="text-[11px] text-gray-500">Chapter {chapter.number || "?"}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>
        <aside className="rounded-3xl border border-white/5 bg-zinc-900 p-5">
          <SectionHeader title="Live Feed" subtitle="Commentary from WordPress" compact />
          <div className="mt-4 space-y-3">
            {payload.liveFeed?.map((item) => (
              <div key={item.id} className="rounded-2xl bg-black/30 p-3">
                <p className="text-xs font-bold text-orange-400">{item.author}</p>
                <p className="mt-1 text-sm text-gray-300">{item.text}</p>
                {item.targetTitle && <p className="mt-2 text-[11px] text-gray-500">{item.targetTitle}</p>}
              </div>
            ))}
          </div>
        </aside>
      </section>
    </main>
  );
}

function SectionHeader({ title, subtitle, compact = false }) {
  return (
    <div className={compact ? "" : "mb-5"}>
      <h2 className="flex items-center gap-2 text-xl font-black md:text-2xl">
        <span className="h-6 w-1.5 rounded-full bg-orange-500" />
        {title}
      </h2>
      <p className="mt-1 text-xs text-gray-500">{subtitle}</p>
    </div>
  );
}
