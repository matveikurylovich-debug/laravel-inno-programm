"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import CardPayment from "@/app/components/CardPayment";
import { apiFetch, readError } from "@/app/lib/api";
import { canCancel, isFinalStatus, money, statusLabel, type Order } from "@/app/lib/orders";

export default function OrderPage() {
  const params = useParams<{ id: string }>();
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState("");
  const [cancelling, setCancelling] = useState(false);

  const load = useCallback(async (): Promise<Order | null> => {
    const response = await apiFetch(`/api/orders/${params.id}`);
    if (!response.ok) {
      setError(await readError(response));
      return null;
    }

    const body = (await response.json()) as { data: Order };
    setOrder(body.data);
    setError("");

    return body.data;
  }, [params.id]);

  useEffect(() => {
    let timer = 0;
    let stopped = false;

    async function poll() {
      const current = await load().catch(() => {
        setError("Не удалось загрузить заказ");
        return null;
      });

      if (stopped || (current && isFinalStatus(current.status))) {
        return;
      }

      timer = window.setTimeout(() => void poll(), 2000);
    }

    void poll();

    return () => {
      stopped = true;
      window.clearTimeout(timer);
    };
  }, [load]);

  async function cancelOrder() {
    if (!order || cancelling) {
      return;
    }

    setCancelling(true);
    setError("");
    const response = await apiFetch(`/api/orders/${order.id}/cancel`, { method: "POST" });
    setCancelling(false);

    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    await load();
  }

  const clientSecret = order?.payment?.stripe_client_secret;
  const waitingForPayment = order?.status === "payment_pending" && Boolean(clientSecret);
  const waitingForSaga = order !== null && !isFinalStatus(order.status) && !waitingForPayment;

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <Link href="/orders" className="btn btn-ghost btn-sm -ml-3">← Все заказы</Link>
        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {order ? (
          <article className="card mt-4 p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h1 className="page-title">{statusLabel(order.status)}</h1>
                <p className="page-subtitle">{order.id}</p>
              </div>
              <p className="text-xl font-semibold">{money(order.total_amount, order.currency)}</p>
            </div>

            <ul className="mt-6 divide-y divide-slate-100">
              {(order.items ?? []).map((item) => (
                <li key={item.id} className="flex justify-between py-3 text-sm">
                  <span>{item.product_name} × {item.quantity}</span>
                  <span>{money(item.subtotal, order.currency)}</span>
                </li>
              ))}
            </ul>

            {waitingForSaga ? <p className="mt-4 text-sm text-slate-500">Обновляем статус заказа...</p> : null}

            {canCancel(order.status) ? (
              <button
                type="button"
                className="btn btn-danger mt-6"
                disabled={cancelling}
                onClick={() => void cancelOrder()}
              >
                {cancelling ? "Отменяем..." : "Отменить заказ"}
              </button>
            ) : null}

            {waitingForPayment && clientSecret ? (
              <div className="mt-6 border-t border-slate-100 pt-4">
                <h2 className="text-lg font-semibold">Оплата картой</h2>
                <CardPayment orderId={order.id} clientSecret={clientSecret} onPaid={() => void load()} />
              </div>
            ) : null}
          </article>
        ) : null}
      </main>
    </div>
  );
}
