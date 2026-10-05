"use client";

import Link from "next/link";
import Brand from "@/app/components/Brand";
import UserMenu from "@/app/components/UserMenu";

export default function AppHeader() {
  return (
    <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <Link href="/" aria-label="InnoTrainne">
          <Brand />
        </Link>
        <UserMenu />
      </div>
    </header>
  );
}
