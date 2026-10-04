"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string };
type Category = { id: number; name: string; all_children?: Category[] };

export default function CategoriesPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [name, setName] = useState("");
  const [error, setError] = useState("");

  useEffect(() => {
    apiFetch("/api/catalog/v1/stores")
      .then(async (response) => {
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
        const body = (await response.json()) as { data: Category[] };
        setCategories(body.data ?? []);
      })
      .catch(() => setError("Не удалось загрузить категории"));
  }, [storeId]);

  async function createCategory(event: FormEvent) {
    event.preventDefault();
    setError("");
    const response = await apiFetch("/api/catalog/v1/categories", {
      method: "POST",
      body: JSON.stringify({ store_id: storeId, name }),
    });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }
    setName("");
    const tree = await apiFetch(`/api/catalog/v1/stores/${storeId}/categories/tree`);
    const body = (await tree.json()) as { data: Category[] };
    setCategories(body.data ?? []);
  }

  return (
    <section>
      <h1 className="text-2xl font-semibold">Категории</h1>
      {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
      <label className="mt-4 block text-sm">
        Магазин
        <select className="mt-1 block rounded-lg border border-slate-300 px-3 py-2" value={storeId ?? ""} onChange={(event) => setStoreId(Number(event.target.value))}>
          {stores.map((store) => (
            <option key={store.id} value={store.id}>{store.name}</option>
          ))}
        </select>
      </label>
      <form onSubmit={createCategory} className="mt-4 flex gap-2">
        <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название категории" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
        <button className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Создать</button>
      </form>
      <ul className="mt-6 space-y-1 text-sm">
        {categories.map((category) => (
          <li key={category.id}>{category.name}</li>
        ))}
      </ul>
    </section>
  );
}
