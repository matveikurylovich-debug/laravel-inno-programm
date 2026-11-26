"use client";

import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import QuantityInput from "@/app/components/QuantityInput";
import { removeLine, setQuantity } from "@/app/lib/cart";
import { formatMoney } from "@/app/lib/format";
import { useCart } from "@/app/lib/use-cart";

export default function CartPage() {
  const lines = useCart();

  const currency = lines[0]?.currency ?? "USD";
  const total = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <h1 className="page-title">Корзина</h1>
        <p className="page-subtitle">Товары хранятся в этом браузере до оформления заказа.</p>

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
                      onChange={(nextQuantity) =>
                        setQuantity(line.productId, line.locationId, nextQuantity)
                      }
                    />
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm"
                      onClick={() => removeLine(line.productId, line.locationId)}
                    >
                      Убрать
                    </button>
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-4 flex items-center justify-between">
              <p className="text-lg font-semibold">{formatMoney(total, currency)}</p>
              <Link href="/checkout" className="btn btn-primary">
                Оформить заказ
              </Link>
            </div>
          </section>
        )}
      </main>
    </div>
  );
}
