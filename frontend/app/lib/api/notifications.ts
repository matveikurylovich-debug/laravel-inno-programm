import { requestJson } from "@/app/lib/api";
import type { NotificationLog, PaginatedNotifications } from "@/app/lib/types";

export type NotificationFilters = {
  page?: number;
  per_page?: number;
  status?: string;
  recipient?: string;
  date_from?: string;
  date_to?: string;
};

export async function listNotifications(
  filters: NotificationFilters = {},
  signal?: AbortSignal,
): Promise<PaginatedNotifications> {
  const params = new URLSearchParams({
    page: String(filters.page ?? 1),
    per_page: String(filters.per_page ?? 15),
  });

  if (filters.status) params.set("status", filters.status);
  if (filters.recipient) params.set("recipient", filters.recipient);
  if (filters.date_from) params.set("date_from", filters.date_from);
  if (filters.date_to) params.set("date_to", filters.date_to);

  return requestJson<PaginatedNotifications>(`/api/notifications?${params.toString()}`, {
    signal,
  });
}

export async function replayNotificationById(
  id: string,
  signal?: AbortSignal,
): Promise<NotificationLog> {
  const body = await requestJson<{ data?: NotificationLog } | NotificationLog>(
    `/api/notifications/${encodeURIComponent(id)}/replay`,
    {
      method: "POST",
      signal,
    },
  );

  if ("data" in body && body.data) {
    return body.data;
  }

  return body as NotificationLog;
}
