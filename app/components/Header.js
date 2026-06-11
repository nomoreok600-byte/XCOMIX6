import Link from "next/link";

export default function Header() {
  return (
    <header className="fixed left-0 top-0 z-50 w-full border-b border-white/10 bg-[#09090b]/85 px-4 py-3 text-white backdrop-blur-xl">
      <nav className="mx-auto flex max-w-7xl items-center justify-between gap-4">
        <Link href="/manga" className="text-lg font-black tracking-tight text-white">
          Manga Headless
        </Link>
        <div className="flex items-center gap-4 text-sm font-bold text-gray-300">
          <Link href="/manga" className="hover:text-orange-400">
            Home
          </Link>
          <a href={process.env.NEXT_PUBLIC_WORDPRESS_API_URL || "#"} className="hidden hover:text-orange-400 sm:inline">
            WordPress API
          </a>
        </div>
      </nav>
    </header>
  );
}
