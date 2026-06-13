import MangaInfoClient from "@/components/headless/MangaInfoClient";
import { getMangaInfo } from "@/lib/headless/wordpress";

export async function generateMetadata({ params }) {
  const manga = await getMangaInfo(params.id);
  return {
    title: `${manga.title} | Manga`,
    description: manga.description,
  };
}

export default async function MangaInfoPage({ params }) {
  const manga = await getMangaInfo(params.id);
  return <MangaInfoClient manga={manga} />;
}
