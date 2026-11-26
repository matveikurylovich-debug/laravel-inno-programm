export type ApiFetchOptions = RequestInit & {
  redirectOnUnauthorized?: boolean;
};

export class ApiError extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "ApiError";
    this.status = status;
  }
}

let refreshInFlight: Promise<number> | null = null;

function refreshSession(): Promise<number> {
  const run = async (): Promise<number> => {
    try {
      const result = await fetch("/api/auth/refresh", {
        method: "POST",
        credentials: "same-origin",
        headers: { Accept: "application/json", "X-Browser-Session": "1" },
      });

      return result.status;
    } catch {
      return 503;
    }
  };

  if (typeof navigator !== "undefined" && navigator.locks) {
    return navigator.locks.request("auth-refresh", run) as unknown as Promise<number>;
  }

  return run();
}

const AUTH_ENDPOINT_PATTERN =
  /\/api\/auth\/(login|register|refresh|two-factor|forgot-password|reset-password)/;
const PUBLIC_AUTH_PAGE_PATTERN =
  /^\/(login|register|two-factor|forgot-password|reset-password)(\/|$)/;

export async function apiFetch(endpoint: string, options: ApiFetchOptions = {}): Promise<Response> {
  const { redirectOnUnauthorized = true, ...requestInit } = options;
  const headers = new Headers(requestInit.headers);
  headers.set("Accept", "application/json");

  const isForm = typeof FormData !== "undefined" && requestInit.body instanceof FormData;
  if (requestInit.body && !isForm && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  headers.set("X-Browser-Session", "1");
  const send = () => fetch(endpoint, { ...requestInit, headers, credentials: "same-origin" });

  let response = await send();
  const authenticationEndpoint = AUTH_ENDPOINT_PATTERN.test(endpoint);

  if (response.status === 401 && !authenticationEndpoint && typeof window !== "undefined") {
    if (!refreshInFlight) {
      refreshInFlight = refreshSession().finally(() => {
        refreshInFlight = null;
      });
    }

    const refreshStatus = await refreshInFlight;
    if (refreshStatus >= 200 && refreshStatus < 300) {
      response = await send();
    } else if (refreshStatus === 429 || refreshStatus >= 500) {
      return new Response(null, { status: refreshStatus });
    } else if (refreshStatus >= 400 && refreshStatus < 500) {
      const currentPathname = window.location?.pathname ?? "";
      if (redirectOnUnauthorized && !PUBLIC_AUTH_PAGE_PATTERN.test(currentPathname)) {
        // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- Non-React fetch interceptor resets full browser state on revoked session
        window.location.href = "/login";
      }
      return response;
    }
  }

  return response;
}

function firstProblemError(errors: unknown): string | null {
  if (!errors || typeof errors !== "object") {
    return null;
  }

  for (const value of Object.values(errors as Record<string, unknown>)) {
    if (typeof value === "string" && value !== "") {
      return value;
    }

    if (!Array.isArray(value)) {
      continue;
    }

    const item = (value as unknown[]).find(
      (entry): entry is string => typeof entry === "string" && entry !== "",
    );
    if (typeof item === "string") {
      return item;
    }
  }

  return null;
}

export async function readError(response: Response): Promise<string> {
  const body = (await response.json().catch(() => null)) as unknown;
  if (!body || typeof body !== "object") {
    return response.status >= 500 ? "Сервис временно недоступен" : "Запрос не выполнен";
  }

  const record = body as Record<string, unknown>;
  const fieldError = firstProblemError(record.errors);
  if (fieldError) {
    return fieldError;
  }

  if (typeof record.detail === "string" && record.detail !== "") {
    return record.detail;
  }

  if (typeof record.details === "string" && record.details !== "") {
    return record.details;
  }

  if (typeof record.message === "string" && record.message !== "") {
    return record.message;
  }

  if (typeof record.error === "string" && record.error !== "") {
    return record.error;
  }

  return response.status >= 500 ? "Сервис временно недоступен" : "Запрос не выполнен";
}

export async function requestJson<T>(endpoint: string, options: ApiFetchOptions = {}): Promise<T> {
  const response = await apiFetch(endpoint, options);
  if (!response.ok) {
    throw new ApiError(await readError(response), response.status);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
