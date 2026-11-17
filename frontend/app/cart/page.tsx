"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { readCart, setQuantity, type CartLine } from "@/app/lib/cart";
import { money } from "@/app/lib/orders";

export default function CartPage() {
  const [lines, setLines] = useState<CartLine[]>([]);

  useEffect(() => {
    queueMicrotask(() => setLines(readCart()));
  }, []);

  function changeQuantity(productId: number, locationId: number, quantity: number) {
    setQuantity(productId, locationId, quantity);
    setLines(readCart());
  }

  const total = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 className="page-title">Корзина</h1>
        <p className="page-subtitle">Товары хранятся в этом браузере до оформления заказа.</p>

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
                  <div className="flex items-end gap-3">
                    <label className="text-sm text-slate-500">
                      Кол-во
                      <input
                        className="input mt-1 w-20"
                        type="number"
                        min={0}
                        value={line.quantity}
                        onChange={(event) => changeQuantity(line.productId, line.locationId, Number(event.target.value))}
                      />
                    </label>
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      onClick={() => changeQuantity(line.productId, line.locationId, 0)}
                    >
                      Убрать
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-4 flex items-center justify-between">
              <p className="text-lg font-semibold">{money(total)}</p>
              <Link href="/checkout" className="btn btn-primary">Оформить заказ</Link>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
