"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";

type Store = { id: number; name: string; slug: string };

export default function StoresPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [error, setError] = useState("");
  const [name, setName] = useState("");

  async function load() {
    const response = await apiFetch("/api/catalog/v1/stores");
    if (!response.ok) {
      setError(await readError(response));
      return;
    }
    const body = (await response.json()) as { data: Store[] };
    setStores(body.data ?? []);
  }

  useEffect(() => {
    load().catch(() => setError("Не удалось загрузить магазины"));
  }, []);

  async function createStore(event: FormEvent) {
    event.preventDefault();
    setError("");
    const response = await apiFetch("/api/catalog/v1/stores", {
      method: "POST",
      body: JSON.stringify({ name }),
    });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }
    setName("");
    await load();
  }

  async function remove(store: Store) {
    const response = await apiFetch(`/api/catalog/v1/stores/${store.id}`, { method: "DELETE" });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }
    await load();
  }

  return (
    <section>
      <h1 className="text-2xl font-semibold">Магазины</h1>
      {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
      <form onSubmit={createStore} className="mt-4 flex gap-2">
        <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
        <button className="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Создать</button>
      </form>
      <ul className="mt-6 space-y-2">
        {stores.map((store) => (
          <li key={store.id} className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm">
            <span>{store.name}</span>
            <button type="button" onClick={() => remove(store)} className="text-red-600">Удалить</button>
          </li>
        ))}
      </ul>
    </section>
  );
}
