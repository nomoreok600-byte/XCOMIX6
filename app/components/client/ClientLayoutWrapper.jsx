"use client";

import { usePathname } from "next/navigation";
import Header from "../Header";

export default function ClientLayoutWrapper({ children }) {
  const pathname = usePathname();
  const isReader = pathname?.startsWith("/read/");

  return (
    <>
      {!isReader && <Header />}
      {children}
    </>
  );
}
