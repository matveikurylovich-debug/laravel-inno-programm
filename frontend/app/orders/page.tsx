"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";
import { canCancel, money, statusLabel, type Order } from "@/app/lib/orders";

export default function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [error, setError] = useState("");
  const [cancellingId, setCancellingId] = useState<string | null>(null);
  const [deletingId, setDeletingId] = useState<string | null>(null);

  async function cancelOrder(id: string) {
    setCancellingId(id);
    setError("");
    const response = await apiFetch(`/api/orders/${id}/cancel`, { method: "POST" });
    setCancellingId(null);

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    const body = (await response.json()) as { data: Order };
    setOrders((current) => current.map((order) => (order.id === id ? { ...order, ...body.data } : order)));
  }

  async function deleteOrder(id: string) {
    if (!window.confirm("Удалить заказ из списка?")) {
      return;
    }

    setDeletingId(id);
    setError("");
    const response = await apiFetch(`/api/orders/${id}`, { method: "DELETE" });
    setDeletingId(null);

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    setOrders((current) => current.filter((order) => order.id !== id));
  }

  useEffect(() => {
    apiFetch("/api/orders")
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }

        const body = (await response.json()) as { data: Order[] };
        setOrders(body.data ?? []);
      })
      .catch(() => setError("Не удалось загрузить заказы"));
  }, []);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 className="page-title">Мои заказы</h1>
        <p className="page-subtitle">Список покупок текущего пользователя.</p>
        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {orders.length === 0 && !error ? (
          <div className="card mt-6 px-6 py-12 text-center text-sm text-slate-500">Заказов пока нет.</div>
        ) : null}
        {orders.length > 0 ? (
          <ul className="mt-6 space-y-3">
            {orders.map((order) => (
              <li key={order.id} className="card flex items-center justify-between gap-4 px-5 py-4">
                <Link href={`/orders/${order.id}`} className="min-w-0 flex-1">
                  <p className="font-medium">{statusLabel(order.status)}</p>
                  <p className="mt-1 truncate text-xs text-slate-500">{order.id}</p>
                </Link>
                <p className="font-semibold">{money(order.total_amount, order.currency)}</p>
                <div className="flex shrink-0 items-center gap-2">
                  {canCancel(order.status) ? (
                    <button
                      type="button"
                      className="btn btn-danger btn-sm"
                      disabled={cancellingId === order.id || deletingId === order.id}
                      onClick={() => void cancelOrder(order.id)}
                    >
                      {cancellingId === order.id ? "..." : "Отменить"}
                    </button>
                  ) : null}
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    disabled={deletingId === order.id || cancellingId === order.id}
                    onClick={() => void deleteOrder(order.id)}
                  >
                    {deletingId === order.id ? "..." : "Удалить"}
                  </button>
                </div>
              </li>
            ))}
          </ul>
        ) : null}
      </main>
    </div>
  );
}
