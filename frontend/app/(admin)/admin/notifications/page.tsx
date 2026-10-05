"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";
import type { NotificationLog, PaginatedNotifications } from "@/app/lib/types";

const statuses = ["", "pending", "sent", "failed"];

function statusBadge(status?: string | null): string {
  switch (status) {
    case "sent":
      return "badge badge-success";
    case "failed":
      return "badge badge-danger";
    case "pending":
      return "badge badge-warning";
    default:
      return "badge";
  }
}

export default function NotificationsPage() {
  const [logs, setLogs] = useState<NotificationLog[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [status, setStatus] = useState("");
  const [recipient, setRecipient] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [error, setError] = useState("");
  const [selected, setSelected] = useState<NotificationLog | null>(null);
  const [role, setRole] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setRole(localStorage.getItem("user_role"));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams({ page: String(page), per_page: "15" });
    if (status) params.set("status", status);
    if (recipient) params.set("recipient", recipient);
    if (dateFrom) params.set("date_from", dateFrom);
    if (dateTo) params.set("date_to", dateTo);

    setLoading(true);
    apiFetch(`/api/notifications?${params.toString()}`)
      .then(async (response) => {
        if (!response.ok) {
          setError(await readError(response));
          setLogs([]);
          return;
        }

        const body = (await response.json()) as PaginatedNotifications;
        setLogs(body.data ?? []);
        setLastPage(body.meta?.last_page ?? 1);
        setError("");
      })
      .catch(() => setError("Не удалось загрузить журнал"))
      .finally(() => setLoading(false));
  }, [page, status, recipient, dateFrom, dateTo]);

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPage(1);
    setStatus(String(form.get("status") ?? ""));
    setRecipient(String(form.get("recipient") ?? ""));
    setDateFrom(String(form.get("date_from") ?? ""));
    setDateTo(String(form.get("date_to") ?? ""));
  }

  async function replay(log: NotificationLog) {
    setError("");
    const response = await apiFetch(`/api/notifications/${log.id}/replay`, { method: "POST" });
    if (!response.ok) {
      setError(await readError(response));
      return;
    }

    const updated = (await response.json()) as { data: NotificationLog };
    const next = updated.data ?? updated;
    setLogs((current) => current.map((item) => (item.id === log.id ? { ...item, ...next } : item)));
  }

  return (
    <section>
      <h1 className="page-title">Журнал уведомлений</h1>
      <p className="page-subtitle">Записи из MongoDB. Повторная отправка доступна администратору.</p>

      <form onSubmit={applyFilters} className="card mt-6 grid gap-3 p-4 md:grid-cols-5">
        <select name="status" defaultValue={status} className="select">
          {statuses.map((item) => (
            <option key={item || "all"} value={item}>
              {item || "Все статусы"}
            </option>
          ))}
        </select>
        <input name="recipient" defaultValue={recipient} placeholder="Email" className="input" />
        <input name="date_from" type="date" defaultValue={dateFrom} className="input" />
        <input name="date_to" type="date" defaultValue={dateTo} className="input" />
        <button type="submit" className="btn btn-primary">
          Применить
        </button>
      </form>

      {error ? <p className="alert alert-error mt-4">{error}</p> : null}
      {loading ? <p className="mt-4 text-sm text-slate-500">Загрузка...</p> : null}

      <div className="table-wrap mt-4">
        <table className="data-table min-w-[760px]">
          <thead>
            <tr>
              <th>Дата</th>
              <th>Событие</th>
              <th>Получатель</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            {logs.map((log) => (
              <tr key={log.id}>
                <td className="text-slate-500">
                  {log.created_at ? new Date(log.created_at).toLocaleString("ru-RU") : "—"}
                </td>
                <td className="font-medium">{log.event ?? "—"}</td>
                <td>{log.recipient ?? "—"}</td>
                <td>
                  {log.status ? <span className={statusBadge(log.status)}>{log.status}</span> : "—"}
                </td>
                <td>
                  <div className="flex gap-2">
                    <button type="button" onClick={() => setSelected(log)} className="btn btn-secondary btn-sm">
                      Payload
                    </button>
                    {log.status === "failed" && role === "admin" ? (
                      <button type="button" onClick={() => replay(log)} className="btn btn-primary btn-sm">
                        Replay
                      </button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
            {logs.length === 0 && !loading ? (
              <tr>
                <td colSpan={5} className="!py-12 text-center text-slate-500">
                  Записей нет
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex items-center gap-3 text-sm text-slate-600">
        <button type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)} className="btn btn-secondary btn-sm">
          Назад
        </button>
        <span className="min-w-12 text-center font-medium tabular-nums">
          {page} / {lastPage}
        </span>
        <button type="button" disabled={page >= lastPage} onClick={() => setPage((current) => current + 1)} className="btn btn-secondary btn-sm">
          Вперёд
        </button>
      </div>

      {selected ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onClick={() => setSelected(null)}>
          <div className="max-h-[80vh] w-full max-w-2xl overflow-auto rounded-3xl bg-white p-6 shadow-pop" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold tracking-tight">Payload</h2>
              <button type="button" onClick={() => setSelected(null)} className="btn btn-ghost btn-sm">
                Закрыть
              </button>
            </div>
            <pre className="mt-4 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100">
              {JSON.stringify(selected.payload, null, 2)}
            </pre>
          </div>
        </div>
      ) : null}
    </section>
  );
}
