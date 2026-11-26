"use client";

import { Suspense, useState, type FormEvent } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import Pagination from "@/app/components/Pagination";
import { extractBrandFacets, searchCatalog } from "@/app/lib/api/search";
import { formatMoney } from "@/app/lib/format";
import { useAsyncQuery } from "@/app/lib/use-async-query";

function SearchResults() {
  const router = useRouter();
  const params = useSearchParams();

  const query = params.get("q") ?? "";
  const selectedCategoryId = params.get("category_id") ?? "";
  const selectedBrand = params.get("brand") ?? "";
  const selectedInStock = params.get("in_stock") ?? "";
  const selectedSort = params.get("sort") ?? "relevance";
  const pageParam = Math.max(1, Number(params.get("page") ?? "1") || 1);

  const [editedText, setEditedText] = useState<string | null>(null);
  const text = editedText ?? query;

  const searchKey = JSON.stringify({
    q: query,
    category_id: selectedCategoryId,
    brand: selectedBrand,
    in_stock: selectedInStock,
    sort: selectedSort,
    page: pageParam,
  });

  const searchQuery = useAsyncQuery(
    (signal) =>
      searchCatalog(
        {
          q: query || undefined,
          category_id: selectedCategoryId || undefined,
          brand: selectedBrand || undefined,
          in_stock: selectedInStock || undefined,
          sort: selectedSort !== "relevance" ? selectedSort : undefined,
          page: pageParam,
          per_page: 20,
        },
        signal,
      ),
    `search:${searchKey}`,
    { fallbackError: "Не удалось выполнить поиск", keepPreviousData: true },
  );

  function updateSearchParams(mutator: (next: URLSearchParams) => void) {
    const next = new URLSearchParams(params.toString());
    mutator(next);
    const queryString = next.toString();
    router.push(queryString ? `/catalog/search?${queryString}` : "/catalog/search");
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    setEditedText(null);
    updateSearchParams((next) => {
      const trimmed = text.trim();
      if (trimmed) {
        next.set("q", trimmed);
      } else {
        next.delete("q");
      }
      next.delete("page");
    });
  }

  function toggleCategory(categoryId: string) {
    updateSearchParams((next) => {
      if (selectedCategoryId === categoryId) {
        next.delete("category_id");
      } else {
        next.set("category_id", categoryId);
      }
      next.delete("page");
    });
  }

  function toggleBrand(brand: string) {
    updateSearchParams((next) => {
      if (selectedBrand === brand) {
        next.delete("brand");
      } else {
        next.set("brand", brand);
      }
      next.delete("page");
    });
  }

  function toggleInStock(value: "true" | "false") {
    updateSearchParams((next) => {
      if (selectedInStock === value) {
        next.delete("in_stock");
      } else {
        next.set("in_stock", value);
      }
      next.delete("page");
    });
  }

  function resetFilters() {
    updateSearchParams((next) => {
      next.delete("category_id");
      next.delete("brand");
      next.delete("in_stock");
      next.delete("sort");
      next.delete("page");
    });
  }

  const result = searchQuery.data;
  const brandFacets = extractBrandFacets(result?.facets);
  const hasActiveFilters = Boolean(
    selectedCategoryId || selectedBrand || selectedInStock || selectedSort !== "relevance",
  );
  const lastPage = result
    ? Math.max(1, Math.ceil(result.meta.total / Math.max(1, result.meta.per_page)))
    : 1;

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        <form onSubmit={submit} className="flex flex-wrap gap-2">
          <input
            className="input min-w-60 flex-1"
            value={text}
            onChange={(event) => setEditedText(event.target.value)}
            aria-label="Поисковый запрос"
            placeholder="Найти товар"
          />
          <select
            className="select w-auto"
            aria-label="Сортировка результатов"
            value={selectedSort}
            onChange={(event) => {
              const value = event.target.value;
              updateSearchParams((next) => {
                if (value === "relevance") {
                  next.delete("sort");
                } else {
                  next.set("sort", value);
                }
                next.delete("page");
              });
            }}
          >
            <option value="relevance">По релевантности</option>
            <option value="price_asc">Сначала дешевле</option>
            <option value="price_desc">Сначала дороже</option>
          </select>
          <button type="submit" className="btn btn-primary">
            Найти
          </button>
        </form>

        {searchQuery.error ? (
          <p role="alert" className="alert alert-error mt-4">
            {searchQuery.error}
          </p>
        ) : null}

        {searchQuery.isLoading && !result ? (
          <p className="mt-6 text-sm text-slate-500">Ищем товары...</p>
        ) : result ? (
          <div
            className="mt-6 grid gap-6 lg:grid-cols-[240px_1fr]"
            aria-busy={searchQuery.isLoading}
          >
            <aside className="card space-y-4 p-4 text-sm">
              <div className="flex items-center justify-between">
                <p className="font-semibold">Фильтры</p>
                {hasActiveFilters ? (
                  <button
                    type="button"
                    onClick={resetFilters}
                    className="text-xs font-medium text-brand-700 hover:underline"
                  >
                    Сбросить
                  </button>
                ) : null}
              </div>

              <div className="space-y-1.5">
                <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                  Наличие
                </p>
                <button
                  type="button"
                  aria-pressed={selectedInStock === "true"}
                  onClick={() => toggleInStock("true")}
                  className={`flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left transition ${
                    selectedInStock === "true"
                      ? "bg-brand-50 font-medium text-brand-700"
                      : "text-slate-600 hover:bg-slate-100"
                  }`}
                >
                  <span>В наличии</span>
                  <span className="text-xs text-slate-400">{result.facets.in_stock.true}</span>
                </button>
                <button
                  type="button"
                  aria-pressed={selectedInStock === "false"}
                  onClick={() => toggleInStock("false")}
                  className={`flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left transition ${
                    selectedInStock === "false"
                      ? "bg-brand-50 font-medium text-brand-700"
                      : "text-slate-600 hover:bg-slate-100"
                  }`}
                >
                  <span>Нет в наличии</span>
                  <span className="text-xs text-slate-400">{result.facets.in_stock.false}</span>
                </button>
              </div>

              {result.facets.categories.length > 0 ? (
                <div className="space-y-1.5">
                  <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Категории
                  </p>
                  <ul className="space-y-1">
                    {result.facets.categories.map((category) => {
                      const idString = String(category.id);
                      const active = selectedCategoryId === idString;
                      return (
                        <li key={idString}>
                          <button
                            type="button"
                            aria-pressed={active}
                            onClick={() => toggleCategory(idString)}
                            className={`flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-1.5 text-left transition ${
                              active
                                ? "bg-brand-50 font-medium text-brand-700"
                                : "text-slate-700 hover:bg-slate-100"
                            }`}
                          >
                            <span className="truncate">
                              {category.name || `Категория ${category.id}`}
                            </span>
                            <span className="shrink-0 text-xs text-slate-400">
                              {category.count}
                            </span>
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                </div>
              ) : null}

              {brandFacets.length > 0 ? (
                <div className="space-y-1.5">
                  <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Бренд
                  </p>
                  <ul className="space-y-1">
                    {brandFacets.map((brand) => {
                      const active = selectedBrand === brand.value;
                      return (
                        <li key={brand.value}>
                          <button
                            type="button"
                            aria-pressed={active}
                            onClick={() => toggleBrand(brand.value)}
                            className={`flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-1.5 text-left transition ${
                              active
                                ? "bg-brand-50 font-medium text-brand-700"
                                : "text-slate-700 hover:bg-slate-100"
                            }`}
                          >
                            <span className="truncate">{brand.value}</span>
                            <span className="shrink-0 text-xs text-slate-400">{brand.count}</span>
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                </div>
              ) : null}
            </aside>

            <section>
              <p className="text-sm text-slate-500">Найдено: {result.meta.total}</p>
              {result.data.length === 0 ? (
                <div className="card mt-3 px-6 py-12 text-center text-sm text-slate-500">
                  По выбранным условиям товары не найдены.
                </div>
              ) : (
                <>
                  <ul className="mt-3 space-y-3">
                    {result.data.map((product, index) => (
                      <li
                        key={String(product.id ?? index)}
                        className="card flex items-center justify-between gap-4 px-4 py-3"
                      >
                        <div className="min-w-0">
                          <Link
                            href={`/catalog/products/${product.id}`}
                            className="font-medium hover:text-brand-700"
                          >
                            {product.name}
                          </Link>
                          {product.category_name ? (
                            <p className="text-xs text-slate-500">{product.category_name}</p>
                          ) : null}
                        </div>
                        {product.price !== undefined ? (
                          <p className="shrink-0 font-semibold">
                            {formatMoney(product.price, product.currency ?? "USD")}
                          </p>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                  <Pagination
                    currentPage={result.meta.page}
                    lastPage={lastPage}
                    onPageChange={(nextPage) => {
                      updateSearchParams((next) => {
                        if (nextPage <= 1) {
                          next.delete("page");
                        } else {
                          next.set("page", String(nextPage));
                        }
                      });
                    }}
                  />
                </>
              )}
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
