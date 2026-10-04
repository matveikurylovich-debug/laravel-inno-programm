"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string; slug: string };
type Category = { id: number; name: string; all_children?: Category[] };
type Product = { id: number; name: string; price: string; available_stock?: number };

export default function CatalogPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
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

    apiFetch(`/api/catalog/v1/stores/${storeId}/products`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        const body = (await response.json()) as { data: Product[] };
        setProducts(body.data ?? []);
      })
      .catch(() => setError("Не удалось загрузить товары"));
  }, [storeId]);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-5xl px-6 py-8">
        <h1 className="text-2xl font-semibold">Витрина</h1>
        <p className="mt-1 text-sm text-slate-500">Каталог открыт без входа. Поиск с фильтрами остаётся в Search Service.</p>
        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}

        <label className="mt-6 block text-sm">
          Магазин
          <select
            className="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
            value={storeId ?? ""}
            onChange={(event) => setStoreId(Number(event.target.value))}
          >
            {stores.map((store) => (
              <option key={store.id} value={store.id}>
                {store.name}
              </option>
            ))}
          </select>
        </label>

        <section className="mt-8">
          <h2 className="text-lg font-medium">Категории</h2>
          <ul className="mt-2 space-y-1 text-sm text-slate-700">
            {categories.map((category) => (
              <li key={category.id}>
                {category.name}
                {(category.all_children ?? []).length > 0 ? (
                  <ul className="ml-4 list-disc">
                    {category.all_children?.map((child) => (
                      <li key={child.id}>{child.name}</li>
                    ))}
                  </ul>
                ) : null}
              </li>
            ))}
          </ul>
        </section>

        <section className="mt-8 grid gap-3 sm:grid-cols-2">
          {products.map((product) => (
            <Link key={product.id} href={`/catalog/products/${product.id}`} className="rounded-xl border border-slate-200 bg-white p-4">
              <p className="font-medium">{product.name}</p>
              <p className="mt-1 text-sm text-slate-500">{product.price}</p>
            </Link>
          ))}
        </section>
      </main>
    </div>
  );
}
