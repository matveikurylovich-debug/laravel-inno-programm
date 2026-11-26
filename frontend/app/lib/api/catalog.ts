import { requestJson } from "@/app/lib/api";
import type { PaginationMeta } from "@/app/lib/types";

export type Store = {
  id: number;
  name: string;
  slug?: string;
};

export type Category = {
  id: number;
  name: string;
  all_children?: Category[];
};

export type ProductImage = {
  url: string;
  is_primary: boolean;
};

export type PickupLocation = {
  location_id: number;
  store_id: number;
  name: string;
  city: string;
  address: string;
  phone?: string | null;
  working_hours: string;
  available: number;
  is_in_stock: boolean;
};

export type CatalogProduct = {
  id: number;
  store_id?: number;
  name: string;
  description?: string | null;
  price: string;
  currency?: string;
  attributes?: Record<string, string | number | boolean>;
  total_available?: number;
  available_stock?: number;
  primary_image?: { url: string } | null;
  images?: ProductImage[];
  locations?: PickupLocation[];
};

export type PaginatedProducts = {
  data: CatalogProduct[];
  meta: PaginationMeta;
};

export async function listStores(signal?: AbortSignal): Promise<Store[]> {
  const body = await requestJson<{ data?: Store[] } | Store[]>("/api/catalog/v1/stores", {
    signal,
  });
  return Array.isArray(body) ? body : (body.data ?? []);
}

export async function getCategoryTree(storeId: number, signal?: AbortSignal): Promise<Category[]> {
  const body = await requestJson<{ data?: Category[] }>(
    `/api/catalog/v1/stores/${storeId}/categories/tree`,
    { signal },
  );
  return body.data ?? [];
}

export async function listStoreProducts(
  storeId: number,
  params: { category_id?: number | null; page?: number; per_page?: number } = {},
  signal?: AbortSignal,
): Promise<PaginatedProducts> {
  const search = new URLSearchParams({
    page: String(params.page ?? 1),
    per_page: String(params.per_page ?? 12),
  });
  if (params.category_id) {
    search.set("category_id", String(params.category_id));
  }

  const body = await requestJson<{
    data?: CatalogProduct[];
    meta?: PaginationMeta;
    current_page?: number;
    last_page?: number;
    total?: number;
    per_page?: number;
  }>(`/api/catalog/v1/stores/${storeId}/products?${search.toString()}`, { signal });

  const list = body.data ?? [];
  const meta: PaginationMeta = body.meta ?? {
    current_page: body.current_page ?? params.page ?? 1,
    last_page: body.last_page ?? 1,
    total: body.total ?? list.length,
    per_page: body.per_page ?? params.per_page ?? 12,
  };

  return { data: list, meta };
}

export async function getProduct(
  productId: number | string,
  signal?: AbortSignal,
): Promise<CatalogProduct> {
  const body = await requestJson<{ data: CatalogProduct }>(
    `/api/catalog/v1/products/${encodeURIComponent(String(productId))}`,
    { signal },
  );
  return body.data;
}
