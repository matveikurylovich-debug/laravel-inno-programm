import { requestJson } from "@/app/lib/api";
import type { PaginationMeta } from "@/app/lib/types";

export type OrderItem = {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: number | string;
  subtotal: number | string;
};

export type OrderPayment = {
  status: string;
  stripe_client_secret: string | null;
  publishable_key?: string | null;
};

export type Order = {
  id: string;
  user_id?: number;
  email?: string | null;
  store_id?: number;
  location_id?: number | null;
  status: string;
  payment_status: string;
  total_amount: number | string;
  currency: string;
  created_at?: string;
  items?: OrderItem[];
  payment?: OrderPayment | null;
};

export type PaginatedOrders = {
  data: Order[];
  meta: PaginationMeta;
};

export type CreateOrderPayload = {
  store_id: number;
  location_id: number;
  items: Array<{
    product_id: number;
    quantity: number;
  }>;
};

export async function listOrders(
  params: { page?: number; per_page?: number } = {},
  signal?: AbortSignal,
): Promise<PaginatedOrders> {
  const search = new URLSearchParams();
  if (params.page && params.page > 1) {
    search.set("page", String(params.page));
  }
  if (params.per_page) {
    search.set("per_page", String(params.per_page));
  }

  const query = search.toString();
  const body = await requestJson<{
    data?: Order[];
    meta?: PaginationMeta;
    current_page?: number;
    last_page?: number;
    total?: number;
    per_page?: number;
  }>(`/api/orders${query ? `?${query}` : ""}`, { signal });

  const list = body.data ?? [];
  const meta: PaginationMeta = body.meta ?? {
    current_page: body.current_page ?? params.page ?? 1,
    last_page: body.last_page ?? 1,
    total: body.total ?? list.length,
    per_page: body.per_page ?? params.per_page ?? 15,
  };

  return { data: list, meta };
}

export async function getOrder(orderId: string, signal?: AbortSignal): Promise<Order> {
  const body = await requestJson<{ data: Order }>(`/api/orders/${encodeURIComponent(orderId)}`, {
    signal,
  });
  return body.data;
}

export async function createOrder(
  payload: CreateOrderPayload,
  idempotencyKey: string,
  signal?: AbortSignal,
): Promise<Order> {
  const body = await requestJson<{ data: Order }>("/api/orders", {
    method: "POST",
    headers: { "Idempotency-Key": idempotencyKey },
    body: JSON.stringify(payload),
    signal,
  });
  return body.data;
}

export async function cancelOrderById(orderId: string, signal?: AbortSignal): Promise<Order> {
  const body = await requestJson<{ data: Order }>(
    `/api/orders/${encodeURIComponent(orderId)}/cancel`,
    {
      method: "POST",
      signal,
    },
  );
  return body.data;
}
