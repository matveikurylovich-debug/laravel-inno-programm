let refreshInFlight: Promise<number> | null = null;

function refreshSession(): Promise<number> {
  const run = async (): Promise<number> => {
    const result = await fetch("/api/auth/refresh", {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Browser-Session": "1" },
    });

    return result.status;
  };

  if (typeof navigator !== "undefined" && navigator.locks) {
    return navigator.locks.request("auth-refresh", run) as unknown as Promise<number>;
  }

  return run();
}

export async function apiFetch(endpoint: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  const isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
  if (options.body && !isForm && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  headers.set("X-Browser-Session", "1");
  const send = () => fetch(endpoint, { ...options, headers, credentials: "same-origin" });
  let response = await send();
  const authenticationEndpoint = /\/api\/auth\/(login|register|refresh|two-factor|forgot-password|reset-password)/.test(endpoint);
  if (response.status === 401 && !authenticationEndpoint && typeof window !== "undefined") {
    if (!refreshInFlight) {
      refreshInFlight = refreshSession().finally(() => { refreshInFlight = null; });
    }
    const refreshStatus = await refreshInFlight;
    if (refreshStatus >= 200 && refreshStatus < 300) {
      response = await send();
    } else if (refreshStatus === 429 || refreshStatus >= 500) {
      return new Response(null, { status: refreshStatus });
    } else if (refreshStatus >= 400 && refreshStatus < 500) {
      window.location.href = "/login";
      return response;
    }
  }

  return response;
}

export async function readError(response: Response): Promise<string> {
  const body = await response.json().catch(() => null);
  if (!body || typeof body !== "object") {
    return "Запрос не выполнен";
  }

  if ("error" in body && typeof body.error === "string") {
    return body.error;
  }

  if ("message" in body && typeof body.message === "string") {
    const errors = "errors" in body && body.errors && typeof body.errors === "object"
      ? Object.values(body.errors as Record<string, string[]>)
          .flat()
          .filter((item) => typeof item === "string")
      : [];

    return errors[0] ?? body.message;
  }

  return "Запрос не выполнен";
}

export function clearSession(): void {
  localStorage.removeItem("access_token");
  localStorage.removeItem("user_role");
  localStorage.removeItem("user_name");
}

export async function logout(): Promise<void> {
  try {
    await apiFetch("/api/auth/logout", { method: "POST" });
  } catch {
    // Локальная сессия сбрасывается даже если сервис недоступен.
  } finally {
    clearSession();
    window.location.href = "/login";
  }
}
