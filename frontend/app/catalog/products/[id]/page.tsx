"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type ProductImage = { url: string; is_primary: boolean };
type ProductCard = {
  id: number;
  name: string;
  description?: string | null;
  price: string;
  attributes?: Record<string, string | number | boolean>;
  available_stock?: number;
  images?: ProductImage[];
};

function money(value: string): string {
  const number = Number(value);
  if (Number.isNaN(number)) {
    return value;
  }
  return new Intl.NumberFormat("ru-RU", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(number);
}

export default function ProductPage() {
  const params = useParams<{ id: string }>();
  const [product, setProduct] = useState<ProductCard | null>(null);
  const [error, setError] = useState("");
  const [photo, setPhoto] = useState("");

  useEffect(() => {
    apiFetch(`/api/catalog/v1/products/${params.id}`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        const body = (await response.json()) as { data: ProductCard };
        const images = body.data.images ?? [];
        const primary = images.find((image) => image.is_primary) ?? images[0];
        setProduct(body.data);
        setPhoto(primary?.url ?? "");
      })
      .catch(() => setError("Не удалось загрузить товар"));
  }, [params.id]);

  const stock = product?.available_stock ?? 0;
  const attributes = Object.entries(product?.attributes ?? {});

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        <Link href="/catalog" className="btn btn-ghost btn-sm -ml-3">
          ← Назад в витрину
        </Link>
        {error ? <p className="alert alert-error mt-4">{error}</p> : null}
        {product ? (
          <article className="mt-6 grid items-start gap-8 lg:grid-cols-2">
            <div>
              <div className="card flex h-[420px] items-center justify-center overflow-hidden rounded-3xl">
                {photo ? (
                  <img src={photo} alt="" className="h-full w-full object-contain" />
                ) : (
                  <span className="text-6xl font-semibold text-brand-200">{product.name.slice(0, 1)}</span>
                )}
              </div>
              {(product.images ?? []).length > 1 ? (
                <div className="mt-3 flex gap-2">
                  {product.images?.map((image) => (
                    <button
                      key={image.url}
                      type="button"
                      onClick={() => setPhoto(image.url)}
                      className={`h-16 w-16 overflow-hidden rounded-xl border-2 bg-white transition ${
                        photo === image.url ? "border-brand-500 shadow-brand" : "border-slate-200 hover:border-brand-300"
                      }`}
                    >
                      <img src={image.url} alt="" className="h-full w-full object-cover" />
                    </button>
                  ))}
                </div>
              ) : null}
            </div>

            <div className="card rounded-3xl p-6 sm:p-8">
              <h1 className="text-3xl font-semibold tracking-tight">{product.name}</h1>
              <p className="mt-4 bg-gradient-to-r from-brand-600 to-brand-800 bg-clip-text text-3xl font-bold tracking-tight text-transparent">{money(product.price)}</p>
              <p className={`badge mt-3 ${stock > 0 ? "badge-success" : ""}`}>
                {stock > 0 ? `В наличии: ${stock}` : "Нет в наличии"}
              </p>
              {product.description ? <p className="mt-6 leading-7 text-slate-600">{product.description}</p> : null}
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
        ) : null}
      </main>
    </div>
  );
}
