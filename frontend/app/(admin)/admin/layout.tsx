"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import Brand from "@/app/components/Brand";
import UserMenu from "@/app/components/UserMenu";

const catalogAdminUrl =
  process.env.NEXT_PUBLIC_CATALOG_ADMIN_URL ?? "https://localhost:8443/admin";

const links = [
  { href: "/admin/users", label: "Пользователи и роли", icon: "M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" },
  { href: "/admin/notifications", label: "Журнал уведомлений", icon: "M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" },
  { href: "/admin/analytics", label: "Аналитика", icon: "M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" },
  { href: "/catalog", label: "Витрина", icon: "M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" },
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
    return <div className="state-screen">Проверяем доступ...</div>;
  }

  return (
    <div className="flex min-h-screen">
      <aside className="sticky top-0 flex h-screen w-64 shrink-0 flex-col bg-gradient-to-b from-slate-900 to-slate-950 text-slate-100">
        <div className="px-5 pb-5 pt-6">
          <Brand tone="dark" />
          <p className="mt-4 text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-slate-500">Панель управления</p>
        </div>
        <nav className="flex flex-1 flex-col gap-1 overflow-y-auto px-3 pb-3">
          {links.map((link) => {
            const active = pathname === link.href;
            return (
              <Link
                key={link.href}
                href={link.href}
                className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition ${
                  active
                    ? "bg-brand-500/15 text-white ring-1 ring-inset ring-brand-400/30"
                    : "text-slate-400 hover:bg-white/5 hover:text-white"
                }`}
              >
                <svg
                  className={`h-5 w-5 shrink-0 transition-colors ${active ? "text-brand-300" : "text-slate-500 group-hover:text-slate-300"}`}
                  fill="none"
                  viewBox="0 0 24 24"
                  strokeWidth={1.5}
                  stroke="currentColor"
                  aria-hidden="true"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" d={link.icon} />
                </svg>
                {link.label}
              </Link>
            );
          })}
          <a
            href={catalogAdminUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="group mt-2 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400 ring-1 ring-inset ring-white/10 transition hover:bg-white/5 hover:text-white"
          >
            <svg
              className="h-5 w-5 shrink-0 text-slate-500 group-hover:text-slate-300"
              fill="none"
              viewBox="0 0 24 24"
              strokeWidth={1.5}
              stroke="currentColor"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"
              />
            </svg>
            Админка каталога
          </a>
        </nav>
        <div className="border-t border-white/10 p-3">
          <UserMenu variant="dark" dropUp />
        </div>
      </aside>
      <div className="min-w-0 flex-1 p-6 lg:p-10">
        <div className="mx-auto max-w-6xl">{children}</div>
      </div>
    </div>
  );
}
