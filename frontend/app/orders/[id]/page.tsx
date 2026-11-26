"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import NotFoundState from "@/app/components/NotFoundState";
import CardPayment from "@/app/components/CardPayment";
import { ApiError } from "@/app/lib/api";
import { cancelOrderById, getOrder, type Order } from "@/app/lib/api/orders";
import { formatMoney } from "@/app/lib/format";
import { isMissingResourceMessage } from "@/app/lib/not-found";
import { isFinalStatus, statusLabel } from "@/app/lib/orders";
import { canCancelOrder } from "@/app/lib/permissions";
import { useSession } from "@/app/providers/SessionProvider";

const MAX_TRANSIENT_POLL_ERRORS = 3;

export default function OrderPage() {
  const params = useParams<{ id: string }>();
  const { user } = useSession();
  const [order, setOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [cancelling, setCancelling] = useState(false);

  const loadOrder = useCallback(
    async (signal?: AbortSignal): Promise<{ order: Order | null; fatal: boolean }> => {
      try {
        const fetched = await getOrder(params.id, signal);
        setOrder(fetched);
        setError("");
        setLoading(false);
        return { order: fetched, fatal: false };
      } catch (reason: unknown) {
        if (reason instanceof Error && reason.name === "AbortError") {
          return { order: null, fatal: true };
        }
        setLoading(false);
        if (reason instanceof ApiError) {
          setError(reason.message);
          const unrecoverable =
            reason.status >= 400 && reason.status < 500 && reason.status !== 429;
          return { order: null, fatal: unrecoverable };
        }
        setError("Не удалось загрузить заказ");
        return { order: null, fatal: false };
      }
    },
    [params.id],
  );

  useEffect(() => {
    const controller = new AbortController();
    let timer = 0;
    let stopped = false;
    let transientErrors = 0;

    async function poll() {
      const { order: current, fatal } = await loadOrder(controller.signal);

      if (stopped || controller.signal.aborted || fatal) {
        return;
      }

      if (!current) {
        transientErrors += 1;
        if (transientErrors >= MAX_TRANSIENT_POLL_ERRORS) {
          return;
        }
      } else {
        transientErrors = 0;
        if (isFinalStatus(current.status)) {
          return;
        }
      }

      timer = window.setTimeout(() => {
        void poll();
      }, 2000);
    }

    void poll();

    return () => {
      stopped = true;
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [loadOrder]);

  async function handleCancelOrder() {
    if (!order || cancelling) {
      return;
    }

    setCancelling(true);
    setError("");

    try {
      const updated = await cancelOrderById(order.id);
      setOrder((prev) => (prev ? { ...prev, ...updated } : updated));
    } catch (reason: unknown) {
      setError(reason instanceof Error ? reason.message : "Не удалось отменить заказ");
    } finally {
      setCancelling(false);
    }
  }

  if (!loading && !order && isMissingResourceMessage(error)) {
    return <NotFoundState />;
  }

  const clientSecret = order?.payment?.stripe_client_secret;
  const waitingForPayment = order?.status === "payment_pending" && Boolean(clientSecret);
  const waitingForSaga =
    order !== null && !isFinalStatus(order.status) && !waitingForPayment && !error;

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <Link href="/orders" className="btn btn-ghost btn-sm -ml-3">
          ← Все заказы
        </Link>
        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}
        {loading && !order ? (
          <p className="mt-6 text-sm text-slate-500">Загружаем заказ...</p>
        ) : null}
        {order ? (
          <article className="card mt-4 p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h1 className="page-title">{statusLabel(order.status)}</h1>
                <p className="page-subtitle">{order.id}</p>
              </div>
              <p className="text-xl font-semibold">
                {formatMoney(order.total_amount, order.currency)}
              </p>
            </div>

            <ul className="mt-6 divide-y divide-slate-100">
              {(order.items ?? []).map((item) => (
                <li key={item.id} className="flex justify-between py-3 text-sm">
                  <span>
                    {item.product_name} × {item.quantity}
                  </span>
                  <span>{formatMoney(item.subtotal, order.currency)}</span>
                </li>
              ))}
            </ul>

            {waitingForSaga ? (
              <p className="mt-4 text-sm text-slate-500">Обновляем статус заказа...</p>
            ) : null}

            {canCancelOrder(user, order) ? (
              <button
                type="button"
                className="btn btn-danger mt-6"
                disabled={cancelling}
                onClick={() => {
                  void handleCancelOrder();
                }}
              >
                {cancelling ? "Отменяем..." : "Отменить заказ"}
              </button>
            ) : null}

            {waitingForPayment && clientSecret ? (
              <div className="mt-6 border-t border-slate-100 pt-4">
                <h2 className="text-lg font-semibold">Оплата картой</h2>
                <CardPayment
                  orderId={order.id}
                  clientSecret={clientSecret}
                  publishableKey={order.payment?.publishable_key ?? ""}
                  onPaid={() => {
                    void loadOrder();
                  }}
                />
              </div>
            ) : null}
          </article>
        ) : null}
      </main>
    </div>
  );
}
