"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import Pagination from "@/app/components/Pagination";
import { listNotifications, replayNotificationById } from "@/app/lib/api/notifications";
import { canReplayNotification } from "@/app/lib/permissions";
import type { NotificationLog } from "@/app/lib/types";
import { useAsyncQuery } from "@/app/lib/use-async-query";
import { useSession } from "@/app/providers/SessionProvider";

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
  const { user } = useSession();
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("");
  const [recipient, setRecipient] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [selected, setSelected] = useState<NotificationLog | null>(null);
  const [actionError, setActionError] = useState("");
  const [replayingIds, setReplayingIds] = useState<Set<string>>(() => new Set());

  const dialogRef = useRef<HTMLDialogElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const dialogTitleId = useId();

  const filterKey = JSON.stringify({ page, status, recipient, dateFrom, dateTo });
  const journalQuery = useAsyncQuery(
    (signal) =>
      listNotifications(
        {
          page,
          per_page: 15,
          status: status || undefined,
          recipient: recipient || undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
        },
        signal,
      ),
    `admin:notifications:${filterKey}`,
    { fallbackError: "Не удалось загрузить журнал" },
  );

  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) {
      return;
    }

    if (selected) {
      if (!dialog.open) {
        dialog.showModal();
      }
      closeButtonRef.current?.focus();
    } else if (dialog.open) {
      dialog.close();
    }
  }, [selected]);

  function readFormField(form: FormData, key: string): string {
    const entry = form.get(key);
    return typeof entry === "string" ? entry : "";
  }

  function applyFilters(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setPage(1);
    setActionError("");
    setStatus(readFormField(form, "status"));
    setRecipient(readFormField(form, "recipient"));
    setDateFrom(readFormField(form, "date_from"));
    setDateTo(readFormField(form, "date_to"));
  }

  async function replay(log: NotificationLog) {
    if (replayingIds.has(log.id)) {
      return;
    }

    setActionError("");
    setReplayingIds((prev) => {
      const next = new Set(prev);
      next.add(log.id);
      return next;
    });

    try {
      const updated = await replayNotificationById(log.id);
      journalQuery.mutate((current) =>
        current
          ? {
              ...current,
              data: current.data.map((item) =>
                item.id === log.id ? { ...item, ...updated } : item,
              ),
            }
          : current,
      );
    } catch (reason: unknown) {
      setActionError(reason instanceof Error ? reason.message : "Не удалось повторить отправку");
    } finally {
      setReplayingIds((prev) => {
        const next = new Set(prev);
        next.delete(log.id);
        return next;
      });
    }
  }

  const logs = journalQuery.data?.data ?? [];
  const lastPage = journalQuery.data?.meta?.last_page ?? 1;
  const error = actionError || journalQuery.error;

  return (
    <section>
      <h1 className="page-title">Журнал уведомлений</h1>
      <p className="page-subtitle">
        История отправленных уведомлений. Повторная отправка доступна администратору.
      </p>

      <form onSubmit={applyFilters} className="card mt-6 grid gap-3 p-4 md:grid-cols-5">
        <select
          name="status"
          defaultValue={status}
          aria-label="Статус уведомления"
          className="select"
        >
          {statuses.map((item) => (
            <option key={item || "all"} value={item}>
              {item || "Все статусы"}
            </option>
          ))}
        </select>
        <input
          name="recipient"
          defaultValue={recipient}
          aria-label="Email получателя"
          placeholder="Email"
          className="input"
        />
        <input
          name="date_from"
          type="date"
          defaultValue={dateFrom}
          aria-label="Дата с"
          className="input"
        />
        <input
          name="date_to"
          type="date"
          defaultValue={dateTo}
          aria-label="Дата по"
          className="input"
        />
        <button type="submit" className="btn btn-primary">
          Применить
        </button>
      </form>

      {error ? (
        <p role="alert" className="alert alert-error mt-4">
          {error}
        </p>
      ) : null}
      {journalQuery.isLoading ? <p className="mt-4 text-sm text-slate-500">Загрузка...</p> : null}

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
            {logs.map((log) => {
              const isReplaying = replayingIds.has(log.id);
              return (
                <tr key={log.id}>
                  <td className="text-slate-500">
                    {log.created_at ? new Date(log.created_at).toLocaleString("ru-RU") : "—"}
                  </td>
                  <td className="font-medium">{log.event ?? "—"}</td>
                  <td>{log.recipient ?? "—"}</td>
                  <td>
                    {log.status ? (
                      <span className={statusBadge(log.status)}>{log.status}</span>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td>
                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => setSelected(log)}
                        className="btn btn-secondary btn-sm"
                      >
                        Детали
                      </button>
                      {canReplayNotification(user, log.status) ? (
                        <button
                          type="button"
                          disabled={isReplaying}
                          onClick={() => {
                            void replay(log);
                          }}
                          className="btn btn-primary btn-sm"
                        >
                          {isReplaying ? "Отправка..." : "Повторить"}
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              );
            })}
            {logs.length === 0 && !journalQuery.isLoading ? (
              <tr>
                <td colSpan={5} className="!py-12 text-center text-slate-500">
                  Записей нет
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>

      <Pagination currentPage={page} lastPage={lastPage} onPageChange={setPage} />

      <dialog
        ref={dialogRef}
        aria-labelledby={dialogTitleId}
        onClose={() => setSelected(null)}
        className="m-auto max-h-[80vh] w-full max-w-2xl overflow-auto rounded-3xl bg-white p-6 text-slate-900 shadow-pop backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
      >
        {selected ? (
          <div>
            <div className="flex items-center justify-between gap-4">
              <h2 id={dialogTitleId} className="text-lg font-semibold tracking-tight">
                Детали уведомления
              </h2>
              <button
                ref={closeButtonRef}
                type="button"
                onClick={() => setSelected(null)}
                className="btn btn-ghost btn-sm"
              >
                Закрыть
              </button>
            </div>
            {selected.error_message ? (
              <p className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {selected.error_message}
              </p>
            ) : null}
            <pre className="mt-4 overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs leading-5 text-slate-100">
              {JSON.stringify(selected.payload, null, 2)}
            </pre>
          </div>
        ) : null}
      </dialog>
    </section>
  );
}
