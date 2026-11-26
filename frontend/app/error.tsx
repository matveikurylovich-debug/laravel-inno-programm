"use client";

import Link from "next/link";

export default function ErrorPage({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <main className="state-screen">
      <div role="alert" className="card max-w-md space-y-4 p-6 text-center">
        <p className="eyebrow">Ошибка</p>
        <h1 className="text-xl font-semibold text-slate-900">Что-то пошло не так</h1>
        <p className="text-sm text-slate-600">
          {error.message || "Не удалось отобразить страницу. Попробуйте обновить данные."}
        </p>
        <div className="flex flex-wrap justify-center gap-3 pt-1">
          <button type="button" onClick={() => reset()} className="btn btn-primary">
            Попробовать снова
          </button>
          <Link href="/catalog" className="btn btn-secondary">
            В каталог
          </Link>
        </div>
      </div>
    </main>
  );
}
