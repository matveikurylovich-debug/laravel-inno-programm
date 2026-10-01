"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import UserMenu from "@/app/components/UserMenu";

const links = [
  { href: "/admin/users", label: "Пользователи и роли" },
  { href: "/admin/notifications", label: "Журнал уведомлений" },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    const role = localStorage.getItem("user_role");

    if (!token) {
      window.location.href = "/login";
      return;
    }

    if (role === "customer" || !role) {
      window.location.href = "/";
      return;
    }

    setReady(true);
  }, []);

  if (!ready) {
    return <div className="p-8 text-sm text-slate-500">Проверяем доступ...</div>;
  }

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-64 shrink-0 flex-col bg-slate-900 text-slate-100">
        <div className="border-b border-slate-800 px-5 py-6">
          <p className="text-xs uppercase tracking-wide text-slate-400">Панель</p>
          <p className="mt-1 text-lg font-semibold">InnoTrainne</p>
        </div>
        <nav className="flex flex-1 flex-col gap-1 p-3">
          {links.map((link) => {
            const active = pathname === link.href;
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`rounded-lg px-3 py-2 text-sm ${active ? "bg-slate-700 text-white" : "text-slate-300 hover:bg-slate-800"}`}
              >
                {link.label}
              </Link>
            );
          })}
        </nav>
        <div className="border-t border-slate-800 p-3">
          <UserMenu variant="dark" dropUp />
        </div>
      </aside>
      <div className="min-w-0 flex-1 p-8">{children}</div>
    </div>
  );
}
