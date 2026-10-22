"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useState } from "react";
import Brand from "@/app/components/Brand";
import UserMenu from "@/app/components/UserMenu";
import { readCart } from "@/app/lib/cart";

function useCartCount(): number {
  const [count, setCount] = useState(0);

  useEffect(() => {
    function sync() {
      setCount(readCart().reduce((sum, line) => sum + line.quantity, 0));
    }

    sync();
    window.addEventListener("cart-changed", sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener("cart-changed", sync);
      window.removeEventListener("storage", sync);
    };
  }, []);

  return count;
}

export default function AppHeader() {
  const pathname = usePathname();
  const cartCount = useCartCount();
  const [signedIn, setSignedIn] = useState(false);
  const [staff, setStaff] = useState(false);
  const catalogActive = pathname === "/catalog" || pathname.startsWith("/catalog/");
  const assistantActive = pathname === "/assistant";
  const ordersActive = pathname === "/orders" || pathname.startsWith("/orders/");
  const cartActive = pathname === "/checkout";

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    const role = localStorage.getItem("user_role");
    setSignedIn(Boolean(token));
    setStaff(role === "admin" || role === "analyst");
  }, []);

  return (
    <header className="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
        <Link href="/catalog" aria-label="В каталог" className="shrink-0">
          <Brand />
        </Link>

        <nav className="flex items-center gap-1" aria-label="Магазин">
          <Link
            href="/catalog"
            aria-current={catalogActive ? "page" : undefined}
            className={`rounded-xl px-3 py-2 text-sm font-medium transition ${
              catalogActive ? "bg-brand-50 text-brand-700" : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
            }`}
          >
            Каталог
          </Link>
          {signedIn ? (
            <Link
              href="/assistant"
              aria-current={assistantActive ? "page" : undefined}
              className={`rounded-xl px-3 py-2 text-sm font-medium transition ${
                assistantActive ? "bg-brand-50 text-brand-700" : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
              }`}
            >
              Консультант
            </Link>
          ) : null}
        </nav>

        <div className="ml-auto flex items-center gap-1 sm:gap-2">
          {signedIn ? (
            <Link
              href="/orders"
              aria-current={ordersActive ? "page" : undefined}
              className={`rounded-xl px-3 py-2 text-sm font-medium transition ${
                ordersActive ? "bg-brand-50 text-brand-700" : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"
              }`}
            >
              Заказы
            </Link>
          ) : null}

          <Link
            href="/checkout"
            aria-current={cartActive ? "page" : undefined}
            className={`relative inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition ${
              cartActive ? "bg-brand-50 text-brand-700" : "text-slate-700 hover:bg-slate-100"
            }`}
          >
            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.7} aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.5l1.6 9.2a2 2 0 002 1.6h9.9a2 2 0 001.9-1.4L21 6.5H6.2" />
              <path strokeLinecap="round" d="M9 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm10.5 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
            </svg>
            <span className="hidden sm:inline">Корзина</span>
            {cartCount > 0 ? (
              <span className="inline-flex min-w-5 items-center justify-center rounded-full bg-brand-600 px-1.5 text-[0.6875rem] font-semibold text-white">
                {cartCount}
              </span>
            ) : null}
          </Link>

          {staff ? (
            <Link
              href="/admin/users"
              className="hidden rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900 sm:inline-flex"
            >
              Панель
            </Link>
          ) : null}

          {signedIn ? (
            <UserMenu />
          ) : (
            <Link href="/login" className="btn btn-primary btn-sm">
              Войти
            </Link>
          )}
        </div>
      </div>
    </header>
  );
}
