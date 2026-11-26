import { canViewAdmin } from "@/app/lib/permissions";
import type { AuthUser } from "@/app/lib/types";

export function destinationFor(userOrRole: Pick<AuthUser, "role" | "roles"> | string): string {
  const target = typeof userOrRole === "string" ? { role: userOrRole } : userOrRole;
  return canViewAdmin(target) ? "/admin/users" : "/catalog";
}

export function safeNextPath(rawNext: string | null | undefined, fallback: string): string {
  if (!rawNext) {
    return fallback;
  }

  const trimmed = rawNext.trim();
  if (!trimmed.startsWith("/") || trimmed.startsWith("//") || trimmed.startsWith("/\\")) {
    return fallback;
  }

  return trimmed;
}
