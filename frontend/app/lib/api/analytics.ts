import { requestJson } from "@/app/lib/api";

export type AnalyticsPoint = {
  period: string;
  value: number;
  count: number;
};

export type UsersReport = {
  total: number;
  series: AnalyticsPoint[];
};

export type OrdersReport = {
  funnel: {
    created: number;
    paid: number;
    completed: number;
    cancelled: number;
  };
  conversion: {
    created_to_paid: number;
    created_to_cancelled: number;
  };
  revenue: number;
  average_check: number;
  currency?: string;
  series: AnalyticsPoint[];
};

export type SearchRow = {
  query: string;
  searches: number;
  avg_results?: number;
};

export type SearchReport = {
  popular: SearchRow[];
  without_results: SearchRow[];
};

export type AnalyticsDashboard = {
  users: UsersReport;
  orders: OrdersReport;
  search: SearchReport;
};

export type AnalyticsParams = {
  from: string;
  to: string;
  granularity: string;
};

export async function getAnalyticsDashboard(
  params: AnalyticsParams,
  signal?: AbortSignal,
): Promise<AnalyticsDashboard> {
  const query = new URLSearchParams({
    from: params.from,
    to: params.to,
    granularity: params.granularity,
  }).toString();

  const [users, orders, search] = await Promise.all([
    requestJson<UsersReport>(`/api/analytics/users?${query}`, { signal }),
    requestJson<OrdersReport>(`/api/analytics/orders?${query}`, { signal }),
    requestJson<SearchReport>(`/api/analytics/search?${query}`, { signal }),
  ]);

  return { users, orders, search };
}
