import ReaderClient from "./ReaderClient";
import { fetchMangaList, fetchManga } from "../../../lib/api";

// Static export: enumerate every chapter id across the catalog at build time.
export const dynamicParams = false;

export async function generateStaticParams() {
  try {
    const list = await fetchMangaList({ limit: 1000 });
    const ids = [];
    for (const m of list.data || []) {
      try {
        const detail = await fetchManga(m.slug);
        for (const ch of detail.chapters || []) ids.push({ id: String(ch.id) });
      } catch {
        /* skip a manga whose chapters cannot be resolved at build */
      }
    }
    return ids;
  } catch (err) {
    console.warn("generateStaticParams(reader) could not reach API:", err.message);
    return [];
  }
}

export default async function Page({ params }) {
  const { id } = await params;
  return <ReaderClient id={id} />;
}
