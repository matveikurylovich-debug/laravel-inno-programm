"use client";

import Link from "next/link";
import UserMenu from "@/app/components/UserMenu";

export default function AppHeader() {
  return (
    <header className="border-b border-slate-200 bg-white">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
        <Link href="/" className="text-sm font-semibold">
          InnoTrainne
        </Link>
        <UserMenu />
      </div>
    </header>
  );
}
