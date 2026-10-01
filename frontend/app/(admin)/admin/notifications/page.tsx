"use client";

import { FormEvent, useEffect, useState } from "react";
import { apiFetch, readError } from "@/app/lib/api";
import type { NotificationLog, PaginatedNotifications } from "@/app/lib/types";

const statuses = ["", "pending", "sent", "failed"];

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
      <h1 className="text-2xl font-semibold">Журнал уведомлений</h1>
      <p className="mt-1 text-sm text-slate-500">Записи из MongoDB. Повторная отправка доступна администратору.</p>

      <form onSubmit={applyFilters} className="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 md:grid-cols-5">
        <select name="status" defaultValue={status} className="rounded-lg border border-slate-300 px-3 py-2 text-sm">
          {statuses.map((item) => (
            <option key={item || "all"} value={item}>
              {item || "Все статусы"}
            </option>
          ))}
        </select>
        <input name="recipient" defaultValue={recipient} placeholder="Email" className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <input name="date_from" type="date" defaultValue={dateFrom} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <input name="date_to" type="date" defaultValue={dateTo} className="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
        <button type="submit" className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white">
          Применить
        </button>
      </form>

      {error ? <p className="mt-4 text-sm text-red-600">{error}</p> : null}
      {loading ? <p className="mt-4 text-sm text-slate-500">Загрузка...</p> : null}

      <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table className="w-full min-w-[760px] text-left text-sm">
          <thead className="bg-slate-50 text-slate-500">
            <tr>
              <th className="px-4 py-3 font-medium">Дата</th>
              <th className="px-4 py-3 font-medium">Событие</th>
              <th className="px-4 py-3 font-medium">Получатель</th>
              <th className="px-4 py-3 font-medium">Статус</th>
              <th className="px-4 py-3 font-medium">Действия</th>
            </tr>
          </thead>
          <tbody>
            {logs.map((log) => (
              <tr key={log.id} className="border-t border-slate-100">
                <td className="px-4 py-3 text-slate-500">
                  {log.created_at ? new Date(log.created_at).toLocaleString("ru-RU") : "—"}
                </td>
                <td className="px-4 py-3">{log.event ?? "—"}</td>
                <td className="px-4 py-3">{log.recipient ?? "—"}</td>
                <td className="px-4 py-3">{log.status ?? "—"}</td>
                <td className="px-4 py-3">
                  <div className="flex gap-2">
                    <button type="button" onClick={() => setSelected(log)} className="rounded-lg border border-slate-300 px-2 py-1">
                      Payload
                    </button>
                    {log.status === "failed" && role === "admin" ? (
                      <button type="button" onClick={() => replay(log)} className="rounded-lg bg-slate-900 px-2 py-1 text-white">
                        Replay
                      </button>
                    ) : null}
                  </div>
                </td>
              </tr>
            ))}
            {logs.length === 0 && !loading ? (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                  Записей нет
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex items-center gap-3 text-sm">
        <button type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)} className="rounded-lg border border-slate-300 bg-white px-3 py-1 disabled:opacity-40">
          Назад
        </button>
        <span>
          {page} / {lastPage}
        </span>
        <button type="button" disabled={page >= lastPage} onClick={() => setPage((current) => current + 1)} className="rounded-lg border border-slate-300 bg-white px-3 py-1 disabled:opacity-40">
          Вперёд
        </button>
      </div>

      {selected ? (
        <div className="fixed inset-0 z-20 flex items-center justify-center bg-slate-900/40 p-4" onClick={() => setSelected(null)}>
          <div className="max-h-[80vh] w-full max-w-2xl overflow-auto rounded-2xl bg-white p-6" onClick={(event) => event.stopPropagation()}>
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-semibold">Payload</h2>
              <button type="button" onClick={() => setSelected(null)} className="text-sm text-slate-500">
                Закрыть
              </button>
            </div>
            <pre className="mt-4 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-100">
              {JSON.stringify(selected.payload, null, 2)}
            </pre>
          </div>
        </div>
      ) : null}
    </section>
  );
}
