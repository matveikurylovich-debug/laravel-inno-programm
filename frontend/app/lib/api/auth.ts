import { apiFetch, readError, requestJson } from "@/app/lib/api";
import { resetCartOnLogout } from "@/app/lib/cart";
import type {
  AuthResponse,
  AuthUser,
  DirectoryUser,
  PaginationMeta,
  TwoFactorChallenge,
} from "@/app/lib/types";

export type LoginPayload = {
  email: string;
  password: string;
};

export type RegisterPayload = {
  name: string;
  email: string;
  phone: string;
  password: string;
  password_confirmation: string;
};

export type ResetPasswordPayload = {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type AdminUsersResponse = {
  data: DirectoryUser[];
  roles?: string[];
  meta?: PaginationMeta;
};

export type MeResult =
  | { status: "authenticated"; user: AuthUser }
  | { status: "guest" }
  | { status: "unavailable"; error: string };

export async function fetchMeSession(signal?: AbortSignal): Promise<MeResult> {
  try {
    const response = await apiFetch("/api/auth/me", {
      signal,
      redirectOnUnauthorized: false,
    });

    if (response.ok) {
      const body = (await response.json()) as { user: AuthUser };
      return { status: "authenticated", user: body.user };
    }

    if (response.status === 401 || response.status === 403) {
      return { status: "guest" };
    }

    const message = await readError(response);
    return {
      status: "unavailable",
      error: response.status >= 500 ? "Сервис временно недоступен" : message,
    };
  } catch (reason: unknown) {
    if (reason instanceof Error && reason.name === "AbortError") {
      throw reason;
    }
    return { status: "unavailable", error: "Сервис временно недоступен" };
  }
}

export async function loginRequest(
  payload: LoginPayload,
  signal?: AbortSignal,
): Promise<TwoFactorChallenge> {
  return requestJson<TwoFactorChallenge>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function registerRequest(
  payload: RegisterPayload,
  signal?: AbortSignal,
): Promise<TwoFactorChallenge> {
  return requestJson<TwoFactorChallenge>("/api/auth/register", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function verifyTwoFactorRequest(
  payload: { challenge_id: string; code: string },
  signal?: AbortSignal,
): Promise<AuthResponse> {
  return requestJson<AuthResponse>("/api/auth/two-factor/verify", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function resendTwoFactorRequest(
  payload: { challenge_id: string },
  signal?: AbortSignal,
): Promise<TwoFactorChallenge> {
  return requestJson<TwoFactorChallenge>("/api/auth/two-factor/resend", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function forgotPasswordRequest(
  payload: { email: string },
  signal?: AbortSignal,
): Promise<void> {
  await requestJson<unknown>("/api/auth/forgot-password", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function resetPasswordRequest(
  payload: ResetPasswordPayload,
  signal?: AbortSignal,
): Promise<void> {
  await requestJson<unknown>("/api/auth/reset-password", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function updateProfileRequest(
  payload: { name: string; email: string; phone: string },
  signal?: AbortSignal,
): Promise<{ user?: AuthUser }> {
  return requestJson<{ user?: AuthUser }>("/api/auth/profile", {
    method: "PATCH",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function requestEmailChange(
  payload: { email: string; current_password: string },
  signal?: AbortSignal,
): Promise<void> {
  await requestJson<unknown>("/api/auth/profile/email", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function updatePasswordRequest(
  payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  },
  signal?: AbortSignal,
): Promise<void> {
  await requestJson<unknown>("/api/auth/profile/password", {
    method: "PUT",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function deleteAccountRequest(
  payload: { password: string },
  signal?: AbortSignal,
): Promise<void> {
  await requestJson<unknown>("/api/auth/profile", {
    method: "DELETE",
    body: JSON.stringify(payload),
    signal,
  });
}

export async function logoutSession(): Promise<void> {
  try {
    await apiFetch("/api/auth/logout", {
      method: "POST",
      redirectOnUnauthorized: false,
    });
  } catch {
    // Локальное состояние сбрасывается даже если сервис недоступен.
  } finally {
    resetCartOnLogout();
  }
}

export async function logoutAllSessions(): Promise<void> {
  try {
    await apiFetch("/api/auth/logout-all", {
      method: "POST",
      redirectOnUnauthorized: false,
    });
  } catch {
    // Локальное состояние сбрасывается даже если сервис недоступен.
  } finally {
    resetCartOnLogout();
  }
}

type RawAdminUsersPayload = {
  data?: DirectoryUser[];
  roles?: string[];
  meta?: PaginationMeta;
  current_page?: number;
  last_page?: number;
  total?: number;
  per_page?: number;
};

export async function listAdminUsers(
  params: { page?: number; per_page?: number } = {},
  signal?: AbortSignal,
): Promise<AdminUsersResponse> {
  const search = new URLSearchParams();
  if (params.page && params.page > 1) {
    search.set("page", String(params.page));
  }
  if (params.per_page) {
    search.set("per_page", String(params.per_page));
  }

  const query = search.toString();
  const raw = await requestJson<RawAdminUsersPayload>(
    `/api/auth/admin/users${query ? `?${query}` : ""}`,
    { signal },
  );

  const meta: PaginationMeta | undefined =
    raw.meta ??
    (typeof raw.current_page === "number" && typeof raw.last_page === "number"
      ? {
          current_page: raw.current_page,
          last_page: raw.last_page,
          total: raw.total ?? raw.data?.length ?? 0,
          per_page: raw.per_page,
        }
      : undefined);

  return {
    data: raw.data ?? [],
    roles: Array.isArray(raw.roles) && raw.roles.length > 0 ? raw.roles : undefined,
    meta,
  };
}

export async function updateAdminUserRole(
  userId: number,
  role: string,
  signal?: AbortSignal,
): Promise<{ user?: DirectoryUser }> {
  return requestJson<{ user?: DirectoryUser }>(`/api/auth/admin/users/${userId}/role`, {
    method: "PATCH",
    body: JSON.stringify({ role }),
    signal,
  });
}
