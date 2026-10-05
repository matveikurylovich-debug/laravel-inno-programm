"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string };
type Category = { id: number; name: string };
type ProductImage = { url: string };
type Product = { id: number; name: string; price: string; primary_image?: ProductImage | null };

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

  async function uploadPhoto(product: Product, file: File) {
    setError("");
    const form = new FormData();
    form.append("image", file);

    const response = await apiFetch(`/api/catalog/v1/products/${product.id}/images`, {
      method: "POST",
      body: form,
    });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }

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
      <h1 className="page-title">Товары</h1>
      <p className="page-subtitle">Создание товаров, загрузка фото и управление остатками.</p>
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
        <form onSubmit={createProduct} className="grid gap-3 md:grid-cols-4">
          <select className="select" value={categoryId ?? ""} onChange={(event) => setCategoryId(Number(event.target.value))}>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>{category.name}</option>
            ))}
          </select>
          <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название" className="input" required />
          <input value={price} onChange={(event) => setPrice(event.target.value)} type="number" min="0" step="0.01" className="input" />
          <input value={quantity} onChange={(event) => setQuantity(event.target.value)} type="number" min="0" className="input" />
          <button className="btn btn-primary md:col-span-4">Создать</button>
        </form>
      </div>

      <ul className="mt-6 space-y-2">
        {products.map((product) => (
          <li key={product.id} className="list-row">
            <span className="flex min-w-0 items-center gap-3">
              {product.primary_image?.url ? (
                <img src={product.primary_image.url} alt="" className="h-12 w-12 shrink-0 rounded-xl border border-slate-200 object-cover" />
              ) : (
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-slate-100 to-brand-50 text-lg font-semibold text-brand-300">
                  {product.name.slice(0, 1)}
                </span>
              )}
              <span className="min-w-0">
                <span className="block truncate font-medium">{product.name}</span>
                <span className="block text-slate-500">{product.price}</span>
              </span>
            </span>
            <span className="flex shrink-0 gap-2">
              <label className="btn btn-secondary btn-sm cursor-pointer">
                Фото
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="hidden"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                      uploadPhoto(product, file);
                    }
                    event.target.value = "";
                  }}
                />
              </label>
              <button type="button" onClick={() => updateStock(product)} className="btn btn-secondary btn-sm">Остаток</button>
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
