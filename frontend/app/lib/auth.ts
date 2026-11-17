import type { AuthResponse, AuthUser } from "@/app/lib/types";
import { apiFetch, clearSession } from "@/app/lib/api";

export function destinationFor(role: string): string {
  return role === "admin" || role === "analyst" ? "/admin/notifications" : "/";
}

export function persistAuth(data: AuthResponse): void {
  void data;
  // Remove credentials left by older releases; the server now sets HttpOnly cookies.
  clearSession();
}

export async function currentUser(): Promise<AuthUser | null> {
  const response = await apiFetch("/api/auth/me");
  if (!response.ok) return null;
  return ((await response.json()) as { user: AuthUser }).user;
}
