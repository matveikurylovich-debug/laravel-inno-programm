"use client";

import { useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";

type Point = { period: string; value: number; count: number };
type UsersReport = { total: number; series: Point[] };
type OrdersReport = {
  funnel: { created: number; paid: number; completed: number; cancelled: number };
  conversion: { created_to_paid: number; created_to_cancelled: number };
  revenue: number;
  average_check: number;
  series: Point[];
};
type SearchRow = { query: string; searches: number; avg_results?: number };
type SearchReport = { popular: SearchRow[]; without_results: SearchRow[] };

function money(value: number): string {
  return new Intl.NumberFormat("ru-RU", { style: "currency", currency: "USD" }).format(value || 0);
}

function percent(value: number): string {
  return `${Math.round((value || 0) * 100)}%`;
}

function day(value: string): string {
  return value.slice(0, 10);
}

export default function AnalyticsPage() {
  const [from, setFrom] = useState(() => new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10));
  const [granularity, setGranularity] = useState("day");
  const [users, setUsers] = useState<UsersReport | null>(null);
  const [orders, setOrders] = useState<OrdersReport | null>(null);
  const [search, setSearch] = useState<SearchReport | null>(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const params = new URLSearchParams({ from, to, granularity });
    setLoading(true);
    setError("");

    Promise.all([
      apiFetch(`/api/analytics/users?${params}`),
      apiFetch(`/api/analytics/orders?${params}`),
      apiFetch(`/api/analytics/search?${params}`),
    ])
      .then(async ([usersResponse, ordersResponse, searchResponse]) => {
        for (const response of [usersResponse, ordersResponse, searchResponse]) {
          if (!response.ok) {
            throw new Error(await readError(response));
          }
        }
        setUsers((await usersResponse.json()) as UsersReport);
        setOrders((await ordersResponse.json()) as OrdersReport);
        setSearch((await searchResponse.json()) as SearchReport);
      })
      .catch((reason: unknown) => setError(reason instanceof Error ? reason.message : "Не удалось загрузить аналитику"))
      .finally(() => setLoading(false));
  }, [from, to, granularity]);

  const maxUsers = Math.max(1, ...(users?.series ?? []).map((point) => point.count));

  return (
    <section>
      <h1 className="page-title">Аналитика</h1>
      <p className="page-subtitle">Регистрации, воронка заказов и поисковые запросы. Доступно ролям Analyst и Admin.</p>

      <form className="card mt-6 grid gap-3 p-4 md:grid-cols-3" onSubmit={(event) => event.preventDefault()}>
        <input className="input" type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
        <input className="input" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
        <select className="select" value={granularity} onChange={(event) => setGranularity(event.target.value)}>
          <option value="day">По дням</option>
          <option value="week">По неделям</option>
          <option value="month">По месяцам</option>
        </select>
      </form>

      {error ? <p className="alert alert-error mt-4">{error}</p> : null}
      {loading ? <p className="mt-4 text-sm text-slate-500">Загрузка...</p> : null}

      <div className="mt-6 grid gap-4 md:grid-cols-4">
        <article className="card p-4"><p className="text-sm text-slate-500">Регистрации</p><p className="mt-2 text-2xl font-semibold">{users?.total ?? 0}</p></article>
        <article className="card p-4"><p className="text-sm text-slate-500">Выручка оплаченных</p><p className="mt-2 text-2xl font-semibold">{money(orders?.revenue ?? 0)}</p></article>
        <article className="card p-4"><p className="text-sm text-slate-500">Средний чек</p><p className="mt-2 text-2xl font-semibold">{money(orders?.average_check ?? 0)}</p></article>
        <article className="card p-4"><p className="text-sm text-slate-500">Конверсия в оплату</p><p className="mt-2 text-2xl font-semibold">{percent(orders?.conversion.created_to_paid ?? 0)}</p></article>
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <article className="card p-4">
          <h2 className="font-semibold">Воронка заказов</h2>
          <ul className="mt-3 space-y-2 text-sm">
            <li>Создано: {orders?.funnel.created ?? 0}</li>
            <li>Оплачено: {orders?.funnel.paid ?? 0}</li>
            <li>Завершено: {orders?.funnel.completed ?? 0}</li>
            <li>Отменено: {orders?.funnel.cancelled ?? 0} ({percent(orders?.conversion.created_to_cancelled ?? 0)})</li>
          </ul>
        </article>
        <article className="card p-4">
          <h2 className="font-semibold">Регистрации по периодам</h2>
          <ul className="mt-3 space-y-2">
            {(users?.series ?? []).map((point) => (
              <li key={point.period} className="grid grid-cols-[6rem_1fr_2rem] items-center gap-2 text-sm">
                <span>{day(point.period)}</span>
                <span className="h-2 rounded bg-brand-500" style={{ width: `${(point.count / maxUsers) * 100}%` }} />
                <span>{point.count}</span>
              </li>
            ))}
            {users && users.series.length === 0 ? <li className="text-sm text-slate-500">Нет регистраций за период</li> : null}
          </ul>
        </article>
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <SearchTable title="Популярные запросы" rows={search?.popular ?? []} />
        <SearchTable title="Запросы без результатов" rows={search?.without_results ?? []} />
      </div>
    </section>
  );
}

function SearchTable({ title, rows }: { title: string; rows: SearchRow[] }) {
  return (
    <article className="card p-4">
      <h2 className="font-semibold">{title}</h2>
      <div className="table-wrap mt-3">
        <table className="data-table">
          <thead>
            <tr><th>Запрос</th><th>Количество</th></tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.query}><td>{row.query}</td><td>{row.searches}</td></tr>
            ))}
            {rows.length === 0 ? <tr><td colSpan={2}>Нет данных</td></tr> : null}
          </tbody>
        </table>
      </div>
    </article>
  );
}
