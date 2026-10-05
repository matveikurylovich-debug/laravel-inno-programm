export async function apiFetch(endpoint: string, options: RequestInit = {}): Promise<Response> {
  const headers = new Headers(options.headers);
  headers.set("Accept", "application/json");

  const isForm = typeof FormData !== "undefined" && options.body instanceof FormData;
  if (options.body && !isForm && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (typeof window !== "undefined") {
    const token = localStorage.getItem("access_token");
    if (token) {
      headers.set("Authorization", `Bearer ${token}`);
    }
  }

  const response = await fetch(endpoint, {
    ...options,
    headers,
  });

  if (response.status === 401 && typeof window !== "undefined") {
    clearSession();

    const path = window.location.pathname;
    if (path !== "/login" && path !== "/register" && !path.startsWith("/reset-password") && path !== "/forgot-password" && path !== "/two-factor") {
      window.location.href = "/login";
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
