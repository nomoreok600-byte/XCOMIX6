import MangaClient from "./MangaClient";
import { fetchMangaList } from "../../../lib/api";

// Static export: enumerate every manga slug at build time.
export const dynamicParams = false;

export async function generateStaticParams() {
  try {
    const res = await fetchMangaList({ limit: 1000 });
    return (res.data || []).map((m) => ({ slug: m.slug }));
  } catch (err) {
    console.warn("generateStaticParams(manga) could not reach API:", err.message);
    return [];
  }
}

export default async function Page({ params }) {
  const { slug } = await params;
  return <MangaClient slug={slug} />;
}
