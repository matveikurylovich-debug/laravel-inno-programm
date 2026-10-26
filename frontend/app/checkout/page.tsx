"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";
import { clearCart, readCart, setQuantity, type CartLine } from "@/app/lib/cart";
import { money } from "@/app/lib/orders";

export default function CheckoutPage() {
  const router = useRouter();
  const [lines, setLines] = useState<CartLine[]>([]);
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);
  const idempotencyKey = useRef<string>("");

  useEffect(() => {
    setLines(readCart());
  }, []);

  function changeQuantity(productId: number, locationId: number, quantity: number) {
    setQuantity(productId, locationId, quantity);
    setLines(readCart());
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

    for (const [key, group] of groups) {
      const response = await apiFetch("/api/orders", {
        method: "POST",
        headers: { "Idempotency-Key": `${idempotencyKey.current}:${key}` },
        body: JSON.stringify({
          store_id: group[0].storeId,
          location_id: group[0].locationId,
          items: group.map((line) => ({
            product_id: line.productId,
            quantity: line.quantity,
          })),
        }),
      });

      if (!response.ok) {
        setPending(false);
        setError(await readError(response));
        idempotencyKey.current = "";
        return;
      }

      const body = (await response.json()) as { data: { id: string } };
      created.push(body.data.id);
    }

    setPending(false);
    clearCart();
    router.push(created.length === 1 ? `/orders/${created[0]}` : "/orders");
  }

  const total = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 className="page-title">Оформление заказа</h1>
        <p className="page-subtitle">Количество можно изменить перед отправкой. Повтор того же запроса использует один Idempotency-Key.</p>

        {error ? <p className="alert alert-error mt-4">{error}</p> : null}

        {lines.length === 0 ? (
          <div className="card mt-6 px-6 py-12 text-center text-sm text-slate-500">
            Корзина пуста. <Link href="/catalog" className="text-brand-700">Перейти в каталог</Link>
          </div>
        ) : (
          <section className="card mt-6 p-6">
            <ul className="divide-y divide-slate-100">
              {lines.map((line) => (
                <li key={`${line.productId}-${line.locationId}`} className="flex items-center justify-between gap-4 py-4">
                  <div>
                    <p className="font-medium">{line.name}</p>
                    <p className="text-sm text-slate-500">{money(line.price)}</p>
                    <p className="text-sm text-slate-500">Самовывоз: {line.locationLabel}</p>
                  </div>
                  <label className="text-sm text-slate-500">
                    Кол-во
                    <input
                      className="input mt-1 w-20"
                      type="number"
                      min={1}
                      value={line.quantity}
                      onChange={(event) => changeQuantity(line.productId, line.locationId, Number(event.target.value))}
                    />
                  </label>
                </li>
              ))}
            </ul>
            <div className="mt-4 flex items-center justify-between">
              <p className="text-lg font-semibold">{money(total)}</p>
              <button type="button" className="btn btn-primary" disabled={pending} onClick={() => void placeOrder()}>
                {pending ? "Отправляем..." : "Оформить заказ"}
              </button>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
