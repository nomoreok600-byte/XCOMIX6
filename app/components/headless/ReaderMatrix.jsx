"use client";

import Link from "next/link";
import { useEffect, useMemo, useRef, useState } from "react";
import { proxiedImageUrl } from "@/lib/headless/wordpress";
import { useMangaLibraryState } from "@/hooks/headless/useMangaLibraryState";

const settingsTabs = ["Main", "Style", "Data Engines"];

export default function ReaderMatrix({ chapter }) {
  const pageRefs = useRef([]);
  const { markChapterRead, preferences, updatePreferences } = useMangaLibraryState();
  const [activePage, setActivePage] = useState(1);
  const [loadedPages, setLoadedPages] = useState(() => new Set([0, 1, 2]));
  const [hudVisible, setHudVisible] = useState(true);
  const [drawer, setDrawer] = useState(null);
  const [settingsTab, setSettingsTab] = useState("Main");
  const [localPrefs, setLocalPrefs] = useState(preferences);

  const images = useMemo(() => chapter.images.map(proxiedImageUrl), [chapter.images]);
  const totalPages = images.length;

  useEffect(() => {
    setLocalPrefs(preferences);
  }, [preferences]);

  useEffect(() => {
    markChapterRead({
      mangaId: chapter.mangaId,
      chapterId: chapter.id,
      chapterNumber: chapter.number,
      href: `/read/${chapter.id}`,
      title: chapter.mangaTitle,
      cover: chapter.mangaCover,
    });
  }, [chapter, markChapterRead]);

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          const index = Number(entry.target.getAttribute("data-page-index"));
          setActivePage(index + 1);
          setLoadedPages((current) => {
            const next = new Set(current);
            for (let offset = -1; offset <= 3; offset += 1) {
              const target = index + offset;
              if (target >= 0 && target < totalPages) next.add(target);
            }
            return next;
          });
        });
      },
      { threshold: 0.35, rootMargin: "900px 0px 900px 0px" }
    );

    pageRefs.current.forEach((node) => node && observer.observe(node));
    return () => observer.disconnect();
  }, [totalPages]);

  const updatePref = (patch) => {
    const next = { ...localPrefs, ...patch };
    setLocalPrefs(next);
    updatePreferences(patch);
  };

  const handleCanvasClick = (event) => {
    if (event.target.closest("a,button,input,textarea,select,[data-reader-ui]")) return;
    const centerStart = window.innerWidth * 0.3;
    const centerEnd = window.innerWidth * 0.7;
    if (event.clientX >= centerStart && event.clientX <= centerEnd) {
      setHudVisible((value) => !value);
    }
  };

  const scrollToPage = (page) => {
    const pageNumber = Number(page);
    pageRefs.current[pageNumber - 1]?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const imageStyle = {
    width: `${localPrefs.zoom || 100}%`,
    filter: [
      `brightness(${localPrefs.brightness || 100}%)`,
      localPrefs.grayscale ? "grayscale(100%)" : "",
      localPrefs.invert ? "invert(100%) hue-rotate(180deg)" : "",
    ]
      .filter(Boolean)
      .join(" "),
  };

  return (
    <main onClick={handleCanvasClick} className="min-h-screen bg-[#09090b] text-white">
      <TopHud chapter={chapter} visible={hudVisible} onChapters={() => setDrawer("chapters")} onSettings={() => setDrawer("settings")} />

      <div className={`mx-auto flex w-full flex-col items-center pt-16 ${localPrefs.gaps ? "gap-6" : "gap-0"}`}>
        {images.length === 0 && (
          <div className="mt-24 rounded-3xl border border-red-500/20 bg-red-500/10 p-8 text-center">
            <p className="font-black text-red-300">No scan URLs returned by WordPress.</p>
          </div>
        )}
        {images.map((src, index) => (
          <div
            key={`${src}-${index}`}
            ref={(node) => {
              pageRefs.current[index] = node;
            }}
            data-page-index={index}
            className="relative flex min-h-[360px] w-full justify-center bg-[#09090b]"
          >
            {!loadedPages.has(index) ? (
              <div className="my-24 h-8 w-8 animate-spin rounded-full border-2 border-zinc-700 border-t-orange-500" />
            ) : (
              <img
                src={src}
                alt={`Page ${index + 1}`}
                decoding="async"
                loading={index < 3 ? "eager" : "lazy"}
                className="block h-auto max-w-full object-contain"
                style={imageStyle}
              />
            )}
          </div>
        ))}
      </div>

      <EndFlow chapter={chapter} />

      <BottomHud
        visible={hudVisible}
        activePage={activePage}
        totalPages={totalPages}
        onPageChange={scrollToPage}
        onComments={() => setDrawer("comments")}
      />

      <Drawer open={drawer === "chapters"} onClose={() => setDrawer(null)} side="right">
        <ChaptersDrawer chapter={chapter} activeId={chapter.id} />
      </Drawer>

      <Drawer open={drawer === "settings"} onClose={() => setDrawer(null)} side="bottom">
        <SettingsDrawer
          activeTab={settingsTab}
          setActiveTab={setSettingsTab}
          preferences={localPrefs}
          updatePref={updatePref}
        />
      </Drawer>

      <Drawer open={drawer === "comments"} onClose={() => setDrawer(null)} side="bottom">
        <CommentsDrawer comments={chapter.comments} />
      </Drawer>
    </main>
  );
}

function TopHud({ chapter, visible, onChapters, onSettings }) {
  return (
    <nav
      data-reader-ui
      className={`fixed left-0 top-0 z-40 w-full border-b border-white/10 bg-zinc-950/85 px-4 py-3 backdrop-blur-xl transition ${
        visible ? "translate-y-0 opacity-100" : "-translate-y-full opacity-0"
      }`}
    >
      <div className="mx-auto flex max-w-5xl items-center justify-between gap-3">
        <Link href={`/manga/${chapter.mangaId}`} className="rounded-full bg-white/5 p-2 text-gray-300 hover:text-white">
          ←
        </Link>
        <div className="min-w-0 text-center">
          <p className="truncate text-sm font-black">{chapter.mangaTitle}</p>
          <p className="text-[10px] font-bold uppercase tracking-widest text-orange-400">Chapter {chapter.number}</p>
        </div>
        <div className="flex gap-2">
          <button onClick={onSettings} className="rounded-full bg-white/5 px-3 py-2 text-xs font-bold text-gray-300 hover:text-white">Config</button>
          <button onClick={onChapters} className="rounded-full bg-orange-500 px-3 py-2 text-xs font-black text-white">Chapters</button>
        </div>
      </div>
    </nav>
  );
}

function BottomHud({ visible, activePage, totalPages, onPageChange, onComments }) {
  return (
    <div
      data-reader-ui
      className={`fixed bottom-0 left-0 z-40 w-full border-t border-white/10 bg-zinc-950/90 px-4 py-3 backdrop-blur-xl transition ${
        visible ? "translate-y-0 opacity-100" : "translate-y-full opacity-0"
      }`}
    >
      <div className="mx-auto flex max-w-3xl items-center gap-4">
        <span className="min-w-10 rounded-xl bg-white/10 px-3 py-2 text-center text-xs font-black">{activePage}</span>
        <input
          aria-label="Page slider"
          type="range"
          min="1"
          max={Math.max(totalPages, 1)}
          value={activePage}
          onChange={(event) => onPageChange(event.target.value)}
          className="h-1 flex-1 accent-orange-500"
        />
        <span className="w-10 text-right text-xs font-bold text-gray-500">{totalPages}</span>
        <button onClick={onComments} className="rounded-xl bg-white/10 px-4 py-2 text-xs font-black text-gray-200">Comments</button>
      </div>
    </div>
  );
}

function Drawer({ open, onClose, side, children }) {
  const placement =
    side === "right"
      ? `right-0 top-0 h-full w-full max-w-md ${open ? "translate-x-0" : "translate-x-full"}`
      : `bottom-0 left-0 max-h-[82vh] w-full rounded-t-[2rem] ${open ? "translate-y-0" : "translate-y-full"}`;

  return (
    <>
      <div
        data-reader-ui
        onClick={onClose}
        className={`fixed inset-0 z-50 bg-black/70 backdrop-blur-sm transition ${open ? "opacity-100" : "pointer-events-none opacity-0"}`}
      />
      <aside data-reader-ui className={`fixed z-50 overflow-hidden border border-white/10 bg-zinc-950 shadow-2xl transition duration-300 ${placement}`}>
        {children}
      </aside>
    </>
  );
}

function ChaptersDrawer({ chapter, activeId }) {
  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-white/10 p-5">
        <div className="flex gap-4">
          <img src={proxiedImageUrl(chapter.mangaCover)} alt="" className="h-24 w-16 rounded-xl object-cover" />
          <div>
            <p className="line-clamp-2 font-black">{chapter.mangaTitle}</p>
            <p className="mt-1 text-xs font-bold text-orange-400">{chapter.chapters.length} indexed chapters</p>
            <Link href={`/manga/${chapter.mangaId}`} className="mt-3 inline-block rounded-xl bg-white/10 px-3 py-2 text-xs font-bold">Return to info</Link>
          </div>
        </div>
      </div>
      <div className="flex-1 overflow-y-auto p-3">
        {chapter.chapters.map((item) => (
          <Link
            key={item.id}
            href={`/read/${item.id}`}
            className={`mb-1 flex items-center justify-between rounded-2xl px-4 py-3 text-sm transition ${
              item.id === activeId ? "bg-orange-500 text-white" : "text-gray-300 hover:bg-white/5"
            }`}
          >
            <span className="font-bold">Chapter {item.number}</span>
            {item.id === activeId && <span className="text-[10px] font-black uppercase">Reading</span>}
          </Link>
        ))}
      </div>
    </div>
  );
}

function SettingsDrawer({ activeTab, setActiveTab, preferences, updatePref }) {
  return (
    <div className="mx-auto flex max-w-3xl flex-col">
      <div className="border-b border-white/10 p-5">
        <p className="text-lg font-black">Reader Configurations</p>
        <div className="mt-4 flex gap-2 overflow-x-auto">
          {settingsTabs.map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`rounded-full px-4 py-2 text-xs font-black uppercase tracking-widest ${
                activeTab === tab ? "bg-orange-500 text-white" : "bg-white/5 text-gray-400"
              }`}
            >
              {tab}
            </button>
          ))}
        </div>
      </div>
      <div className="space-y-5 overflow-y-auto p-5">
        {activeTab === "Main" && (
          <>
            <Range label="Canvas zoom" value={preferences.zoom || 100} min="60" max="180" onChange={(zoom) => updatePref({ zoom })} suffix="%" />
            <Toggle label="Page gaps" checked={preferences.gaps} onChange={(gaps) => updatePref({ gaps })} />
          </>
        )}
        {activeTab === "Style" && (
          <>
            <Range label="Brightness" value={preferences.brightness || 100} min="30" max="120" onChange={(brightness) => updatePref({ brightness })} suffix="%" />
            <Toggle label="Grayscale" checked={preferences.grayscale} onChange={(grayscale) => updatePref({ grayscale })} />
            <Toggle label="Invert colors" checked={preferences.invert} onChange={(invert) => updatePref({ invert })} />
          </>
        )}
        {activeTab === "Data Engines" && (
          <Toggle label="Data saver image requests" checked={preferences.dataSaver} onChange={(dataSaver) => updatePref({ dataSaver })} />
        )}
      </div>
    </div>
  );
}

function Range({ label, value, min, max, onChange, suffix }) {
  return (
    <label className="block rounded-2xl bg-white/5 p-4">
      <div className="mb-3 flex justify-between text-sm font-bold">
        <span>{label}</span>
        <span className="text-orange-400">{value}{suffix}</span>
      </div>
      <input type="range" min={min} max={max} value={value} onChange={(event) => onChange(Number(event.target.value))} className="w-full accent-orange-500" />
    </label>
  );
}

function Toggle({ label, checked, onChange }) {
  return (
    <label className="flex items-center justify-between rounded-2xl bg-white/5 p-4 text-sm font-bold">
      {label}
      <input type="checkbox" checked={Boolean(checked)} onChange={(event) => onChange(event.target.checked)} className="h-5 w-5 accent-orange-500" />
    </label>
  );
}

function EndFlow({ chapter }) {
  return (
    <section className="mx-auto max-w-3xl px-4 py-16 pb-28 text-center">
      <h2 className="mb-6 text-2xl font-black">End of Chapter {chapter.number}</h2>
      <div className="grid grid-cols-2 gap-3">
        <Link href={chapter.prevChapterId ? `/read/${chapter.prevChapterId}` : "#"} className={`rounded-2xl bg-white/10 px-5 py-4 font-black ${!chapter.prevChapterId ? "pointer-events-none opacity-30" : ""}`}>
          Previous
        </Link>
        <Link href={chapter.nextChapterId ? `/read/${chapter.nextChapterId}` : "#"} className={`rounded-2xl bg-orange-500 px-5 py-4 font-black ${!chapter.nextChapterId ? "pointer-events-none opacity-30" : ""}`}>
          Next
        </Link>
      </div>
    </section>
  );
}

function CommentsDrawer({ comments }) {
  return (
    <div className="mx-auto max-w-3xl">
      <div className="border-b border-white/10 p-5">
        <p className="text-lg font-black">Chapter Comments</p>
      </div>
      <div className="max-h-[60vh] overflow-y-auto p-5">
        {comments?.length ? (
          comments.map((comment) => (
            <div key={comment.id} className="mb-3 rounded-2xl bg-white/5 p-4">
              <p className="text-sm font-bold text-orange-400">{comment.author}</p>
              <p className="mt-1 text-sm text-gray-300">{comment.text}</p>
            </div>
          ))
        ) : (
          <p className="text-sm text-gray-500">No comments returned by WordPress yet.</p>
        )}
      </div>
    </div>
  );
}
