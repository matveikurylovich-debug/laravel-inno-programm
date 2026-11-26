import Link from "next/link";

export default function SiteFooter() {
  return (
    <footer className="border-t border-slate-200 px-4 py-6 text-sm text-slate-500">
      <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3">
        <p>© 2026 InnoTrainne</p>
        <nav className="flex gap-4">
          <Link href="/">Главная</Link>
          <Link href="/catalog">Каталог</Link>
          <Link href="/cart">Корзина</Link>
          <Link href="/login">Вход</Link>
        </nav>
      </div>
    </footer>
  );
}
