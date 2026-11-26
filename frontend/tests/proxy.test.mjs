import test from "node:test";
import assert from "node:assert/strict";
import {
  evaluateProxyRedirect,
  hasSessionAccessCookie,
  isProtectedPathname,
} from "../app/lib/proxy-guard.ts";

test("isProtectedPathname distinguishes protected and public paths", () => {
  for (const protectedPath of [
    "/profile",
    "/orders",
    "/orders/550e8400-e29b-41d4-a716-446655440000",
    "/checkout",
    "/assistant",
    "/admin",
    "/admin/users",
    "/admin/notifications",
    "/admin/analytics",
  ]) {
    assert.equal(isProtectedPathname(protectedPath), true, `Expected protected: ${protectedPath}`);
  }

  for (const publicPath of [
    "/",
    "/login",
    "/register",
    "/two-factor",
    "/forgot-password",
    "/reset-password/token-123",
    "/catalog",
    "/catalog/products/15",
    "/catalog/search",
    "/cart",
  ]) {
    assert.equal(isProtectedPathname(publicPath), false, `Expected public: ${publicPath}`);
  }
});

test("evaluateProxyRedirect redirects guests on protected routes with encoded next param", () => {
  const noCookies = () => undefined;

  assert.equal(evaluateProxyRedirect("/orders", noCookies), "/login?next=%2Forders");
  assert.equal(
    evaluateProxyRedirect("/admin/notifications", noCookies),
    "/login?next=%2Fadmin%2Fnotifications",
  );
  assert.equal(evaluateProxyRedirect("/catalog", noCookies), null);
  assert.equal(evaluateProxyRedirect("/login", noCookies), null);
  assert.equal(evaluateProxyRedirect("/register", noCookies), null);
});

test("evaluateProxyRedirect sends a signed-in user away from login and register", () => {
  const cookies = (name) => (name === "__Host-access" ? "jwt-token" : undefined);
  assert.equal(evaluateProxyRedirect("/login", cookies), "/");
  assert.equal(evaluateProxyRedirect("/register", cookies), "/");
  assert.equal(evaluateProxyRedirect("/catalog", cookies), null);
});

test("evaluateProxyRedirect allows __Host-access and access_token dev fallback cookies", () => {
  const prodCookies = (name) => (name === "__Host-access" ? "jwt-token" : undefined);
  const devCookies = (name) => (name === "access_token" ? "dev-jwt-token" : undefined);
  const emptyCookies = (name) => (name === "__Host-access" ? "   " : undefined);

  assert.equal(hasSessionAccessCookie(prodCookies), true);
  assert.equal(hasSessionAccessCookie(devCookies), true);
  assert.equal(hasSessionAccessCookie(emptyCookies), false);

  assert.equal(evaluateProxyRedirect("/profile", prodCookies), null);
  assert.equal(evaluateProxyRedirect("/admin/users", devCookies), null);
  assert.equal(evaluateProxyRedirect("/checkout", emptyCookies), "/login?next=%2Fcheckout");
});
