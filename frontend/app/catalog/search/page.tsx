"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense } from "react";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type SearchProduct = { id?: string; name?: string; price?: number; category_name?: string };
type FacetCategory = { id: number | string; name: string; count: number };
type SearchResponse = {
  data: SearchProduct[];
  meta: { total: number; page: number; per_page: number };
  facets: {
    categories: FacetCategory[];
    in_stock: { true: number; false: number };
    price: { min: number | null; max: number | null };
  };
};

function SearchResults() {
  const params = useSearchParams();
  const query = params.get("q") ?? "";
  const [result, setResult] = useState<SearchResponse | null>(null);
  const [error, setError] = useState("");
  const [text, setText] = useState(query);

  useEffect(() => {
    setText(query);
    const search = new URLSearchParams();
    if (query) {
      search.set("q", query);
    }

    apiFetch(`/api/search?${search.toString()}`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        setResult((await response.json()) as SearchResponse);
        setError("");
      })
      .catch(() => setError("Не удалось выполнить поиск"));
  }, [query]);

  function submit(event: FormEvent) {
    event.preventDefault();
    const next = new URLSearchParams();
    if (text.trim()) {
      next.set("q", text.trim());
    }
    window.location.href = `/catalog/search?${next.toString()}`;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <form onSubmit={submit} className="flex gap-2">
          <input className="input" value={text} onChange={(event) => setText(event.target.value)} aria-label="Поиск" placeholder="Найти товар" />
          <button type="submit" className="btn btn-primary">Найти</button>
        </form>
        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {result ? (
          <div className="mt-6 grid gap-6 lg:grid-cols-[240px_1fr]">
            <aside className="card p-4 text-sm">
              <p className="font-semibold">Фильтры</p>
              <p className="mt-3 text-slate-500">В наличии: {result.facets.in_stock.true}</p>
              <p className="text-slate-500">Нет в наличии: {result.facets.in_stock.false}</p>
              <ul className="mt-4 space-y-2">
                {result.facets.categories.map((category) => (
                  <li key={String(category.id)} className="flex justify-between gap-3">
                    <span>{category.name || `Категория ${category.id}`}</span>
                    <span className="text-slate-400">{category.count}</span>
                  </li>
                ))}
              </ul>
            </aside>
            <section>
              <p className="text-sm text-slate-500">Найдено: {result.meta.total}</p>
              <ul className="mt-3 space-y-3">
                {result.data.map((product) => (
                  <li key={product.id} className="card px-4 py-3">
                    <Link href={`/catalog/products/${product.id}`} className="font-medium hover:text-brand-700">
                      {product.name}
                    </Link>
                    {product.category_name ? <p className="text-xs text-slate-500">{product.category_name}</p> : null}
                  </li>
                ))}
              </ul>
            </section>
          </div>
        ) : null}
      </main>
    </div>
  );
}

export default function SearchPage() {
  return (
    <Suspense fallback={<main className="state-screen">Ищем товары...</main>}>
      <SearchResults />
    </Suspense>
  );
}
