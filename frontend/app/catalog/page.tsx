"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string; slug: string };
type Category = { id: number; name: string; all_children?: Category[] };
type Product = {
  id: number;
  name: string;
  price: string;
  available_stock?: number;
  primary_image?: { url: string } | null;
};

function money(value: string): string {
  const number = Number(value);
  if (Number.isNaN(number)) {
    return value;
  }
  return new Intl.NumberFormat("ru-RU", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
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
            <CategoryBranch key={child.id} category={child} selectedId={selectedId} onSelect={onSelect} nested />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

export default function CatalogPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [error, setError] = useState("");

  useEffect(() => {
    apiFetch("/api/catalog/v1/stores")
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        const body = (await response.json()) as { data: Store[] };
        setStores(body.data ?? []);
        setStoreId(body.data?.[0]?.id ?? null);
      })
      .catch(() => setError("Не удалось загрузить магазины"));
  }, []);

  useEffect(() => {
    if (!storeId) {
      return;
    }

    apiFetch(`/api/catalog/v1/stores/${storeId}/categories/tree`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        const body = (await response.json()) as { data: Category[] };
        setCategories(body.data ?? []);
      })
      .catch(() => setError("Не удалось загрузить категории"));
  }, [storeId]);

  useEffect(() => {
    if (!storeId) {
      return;
    }

    const params = new URLSearchParams({ per_page: "100" });
    if (categoryId) {
      params.set("category_id", String(categoryId));
    }

    let cancelled = false;

    apiFetch(`/api/catalog/v1/stores/${storeId}/products?${params.toString()}`)
      .then(async (response) => {
        if (!response.ok) {
          if (!cancelled) {
            setError(await readError(response));
          }
          return;
        }
        const body = (await response.json()) as { data: Product[] };
        if (!cancelled) {
          setProducts(body.data ?? []);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setError("Не удалось загрузить товары");
        }
      });

    return () => {
      cancelled = true;
    };
  }, [storeId, categoryId]);

  const store = stores.find((item) => item.id === storeId);
  const selectedCategory = categoryId ? findCategory(categories, categoryId) : null;

  function selectCategory(id: number) {
    setCategoryId((current) => (current === id ? null : id));
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div className="relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 px-6 py-8 text-white shadow-pop sm:flex-row sm:items-end sm:justify-between sm:px-8">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -right-10 -top-24 h-64 w-64 rounded-full bg-white/10 blur-3xl"
          />
          <div className="relative">
            <p className="text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-brand-200">Каталог</p>
            <h1 className="mt-2 text-3xl font-semibold tracking-tight">{store?.name ?? "Витрина"}</h1>
            <p className="mt-2 max-w-xl text-sm text-brand-100">Товары и категории выбранного магазина.</p>
          </div>
          <label className="relative text-sm font-medium text-brand-100">
            Магазин
            <select
              className="select mt-1.5 min-w-56 border-white/20 bg-white/10 text-white backdrop-blur hover:border-white/40 focus:border-white/60 focus:ring-white/20 [&>option]:text-slate-900"
              value={storeId ?? ""}
              onChange={(event) => {
                setCategoryId(null);
                setStoreId(Number(event.target.value));
              }}
            >
              {stores.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>
        </div>

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}

        <div className="mt-8 grid items-start gap-6 lg:grid-cols-[240px_1fr]">
          <aside className="card p-3 lg:sticky lg:top-24">
            <p className="px-3 py-2 text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-slate-400">Категории</p>
            <button
              type="button"
              aria-current={categoryId === null ? "true" : undefined}
              onClick={() => setCategoryId(null)}
              className={`w-full rounded-lg px-3 py-2 text-left text-sm transition-colors ${
                categoryId === null
                  ? "bg-brand-50 font-medium text-brand-700"
                  : "text-slate-600 hover:bg-brand-50 hover:text-brand-700"
              }`}
            >
              Все товары
            </button>
            {categories.length === 0 ? (
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

          <section>
            <div className="mb-4 flex items-baseline justify-between">
              <h2 className="text-lg font-semibold tracking-tight">{selectedCategory?.name ?? "Товары"}</h2>
              <p className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold tabular-nums text-slate-600">{products.length}</p>
            </div>
            {products.length === 0 ? (
              <div className="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-6 py-16 text-center text-sm text-slate-500">
                {selectedCategory ? "В этой категории пока нет товаров" : "В этом магазине пока нет товаров"}
              </div>
            ) : (
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {products.map((product) => (
                  <Link
                    key={product.id}
                    href={`/catalog/products/${product.id}`}
                    className="card group overflow-hidden transition duration-200 hover:-translate-y-1 hover:border-brand-200 hover:shadow-pop"
                  >
                    <div className="flex h-52 items-center justify-center overflow-hidden bg-gradient-to-br from-slate-100 to-brand-50">
                      {product.primary_image?.url ? (
                        <img src={product.primary_image.url} alt="" className="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
                      ) : (
                        <span className="text-4xl font-semibold text-brand-300">{product.name.slice(0, 1)}</span>
                      )}
                    </div>
                    <div className="p-4">
                      <p className="line-clamp-2 min-h-12 font-medium leading-6 transition-colors group-hover:text-brand-700">{product.name}</p>
                      <p className="mt-3 text-lg font-semibold tracking-tight">{money(product.price)}</p>
                    </div>
                  </Link>
                ))}
              </div>
            )}
          </section>
        </div>
      </main>
    </div>
  );
}
