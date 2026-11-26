"use client";

export default function GlobalErrorPage({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  void error;
  return (
    <html lang="ru">
      <body className="flex min-h-screen items-center justify-center bg-slate-50 p-6 text-slate-900 antialiased">
        <main
          role="alert"
          className="w-full max-w-md space-y-4 rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm"
        >
          <h1 className="text-xl font-semibold">Критическая ошибка приложения</h1>
          <p className="text-sm text-slate-600">
            Произошёл непредвиденный сбой. Попробуйте перезагрузить страницу.
          </p>
          <button
            type="button"
            onClick={() => reset()}
            className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700"
          >
            Перезагрузить
          </button>
        </main>
      </body>
    </html>
  );
}
