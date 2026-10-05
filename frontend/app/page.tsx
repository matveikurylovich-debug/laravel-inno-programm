"use client";

import { useEffect, useState } from "react";
import AppHeader from "@/app/components/AppHeader";

export default function CustomerHomePage() {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("access_token");
    const role = localStorage.getItem("user_role");

    if (!token) {
      window.location.href = "/login";
      return;
    }

    if (role === "admin" || role === "analyst") {
      window.location.href = "/admin/notifications";
      return;
    }

    setReady(true);
  }, []);

  if (!ready) {
    return <main className="state-screen">Проверяем сессию...</main>;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl flex-col justify-center px-4 py-10 sm:px-6">
        <section className="card relative overflow-hidden p-8 sm:p-10">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-gradient-to-br from-brand-300/40 to-sky-300/30 blur-2xl"
          />
          <div className="relative">
            <span className="badge badge-brand">Покупатель</span>
            <h1 className="mt-4 text-3xl font-semibold tracking-tight">Личный кабинет покупателя</h1>
            <p className="mt-3 max-w-xl leading-7 text-slate-600">
              Здесь будет история заказов. Имя, телефон и пароль можно изменить в меню профиля.
            </p>
            <a href="/catalog" className="btn btn-primary mt-7">
              Открыть витрину
              <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path
                  fillRule="evenodd"
                  d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"
                  clipRule="evenodd"
                />
              </svg>
            </a>
          </div>
        </section>
      </main>
    </div>
  );
}
