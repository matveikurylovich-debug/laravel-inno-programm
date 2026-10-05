"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { apiFetch, logout } from "@/app/lib/api";
import type { AuthUser } from "@/app/lib/types";

type UserMenuProps = {
  variant?: "light" | "dark";
  dropUp?: boolean;
};

export default function UserMenu({ variant = "light", dropUp = false }: UserMenuProps) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setName(localStorage.getItem("user_name") ?? "");

    apiFetch("/api/auth/me")
      .then(async (response) => {
        if (!response.ok) {
          return;
        }

        const body = (await response.json()) as { user: AuthUser };
        setName(body.user.name);
        setEmail(body.user.email);
        localStorage.setItem("user_name", body.user.name);
      })
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    function onPointerDown(event: MouseEvent) {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, []);

  const buttonClass =
    variant === "dark"
      ? "w-full justify-between text-slate-200 hover:bg-white/10"
      : "text-slate-700 hover:bg-slate-100";
  const initial = (name || "П").trim().slice(0, 1).toUpperCase();

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className={`inline-flex items-center gap-2.5 rounded-xl py-1.5 pl-1.5 pr-2.5 text-sm font-medium transition ${buttonClass}`}
      >
        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-400 to-brand-700 text-xs font-semibold text-white">
          {initial}
        </span>
        <span className="min-w-0 flex-1 truncate text-left">{name || "Профиль"}</span>
        <svg
          className={`h-4 w-4 shrink-0 opacity-60 transition-transform ${open ? "rotate-180" : ""}`}
          viewBox="0 0 20 20"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            fillRule="evenodd"
            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
            clipRule="evenodd"
          />
        </svg>
      </button>

      {open ? (
        <div
          className={`absolute z-40 w-60 rounded-2xl border border-slate-200 bg-white p-1.5 text-slate-900 shadow-pop ${
            dropUp ? "bottom-full left-0 mb-2" : "right-0 mt-2"
          }`}
        >
          <div className="border-b border-slate-100 px-3 pb-2.5 pt-2">
            <p className="truncate text-sm font-semibold">{name || "Пользователь"}</p>
            {email ? <p className="mt-0.5 truncate text-xs text-slate-500">{email}</p> : null}
          </div>
          <div className="pt-1.5">
            <Link
              href="/profile"
              onClick={() => setOpen(false)}
              className="block rounded-lg px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-100"
            >
              Профиль
            </Link>
            <button
              type="button"
              onClick={() => {
                void logout();
              }}
              className="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 transition hover:bg-rose-50"
            >
              Выйти
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
