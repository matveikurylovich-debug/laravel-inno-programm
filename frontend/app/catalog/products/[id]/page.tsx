"use client";

import { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { useParams } from "next/navigation";
import AppHeader from "@/app/components/AppHeader";
import NotFoundState from "@/app/components/NotFoundState";
import QuantityInput from "@/app/components/QuantityInput";
import { getProduct, type CatalogProduct } from "@/app/lib/api/catalog";
import { addToCart } from "@/app/lib/cart";
import { formatMoney } from "@/app/lib/format";
import { isMissingResourceMessage } from "@/app/lib/not-found";
import { useAsyncQuery } from "@/app/lib/use-async-query";

function ProductCardView({ product }: { product: CatalogProduct }) {
  const images = product.images ?? [];
  const primary = images.find((image) => image.is_primary) ?? images[0];
  const locations = product.locations ?? [];
  const preferredLocation =
    locations.find((location) => location.is_in_stock) ?? locations[0] ?? null;

  const [photo, setPhoto] = useState(primary?.url ?? "");
  const [quantity, setQuantity] = useState(1);
  const [locationId, setLocationId] = useState<number | null>(
    preferredLocation?.location_id ?? null,
  );
  const [added, setAdded] = useState(false);

  const selected = locations.find((location) => location.location_id === locationId) ?? null;
  const stock = selected?.available ?? product.total_available ?? product.available_stock ?? 0;
  const maxSelectable = Math.max(1, Math.min(1000, stock || 1000));
  const attributes = Object.entries(product.attributes ?? {});

  return (
    <article className="mt-6 grid items-start gap-8 lg:grid-cols-2">
      <div>
        <div className="card relative flex h-[420px] items-center justify-center overflow-hidden rounded-3xl">
          {photo ? (
            <Image
              src={photo}
              alt={product.name}
              fill
              sizes="(max-width: 1024px) 100vw, 50vw"
              className="object-contain"
            />
          ) : (
            <span className="text-6xl font-semibold text-brand-200">
              {product.name.slice(0, 1)}
            </span>
          )}
        </div>
        {images.length > 1 ? (
          <div className="mt-3 flex flex-wrap gap-2">
            {images.map((image, index) => (
              <button
                key={image.url}
                type="button"
                aria-label={`Показать фото ${index + 1} товара ${product.name}`}
                aria-pressed={photo === image.url}
                onClick={() => setPhoto(image.url)}
                className={`relative h-16 w-16 overflow-hidden rounded-xl border-2 bg-white transition ${
                  photo === image.url
                    ? "border-brand-500 shadow-brand"
                    : "border-slate-200 hover:border-brand-300"
                }`}
              >
                <Image
                  src={image.url}
                  alt={`${product.name} — миниатюра ${index + 1}`}
                  fill
                  sizes="64px"
                  className="object-cover"
                />
              </button>
            ))}
          </div>
        ) : null}
      </div>

      <div className="card rounded-3xl p-6 sm:p-8">
        <h1 className="text-3xl font-semibold tracking-tight">{product.name}</h1>
        <p className="mt-4 bg-gradient-to-r from-brand-600 to-brand-800 bg-clip-text text-3xl font-bold tracking-tight text-transparent">
          {formatMoney(product.price, product.currency ?? "USD")}
        </p>
        <p className={`badge mt-3 ${stock > 0 ? "badge-success" : ""}`}>
          {stock > 0 ? `В выбранной точке: ${stock}` : "Нет в наличии"}
        </p>
        <div className="mt-6 space-y-2">
          <p className="text-sm font-medium text-slate-700">Наличие в магазинах</p>
          {locations.length === 0 ? (
            <p className="text-sm text-slate-500">У этого магазина нет точек самовывоза</p>
          ) : (
            locations.map((location) => (
              <label
                key={location.location_id}
                className={`flex cursor-pointer items-start gap-3 rounded-2xl border px-4 py-3 ${
                  locationId === location.location_id
                    ? "border-brand-500 bg-brand-50"
                    : "border-slate-200"
                }`}
              >
                <input
                  type="radio"
                  name="pickup"
                  className="mt-1"
                  checked={locationId === location.location_id}
                  onChange={() => setLocationId(location.location_id)}
                />
                <span className="min-w-0 flex-1">
                  <span className="block font-medium">{location.name}</span>
                  <span className="block text-sm text-slate-600">
                    {location.city}, {location.address}
                  </span>
                  <span className="block text-sm text-slate-500">
                    {location.working_hours}
                    {location.phone ? ` · ${location.phone}` : ""}
                  </span>
                </span>
                <span className={`badge ${location.is_in_stock ? "badge-success" : ""}`}>
                  {location.is_in_stock ? `${location.available} шт.` : "Нет в наличии"}
                </span>
              </label>
            ))
          )}
        </div>
        <div className="mt-6 flex flex-wrap items-end gap-3">
          <QuantityInput
            label="Количество"
            value={quantity}
            max={maxSelectable}
            onChange={setQuantity}
            className="input w-24"
          />
          <button
            type="button"
            className="btn btn-primary"
            disabled={!product.store_id || !selected || selected.available < quantity}
            onClick={() => {
              if (!product.store_id || !selected || selected.available < quantity) {
                return;
              }
              addToCart({
                productId: product.id,
                storeId: product.store_id,
                locationId: selected.location_id,
                locationLabel: `${selected.name}, ${selected.address}`,
                name: product.name,
                price: Number(product.price),
                currency: product.currency ?? "USD",
                quantity,
              });
              setAdded(true);
            }}
          >
            В корзину
          </button>
          {added ? (
            <span className="text-sm text-emerald-700">
              Товар добавлен в корзину.{" "}
              <Link href="/cart" className="link">
                Перейти в корзину
              </Link>
            </span>
          ) : null}
        </div>
        {product.description ? (
          <p className="mt-6 leading-7 text-slate-600">{product.description}</p>
        ) : null}
        {attributes.length > 0 ? (
          <dl className="mt-8 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/60 px-4">
            {attributes.map(([name, value]) => (
              <div key={name} className="grid grid-cols-2 gap-4 py-3 text-sm">
                <dt className="text-slate-500">{name}</dt>
                <dd className="text-right font-medium">{String(value)}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </div>
    </article>
  );
}

export default function ProductPage() {
  const params = useParams<{ id: string }>();
  const productQuery = useAsyncQuery(
    (signal) => getProduct(params.id, signal),
    `product:${params.id}`,
    { fallbackError: "Не удалось загрузить товар" },
  );

  if (productQuery.error && isMissingResourceMessage(productQuery.error)) {
    return <NotFoundState />;
  }

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <Link href="/catalog" className="btn btn-ghost btn-sm -ml-3">
          ← Назад в витрину
        </Link>
        {productQuery.error ? (
          <p role="alert" className="alert alert-error mt-4">
            {productQuery.error}
          </p>
        ) : null}
        {productQuery.isLoading ? (
          <p className="mt-6 text-sm text-slate-500">Загружаем карточку товара...</p>
        ) : null}
        {productQuery.data ? (
          <ProductCardView key={productQuery.data.id} product={productQuery.data} />
        ) : null}
      </main>
    </div>
  );
}
