import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";

export default function NotFoundState() {
  return (
    <div className="flex flex-1 flex-col">
      <AppHeader />
      <main className="mx-auto flex w-full max-w-3xl flex-1 items-center px-4 py-16 sm:px-6">
        <div className="card w-full space-y-4 p-6 text-center">
          <p className="eyebrow">404</p>
          <h1 className="text-xl font-semibold text-slate-900">Страница не найдена</h1>
          <p className="text-sm text-slate-600">
            Запрошенный ресурс не существует или страница была перемещена.
          </p>
          <div className="pt-1">
            <Link href="/catalog" className="btn btn-primary">
              Перейти в каталог
            </Link>
          </div>
        </div>
      </main>
    </div>
  );
}
