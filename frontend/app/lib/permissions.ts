export type PermissionUser = {
  id?: number | string | null;
  role?: string | null;
  roles?: readonly string[] | null;
};

export type OrderPermissionTarget = {
  status: string;
  user_id?: number | string | null;
};

export type TargetUserLike = {
  id: number | string;
};

const CANCELLABLE_STATUSES = new Set([
  "created",
  "stock_reserved",
  "payment_pending",
  "paid",
  "confirmed",
]);

export function resolveUserRoles(user: PermissionUser | null | undefined): string[] {
  if (!user) {
    return [];
  }

  const collected = new Set<string>();
  if (Array.isArray(user.roles)) {
    for (const item of user.roles) {
      if (typeof item === "string" && item.trim() !== "") {
        collected.add(item.trim().toLowerCase());
      }
    }
  }

  if (typeof user.role === "string" && user.role.trim() !== "") {
    collected.add(user.role.trim().toLowerCase());
  }

  return [...collected];
}

function hasAnyRole(user: PermissionUser | null | undefined, allowed: readonly string[]): boolean {
  const roles = resolveUserRoles(user);
  return allowed.some((role) => roles.includes(role));
}

export function canViewAdmin(user: PermissionUser | null | undefined): boolean {
  return hasAnyRole(user, ["admin", "analyst"]);
}

export function canManageUsers(
  user: PermissionUser | null | undefined,
  targetUser?: TargetUserLike | null,
): boolean {
  if (!hasAnyRole(user, ["admin"])) {
    return false;
  }

  if (targetUser && user?.id !== undefined && user.id !== null) {
    if (String(targetUser.id) === String(user.id)) {
      return false;
    }
  }

  return true;
}

export function canReplayNotification(
  user: PermissionUser | null | undefined,
  status: string | null | undefined = "failed",
): boolean {
  return hasAnyRole(user, ["admin"]) && status === "failed";
}

export function canOpenCatalogAdmin(user: PermissionUser | null | undefined): boolean {
  return hasAnyRole(user, ["admin"]);
}

export function canCancelOrder(
  user: PermissionUser | null | undefined,
  order: OrderPermissionTarget | null | undefined,
): boolean {
  if (!user || !order) {
    return false;
  }

  if (!CANCELLABLE_STATUSES.has(order.status)) {
    return false;
  }

  if (hasAnyRole(user, ["admin"])) {
    return true;
  }

  if (!hasAnyRole(user, ["customer"])) {
    return false;
  }

  if (order.user_id === undefined || order.user_id === null) {
    return true;
  }

  if (user.id === undefined || user.id === null) {
    return false;
  }

  return String(order.user_id) === String(user.id);
}
