import MangaHomeClient from "@/components/headless/MangaHomeClient";
import { getHeadlessHome } from "@/lib/headless/wordpress";

export const metadata = {
  title: "Manga | Headless Reader",
  description: "Fast headless manga frontend powered by WordPress REST data.",
};

export default async function MangaHomePage() {
  const payload = await getHeadlessHome();
  return <MangaHomeClient payload={payload} />;
}
