import { NextResponse, type NextRequest } from "next/server";
import { evaluateProxyRedirect } from "@/app/lib/proxy-guard";

/**
 * Next.js 16 server-side route guard (`proxy.ts`).
 *
 * Note on cookie scope (PR-14): the HttpOnly refresh cookie uses `Path=/api/auth`,
 * so browsers do not send it on page navigation requests. Therefore `proxy.ts`
 * checks the `__Host-access` cookie (with `access_token` fallback in local HTTP dev),
 * while client-side `apiFetch` performs single-flight silent refresh via
 * `POST /api/auth/refresh` whenever an API call receives `401`.
 */
export function proxy(request: NextRequest): NextResponse {
  const redirectTarget = evaluateProxyRedirect(
    request.nextUrl.pathname,
    (name) => request.cookies.get(name)?.value,
  );

  if (redirectTarget) {
    return NextResponse.redirect(new URL(redirectTarget, request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/profile/:path*",
    "/orders/:path*",
    "/checkout/:path*",
    "/assistant/:path*",
    "/admin/:path*",
    "/login",
    "/register",
  ],
};
