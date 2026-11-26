"use client";

import { Suspense, useCallback, useEffect } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import Pagination from "@/app/components/Pagination";
import {
  getCategoryTree,
  listStoreProducts,
  listStores,
  type Category,
} from "@/app/lib/api/catalog";
import { formatMoney } from "@/app/lib/format";
import { useAsyncQuery } from "@/app/lib/use-async-query";

function readId(value: string | null): number | null {
  if (!value || !/^\d+$/.test(value)) {
    return null;
  }

  const parsed = Number(value);
  return parsed > 0 ? parsed : null;
}

function findCategory(categories: Category[], id: number): Category | null {
  for (const category of categories) {
    if (category.id === id) {
      return category;
    }

    const nested = findCategory(category.all_children ?? [], id);
    if (nested) {
      return nested;
    }
  }

  return null;
}

function CategoryBranch({
  category,
  selectedId,
  nested = false,
  onSelect,
}: {
  category: Category;
  selectedId: number | null;
  nested?: boolean;
  onSelect: (id: number) => void;
}) {
  const children = category.all_children ?? [];
  const active = category.id === selectedId;

  return (
    <li>
      <button
        type="button"
        aria-current={active ? "true" : undefined}
        onClick={() => onSelect(category.id)}
        className={`w-full rounded-lg px-3 py-2 text-left text-sm transition-colors ${
          active
            ? "bg-brand-50 font-medium text-brand-700"
            : nested
              ? "text-slate-600 hover:bg-brand-50 hover:text-brand-700"
              : "font-medium text-slate-900 hover:bg-brand-50 hover:text-brand-700"
        }`}
      >
        {category.name}
      </button>
      {children.length > 0 ? (
        <ul className="ml-4 border-l border-slate-200">
          {children.map((child) => (
            <CategoryBranch
              key={child.id}
              category={child}
              selectedId={selectedId}
              onSelect={onSelect}
              nested
            />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

function CatalogBrowser() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const accessDenied = searchParams.get("access") === "denied";
  const page = Math.max(1, Number(searchParams.get("page") ?? "1") || 1);
  const categoryId = readId(searchParams.get("category"));
  const storeFromUrl = readId(searchParams.get("store"));

  const replaceQuery = useCallback(
    (mutator: (next: URLSearchParams) => void) => {
      const next = new URLSearchParams(searchParams.toString());
      mutator(next);
      const query = next.toString();
      router.replace(query ? `${pathname}?${query}` : pathname, { scroll: false });
    },
    [pathname, router, searchParams],
  );

  const storesQuery = useAsyncQuery((signal) => listStores(signal), "catalog:stores", {
    fallbackError: "Не удалось загрузить магазины",
  });

  const stores = storesQuery.data ?? [];
  const knownStore = stores.find((item) => item.id === storeFromUrl) ?? null;
  const activeStoreId = knownStore?.id ?? stores[0]?.id ?? null;

  useEffect(() => {
    const loadedStores = storesQuery.data ?? [];
    if (loadedStores.length === 0) {
      return;
    }

    if (storeFromUrl !== null && loadedStores.some((store) => store.id === storeFromUrl)) {
      return;
    }

    const fallbackStore = loadedStores[0];
    if (!fallbackStore) {
      return;
    }

    replaceQuery((next) => {
      next.set("store", String(fallbackStore.id));
      if (storeFromUrl !== null) {
        next.delete("category");
        next.delete("page");
      }
    });
  }, [replaceQuery, storeFromUrl, storesQuery.data]);

  const categoriesQuery = useAsyncQuery(
    (signal) => getCategoryTree(activeStoreId, signal),
    `catalog:categories:${activeStoreId ?? "none"}`,
    {
      enabled: activeStoreId !== null,
      fallbackError: "Не удалось загрузить категории",
    },
  );

  const productsQuery = useAsyncQuery(
    (signal) =>
      listStoreProducts(activeStoreId, { category_id: categoryId, page, per_page: 12 }, signal),
    `catalog:products:${activeStoreId ?? "none"}:${categoryId ?? "all"}:${page}`,
    {
      enabled: activeStoreId !== null,
      fallbackError: "Не удалось загрузить товары",
      keepPreviousData: true,
    },
  );

  const categories = categoriesQuery.data ?? [];
  const products = productsQuery.data?.data ?? [];
  const meta = productsQuery.data?.meta;
  const totalCount = meta?.total ?? products.length;
  const lastPage = meta?.last_page ?? 1;

  const error = storesQuery.error || categoriesQuery.error || productsQuery.error;
  const showInitialLoader = productsQuery.isLoading && products.length === 0;

  const store = stores.find((item) => item.id === activeStoreId);
  const selectedCategory = categoryId ? findCategory(categories, categoryId) : null;

  function selectCategory(id: number) {
    replaceQuery((next) => {
      if (categoryId === id) {
        next.delete("category");
      } else {
        next.set("category", String(id));
      }
      next.delete("page");
    });
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        {accessDenied ? (
          <p role="alert" className="alert alert-error mb-4">
            Недостаточно прав для доступа к этому разделу.
          </p>
        ) : null}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-[0.6875rem] font-semibold uppercase tracking-[0.16em] text-slate-400">
              Магазин
            </p>
            <h1 className="page-title mt-1">{store?.name ?? "Каталог"}</h1>
            <p className="page-subtitle">Выберите категорию слева или найдите товар по названию.</p>
            <form action="/catalog/search" className="mt-4 flex max-w-xl gap-2">
              <input
                className="input"
                name="q"
                placeholder="Найти товар"
                aria-label="Поиск по каталогу"
              />
              <button type="submit" className="btn btn-primary">
                Найти
              </button>
            </form>
          </div>
          {stores.length > 1 ? (
            <label className="field min-w-56">
              Другой магазин
              <select
                className="select"
                value={activeStoreId ?? ""}
                onChange={(event) => {
                  replaceQuery((next) => {
                    next.set("store", event.target.value);
                    next.delete("category");
                    next.delete("page");
                  });
                }}
              >
                {stores.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.name}
                  </option>
                ))}
              </select>
            </label>
          ) : null}
        </div>

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}

        <div className="mt-8 grid items-start gap-6 lg:grid-cols-[240px_1fr]">
          <aside className="card p-3 lg:sticky lg:top-24">
            <p className="px-3 py-2 text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Категории
            </p>
            <button
              type="button"
              aria-current={categoryId === null ? "true" : undefined}
              onClick={() => {
                replaceQuery((next) => {
                  next.delete("category");
                  next.delete("page");
                });
              }}
              className={`w-full rounded-lg px-3 py-2 text-left text-sm transition-colors ${
                categoryId === null
                  ? "bg-brand-50 font-medium text-brand-700"
                  : "text-slate-600 hover:bg-brand-50 hover:text-brand-700"
              }`}
            >
              Все товары
            </button>
            {categoriesQuery.isLoading ? (
              <p className="px-3 py-2 text-sm text-slate-400">Загрузка...</p>
            ) : categories.length === 0 ? (
              <p className="px-3 py-2 text-sm text-slate-500">Категорий пока нет</p>
            ) : (
              <ul>
                {categories.map((category) => (
                  <CategoryBranch
                    key={category.id}
                    category={category}
                    selectedId={categoryId}
                    onSelect={selectCategory}
                  />
                ))}
              </ul>
            )}
          </aside>

          <section aria-busy={productsQuery.isLoading}>
            <div className="mb-4 flex items-baseline justify-between">
              <h2 className="text-lg font-semibold tracking-tight">
                {selectedCategory?.name ?? "Товары"}
              </h2>
              {!showInitialLoader ? (
                <p className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-slate-600">
                  {totalCount}
                </p>
              ) : null}
            </div>

            {showInitialLoader ? (
              <div className="rounded-2xl border border-slate-200 bg-white/70 px-6 py-16 text-center text-sm text-slate-500">
                Загружаем товары...
              </div>
            ) : products.length === 0 && !error ? (
              <div className="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-16 text-center text-sm text-slate-500">
                {selectedCategory
                  ? "В этой категории пока нет товаров"
                  : "В этом магазине пока нет товаров"}
              </div>
            ) : (
              <>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                  {products.map((product) => (
                    <Link
                      key={product.id}
                      href={`/catalog/products/${product.id}`}
                      className="card group overflow-hidden transition duration-200 hover:-translate-y-1 hover:border-brand-200 hover:shadow-pop"
                    >
                      <div className="relative flex h-52 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-100 to-brand-50">
                        {product.primary_image?.url ? (
                          <Image
                            src={product.primary_image.url}
                            alt={product.name}
                            fill
                            sizes="(max-width: 640px) 100vw, (max-width: 1280px) 50vw, 33vw"
                            className="object-cover transition duration-300 group-hover:scale-105"
                          />
                        ) : (
                          <span className="text-4xl font-semibold text-brand-300">
                            {product.name.slice(0, 1)}
                          </span>
                        )}
                      </div>
                      <div className="p-4">
                        <p className="line-clamp-2 min-h-12 font-medium leading-6 transition-colors group-hover:text-brand-700">
                          {product.name}
                        </p>
                        <div className="mt-3 flex items-center justify-between gap-3">
                          <p className="text-lg font-semibold tracking-tight">
                            {formatMoney(product.price, product.currency ?? "USD")}
                          </p>
                          <span
                            className={`text-xs ${
                              (product.available_stock ?? 0) > 0
                                ? "text-emerald-700"
                                : "text-slate-400"
                            }`}
                          >
                            {(product.available_stock ?? 0) > 0 ? "В наличии" : "Нет в наличии"}
                          </span>
                        </div>
                      </div>
                    </Link>
                  ))}
                </div>
                <Pagination
                  currentPage={meta?.current_page ?? page}
                  lastPage={lastPage}
                  onPageChange={(nextPage) => {
                    replaceQuery((next) => {
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
      </main>
    </div>
  );
}

export default function CatalogPage() {
  return (
    <Suspense fallback={<main className="state-screen">Загружаем каталог...</main>}>
      <CatalogBrowser />
    </Suspense>
  );
}
