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
    return <main className="p-8 text-sm text-slate-500">Проверяем сессию...</main>;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-3xl flex-col justify-center px-6">
        <p className="text-sm font-medium text-slate-500">Покупатель</p>
        <h1 className="mt-2 text-3xl font-semibold">Личный кабинет покупателя</h1>
        <p className="mt-3 max-w-xl text-slate-600">
          Здесь будет история заказов. Имя, телефон и пароль можно изменить в меню профиля.
        </p>
        <a href="/catalog" className="mt-6 text-sm font-medium text-slate-900 underline">
          Открыть витрину
        </a>
      </main>
    </div>
  );
}
