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
      <h1 className="page-title">Магазины</h1>
      <p className="page-subtitle">Создание и удаление магазинов витрины.</p>
      {error ? <p className="alert alert-error mt-4">{error}</p> : null}
      <form onSubmit={createStore} className="card mt-6 flex flex-col gap-3 p-4 sm:flex-row">
        <input value={name} onChange={(event) => setName(event.target.value)} placeholder="Название" className="input flex-1" required />
        <button className="btn btn-primary">Создать</button>
      </form>
      <ul className="mt-6 space-y-2">
        {stores.map((store) => (
          <li key={store.id} className="list-row">
            <span className="font-medium">{store.name}</span>
            <button type="button" onClick={() => remove(store)} className="btn btn-danger-ghost btn-sm">Удалить</button>
          </li>
        ))}
      </ul>
    </section>
  );
}
