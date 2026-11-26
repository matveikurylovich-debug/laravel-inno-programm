"use client";

import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { destinationFor } from "@/app/lib/auth";
import { SessionUnavailableScreen, useSession } from "@/app/providers/SessionProvider";

export default function HomePage() {
  const { user, status, error, refresh } = useSession();

  if (status === "unavailable") {
    return (
      <SessionUnavailableScreen
        message={error}
        onRetry={() => {
          void refresh();
        }}
      />
    );
  }

  const signedIn = status === "authenticated" && user !== null;

  return (
    <div className="flex flex-1 flex-col">
      <AppHeader />
      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col justify-center px-4 py-16 sm:px-6">
        <p className="text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-slate-400">
          InnoTrainne
        </p>
        <h1 className="mt-3 max-w-2xl text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl">
          Магазин, в котором заказ начинается с витрины
        </h1>
        <p className="mt-4 max-w-xl text-lg leading-8 text-slate-600">
          Смотрите каталог, собирайте корзину и оформляйте заказ. Консультант и история покупок
          доступны после входа.
        </p>
        <div className="mt-8 flex flex-wrap gap-3">
          <Link href="/catalog" className="btn btn-primary">
            Открыть каталог
          </Link>
          {signedIn ? (
            <Link href={destinationFor(user)} className="btn btn-secondary">
              Продолжить
            </Link>
          ) : (
            <Link href="/login" className="btn btn-secondary">
              Войти
            </Link>
          )}
        </div>
      </main>
    </div>
  );
}
