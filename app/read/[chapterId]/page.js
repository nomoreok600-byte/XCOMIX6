import ReaderMatrix from "@/components/headless/ReaderMatrix";
import { getChapterScans } from "@/lib/headless/wordpress";

export async function generateMetadata({ params }) {
  const chapter = await getChapterScans(params.chapterId);
  return {
    title: `${chapter.mangaTitle} Chapter ${chapter.number} | Reader`,
    description: `Read ${chapter.mangaTitle} chapter ${chapter.number}.`,
  };
}

export default async function ChapterReaderPage({ params }) {
  const chapter = await getChapterScans(params.chapterId);
  return <ReaderMatrix chapter={chapter} />;
}
