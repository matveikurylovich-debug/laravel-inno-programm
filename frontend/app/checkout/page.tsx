"use client";

import { useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import QuantityInput from "@/app/components/QuantityInput";
import { ApiError } from "@/app/lib/api";
import { createOrder } from "@/app/lib/api/orders";
import { removeLine, removeLines, setQuantity, type CartLine } from "@/app/lib/cart";
import { formatMoney } from "@/app/lib/format";
import { useCart } from "@/app/lib/use-cart";

export default function CheckoutPage() {
  const router = useRouter();
  const lines = useCart();
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const idempotencyKey = useRef<string>("");

  function changeQuantity(productId: number, locationId: number, quantity: number) {
    setQuantity(productId, locationId, quantity);
    idempotencyKey.current = "";
  }

  function removeItem(productId: number, locationId: number) {
    removeLine(productId, locationId);
    idempotencyKey.current = "";
  }

  async function placeOrder() {
    if (lines.length === 0) {
      return;
    }

    if (!idempotencyKey.current) {
      idempotencyKey.current = crypto.randomUUID();
    }

    const groups = new Map<string, CartLine[]>();
    for (const line of lines) {
      const key = `${line.storeId}:${line.locationId}`;
      groups.set(key, [...(groups.get(key) ?? []), line]);
    }

    setPending(true);
    setError("");

    const created: string[] = [];

    try {
      for (const [key, group] of groups) {
        const order = await createOrder(
          {
            store_id: group[0].storeId,
            location_id: group[0].locationId,
            items: group.map((line) => ({
              product_id: line.productId,
              quantity: line.quantity,
            })),
          },
          `${idempotencyKey.current}:${key}`,
        );

        created.push(order.id);
        removeLines(group);
      }

      router.push(created.length === 1 ? `/orders/${created[0]}` : "/orders");
    } catch (reason: unknown) {
      if (reason instanceof ApiError) {
        setError(reason.message);
      } else {
        setError("Нет связи с сервером. Повторите попытку — заказ не задублируется.");
      }
    } finally {
      setPending(false);
    }
  }

  const currency = lines[0]?.currency ?? "USD";
  const total = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 className="page-title">Оформление заказа</h1>
        <p className="page-subtitle">
          Проверьте выбранные позиции перед подтверждением.{" "}
          <Link href="/cart" className="text-brand-700">
            Вернуться в корзину
          </Link>
        </p>

        {error ? (
          <p role="alert" className="alert alert-error mt-4">
            {error}
          </p>
        ) : null}

        {lines.length === 0 ? (
          <div className="card mt-6 px-6 py-12 text-center text-sm text-slate-500">
            Корзина пуста.{" "}
            <Link href="/catalog" className="text-brand-700">
              Перейти в каталог
            </Link>
          </div>
        ) : (
          <section className="card mt-6 p-6">
            <ul className="divide-y divide-slate-100">
              {lines.map((line) => (
                <li
                  key={`${line.productId}-${line.locationId}`}
                  className="flex flex-wrap items-center justify-between gap-4 py-4"
                >
                  <div>
                    <p className="font-medium">{line.name}</p>
                    <p className="text-sm text-slate-500">
                      {formatMoney(line.price, line.currency ?? currency)}
                    </p>
                    <p className="text-sm text-slate-500">Самовывоз: {line.locationLabel}</p>
                  </div>
                  <div className="flex items-end gap-3">
                    <QuantityInput
                      label="Кол-во"
                      value={line.quantity}
                      disabled={pending}
                      onChange={(nextQuantity) =>
                        changeQuantity(line.productId, line.locationId, nextQuantity)
                      }
                    />
                    <button
                      type="button"
                      disabled={pending}
                      className="btn btn-ghost btn-sm"
                      onClick={() => removeItem(line.productId, line.locationId)}
                    >
                      Убрать
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 pt-4">
              <div>
                <p className="text-lg font-semibold">{formatMoney(total, currency)}</p>
                <p className="mt-0.5 text-xs text-slate-500">Итоговую сумму рассчитает сервер</p>
              </div>
              <button
                type="button"
                className="btn btn-primary"
                disabled={pending}
                onClick={() => {
                  void placeOrder();
                }}
              >
                {pending ? "Отправляем..." : "Оформить заказ"}
              </button>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
