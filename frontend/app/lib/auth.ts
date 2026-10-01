import type { AuthResponse } from "@/app/lib/types";

export function destinationFor(role: string): string {
  return role === "admin" || role === "analyst" ? "/admin/notifications" : "/";
}

export function persistAuth(data: AuthResponse): void {
  localStorage.setItem("access_token", data.access_token);
  localStorage.setItem("user_role", data.user.role);
  localStorage.setItem("user_name", data.user.name);
}
