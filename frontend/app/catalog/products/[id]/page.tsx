"use client";

import { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import Link from "next/link";
import AppHeader from "@/app/components/AppHeader";
import { apiFetch, readError } from "@/app/lib/api";

type ProductCard = {
  id: number;
  name: string;
  description?: string | null;
  price: string;
  attributes?: Record<string, string | number | boolean>;
  available_stock?: number;
};

export default function ProductPage() {
  const params = useParams<{ id: string }>();
  const [product, setProduct] = useState<ProductCard | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    apiFetch(`/api/catalog/v1/products/${params.id}`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          return;
        }
        const body = (await response.json()) as { data: ProductCard };
        setProduct(body.data);
      })
      .catch(() => setError("Не удалось загрузить товар"));
  }, [params.id]);

  return (
    <div className="min-h-screen">
      <AppHeader />
      <main className="mx-auto max-w-3xl px-6 py-8">
        <Link href="/catalog" className="text-sm text-slate-500">
          Назад в витрину
        </Link>
        {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}
        {product ? (
          <article className="mt-4">
            <h1 className="text-3xl font-semibold">{product.name}</h1>
            <p className="mt-2 text-lg">{product.price}</p>
            <p className="mt-2 text-sm text-slate-500">В наличии: {product.available_stock ?? 0}</p>
            {product.description ? <p className="mt-4 text-slate-700">{product.description}</p> : null}
          </article>
        ) : null}
      </main>
    </div>
  );
}
