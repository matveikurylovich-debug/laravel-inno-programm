"use client";

import { useState } from "react";
import Link from "next/link";
import Pagination from "@/app/components/Pagination";
import { cancelOrderById, listOrders } from "@/app/lib/api/orders";
import { formatMoney } from "@/app/lib/format";
import { statusLabel } from "@/app/lib/orders";
import { canCancelOrder } from "@/app/lib/permissions";
import { useAsyncQuery } from "@/app/lib/use-async-query";
import { useSession } from "@/app/providers/SessionProvider";

export default function AdminOrdersPage() {
  const { user } = useSession();
  const [page, setPage] = useState(1);
  const [actionError, setActionError] = useState("");
  const [cancellingId, setCancellingId] = useState<string | null>(null);

  const ordersQuery = useAsyncQuery(
    (signal) => listOrders({ page }, signal),
    `admin:orders:${page}`,
    { fallbackError: "Не удалось загрузить заказы" },
  );

  async function cancelOrder(id: string) {
    setCancellingId(id);
    setActionError("");

    try {
      const updated = await cancelOrderById(id);
      ordersQuery.mutate((current) =>
        current
          ? {
              ...current,
              data: current.data.map((order) =>
                order.id === id ? { ...order, ...updated } : order,
              ),
            }
          : current,
      );
    } catch (reason: unknown) {
      setActionError(reason instanceof Error ? reason.message : "Не удалось отменить заказ");
    } finally {
      setCancellingId(null);
    }
  }

  const orders = ordersQuery.data?.data ?? [];
  const meta = ordersQuery.data?.meta;
  const error = actionError || ordersQuery.error;

  return (
    <section>
      <h1 className="page-title">Все заказы</h1>
      <p className="page-subtitle">Заказы всех покупателей интернет-магазина.</p>

      {error ? (
        <p role="alert" className="alert alert-error mt-4">
          {error}
        </p>
      ) : null}

      {ordersQuery.isLoading ? (
        <div className="card mt-6 px-6 py-12 text-center text-sm text-slate-500">
          Загружаем заказы...
        </div>
      ) : orders.length === 0 && !error ? (
        <div className="card mt-6 px-6 py-12 text-center text-sm text-slate-500">
          Заказов пока нет.
        </div>
      ) : null}

      {orders.length > 0 ? (
        <>
          <ul className="mt-6 space-y-3">
            {orders.map((order) => (
              <li key={order.id} className="card flex items-center justify-between gap-4 px-5 py-4">
                <Link href={`/orders/${order.id}`} className="min-w-0 flex-1">
                  <p className="font-medium">{statusLabel(order.status)}</p>
                  <p className="mt-1 truncate text-xs text-slate-500">{order.id}</p>
                </Link>
                <p className="font-semibold">{formatMoney(order.total_amount, order.currency)}</p>
                {canCancelOrder(user, order) ? (
                  <button
                    type="button"
                    className="btn btn-danger btn-sm"
                    disabled={cancellingId === order.id}
                    onClick={() => {
                      void cancelOrder(order.id);
                    }}
                  >
                    {cancellingId === order.id ? "..." : "Отменить"}
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
          <Pagination
            currentPage={meta?.current_page ?? page}
            lastPage={meta?.last_page ?? 1}
            onPageChange={setPage}
          />
        </>
      ) : null}
    </section>
  );
}
