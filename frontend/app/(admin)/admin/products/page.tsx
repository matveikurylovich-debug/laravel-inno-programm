"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string };
type Category = { id: number; name: string };
type Product = { id: number; name: string; price: string };

export default function ProductsPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [storeId, setStoreId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [name, setName] = useState("");
  const [price, setPrice] = useState("0");
  const [quantity, setQuantity] = useState("0");
  const [categoryId, setCategoryId] = useState<number | null>(null);
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
        setCategoryId(body.data?.[0]?.id ?? null);
      })
      .catch(() => setError("Не удалось загрузить категории"));

    apiFetch(`/api/catalog/v1/stores/${storeId}/products`)
      .then(async (response) => {
        const body = (await response.json()) as { data: Product[] };
        setProducts(body.data ?? []);
      })
      .catch(() => setError("Не удалось загрузить товары"));
  }, [storeId]);

  async function createProduct(event: FormEvent) {
    event.preventDefault();
    setError("");
    const response = await apiFetch("/api/catalog/v1/products", {
      method: "POST",
      body: JSON.stringify({
        store_id: storeId,
        category_id: categoryId,
        name,
        price: Number(price),
        quantity: Number(quantity),
      }),
    });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }
    setName("");
    const list = await apiFetch(`/api/catalog/v1/stores/${storeId}/products`);
    const body = (await list.json()) as { data: Product[] };
    setProducts(body.data ?? []);
  }

  async function updateStock(product: Product) {
    const next = window.prompt("Новый остаток", "0");
    if (next === null) {
      return;
    }
    const response = await apiFetch(`/api/catalog/v1/products/${product.id}/stock`, {
      method: "PATCH",
      body: JSON.stringify({ quantity: Number(next) }),
    });
    if (!response.ok) {
      setError(await readError(response));
    }
  }

  return (
    <section>
      <h1 className="text-2xl font-semibold">Товары</h1>
      {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
      <label className="mt-4 block text-sm">
        Магазин
        <select className="mt-1 block rounded-lg border border-slate-300 px-3 py-2" value={storeId ?? ""} onChange={(event) => setStoreId(Number(event.target.value))}>
          {stores.map((store) => (
            <option key={store.id} value={store.id}>{store.name}</option>
          ))}
        </select>
      </label>
      <form onSubmit={createProduct} className="mt-4 grid gap-2 md:grid-cols-4">
        <select className="rounded-lg border border-slate-300 px-3 py-2 text-sm" value={categoryId ?? ""} onChange={(event) => setCategoryId(Number(event.target.value))}>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>{category.name}</option>
          ))}
        </select>
        <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
        <input value={price} onChange={(event) => setPrice(event.target.value)} type="number" min="0" step="0.01" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <input value={quantity} onChange={(event) => setQuantity(event.target.value)} type="number" min="0" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <button className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white md:col-span-4">Создать</button>
      </form>
      <ul className="mt-6 space-y-2">
        {products.map((product) => (
          <li key={product.id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
            <span>{product.name} — {product.price}</span>
            <button type="button" onClick={() => updateStock(product)} className="text-slate-700">Остаток</button>
          </li>
        ))}
      </ul>
    </section>
  );
}
