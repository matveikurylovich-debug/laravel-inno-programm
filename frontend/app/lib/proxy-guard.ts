export const ACCESS_COOKIE_NAMES = ["__Host-access", "access_token"] as const;

export const PROTECTED_ROUTE_PREFIXES = [
  "/profile",
  "/orders",
  "/checkout",
  "/assistant",
  "/admin",
] as const;

export function isProtectedPathname(pathname: string): boolean {
  return PROTECTED_ROUTE_PREFIXES.some(
    (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
  );
}

export function hasSessionAccessCookie(getCookie: (name: string) => string | undefined): boolean {
  return ACCESS_COOKIE_NAMES.some((name) => {
    const value = getCookie(name);
    return typeof value === "string" && value.trim() !== "";
  });
}

export function buildLoginRedirectPath(pathname: string): string {
  return `/login?next=${encodeURIComponent(pathname)}`;
}

const GUEST_ONLY_PATHS = ["/login", "/register"] as const;

export function isGuestOnlyPathname(pathname: string): boolean {
  return GUEST_ONLY_PATHS.some((path) => pathname === path || pathname.startsWith(`${path}/`));
}

export function evaluateProxyRedirect(
  pathname: string,
  getCookie: (name: string) => string | undefined,
): string | null {
  const signedIn = hasSessionAccessCookie(getCookie);

  if (signedIn && isGuestOnlyPathname(pathname)) {
    return "/";
  }

  if (!isProtectedPathname(pathname)) {
    return null;
  }

  if (signedIn) {
    return null;
  }

  return buildLoginRedirectPath(pathname);
}
