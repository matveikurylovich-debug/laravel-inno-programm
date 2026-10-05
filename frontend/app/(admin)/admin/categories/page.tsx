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
      <h1 className="page-title">Категории</h1>
      <p className="page-subtitle">Категории товаров выбранного магазина.</p>
      {error ? <p className="alert alert-error mt-4">{error}</p> : null}
      <div className="card mt-6 space-y-4 p-4 sm:p-5">
        <label className="field block max-w-sm">
          Магазин
          <select className="select" value={storeId ?? ""} onChange={(event) => setStoreId(Number(event.target.value))}>
            {stores.map((store) => (
              <option key={store.id} value={store.id}>{store.name}</option>
            ))}
          </select>
        </label>
        <form onSubmit={createCategory} className="flex flex-col gap-3 sm:flex-row">
          <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название категории" className="input flex-1" required />
          <button className="btn btn-primary">Создать</button>
        </form>
      </div>
      <ul className="mt-6 space-y-2">
        {categories.map((category) => (
          <li key={category.id} className="list-row font-medium">{category.name}</li>
        ))}
      </ul>
    </section>
  );
}
