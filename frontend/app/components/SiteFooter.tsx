import Link from "next/link";

export default function SiteFooter() {
  return (
    <footer className="mt-auto border-t border-slate-200 bg-white">
      <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p>© {new Date().getFullYear()} InnoTrainne</p>
        <nav className="flex flex-wrap gap-x-4 gap-y-2" aria-label="Подвал">
          <Link href="/" className="hover:text-slate-900">
            Главная
          </Link>
          <Link href="/catalog" className="hover:text-slate-900">
            Каталог
          </Link>
          <Link href="/cart" className="hover:text-slate-900">
            Корзина
          </Link>
          <Link href="/login" className="hover:text-slate-900">
            Вход
          </Link>
        </nav>
      </div>
    </footer>
  );
}
