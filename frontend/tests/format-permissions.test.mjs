import test from "node:test";
import assert from "node:assert/strict";
import { formatMoney } from "../app/lib/format.ts";
import {
  canCancelOrder,
  canManageUsers,
  canOpenCatalogAdmin,
  canReplayNotification,
  canViewAdmin,
} from "../app/lib/permissions.ts";

test("formatMoney formats USD and RUB in Russian locale without hardcoded symbols", () => {
  const usd = formatMoney("19.5", "USD");
  const rub = formatMoney(1250, "RUB");

  assert.match(usd, /19,50/);
  assert.match(usd, /\$/);
  assert.doesNotMatch(usd, /₽/);

  assert.match(rub, /1\s*250,00/);
  assert.match(rub, /₽/);
});

test("formatMoney handles non-numeric strings and invalid currency codes gracefully", () => {
  assert.equal(formatMoney(""), "");
  assert.equal(formatMoney("не число"), "не число");

  const fallback = formatMoney(42, "invalid");
  assert.match(fallback, /42,00/);
  assert.match(fallback, /\$/);
});

test("admin and analyst view permissions match backend policies", () => {
  const admin = { id: 1, role: "admin", roles: ["admin"] };
  const analyst = { id: 2, role: "analyst", roles: ["analyst"] };
  const customer = { id: 3, role: "customer", roles: ["customer"] };

  assert.equal(canViewAdmin(admin), true);
  assert.equal(canViewAdmin(analyst), true);
  assert.equal(canViewAdmin(customer), false);
  assert.equal(canViewAdmin(null), false);

  assert.equal(canOpenCatalogAdmin(admin), true);
  assert.equal(canOpenCatalogAdmin(analyst), false);
  assert.equal(canOpenCatalogAdmin(customer), false);
});

test("canManageUsers allows admin for other accounts but blocks analyst and self-edit", () => {
  const admin = { id: 1, role: "admin" };
  const analyst = { id: 2, role: "analyst" };

  assert.equal(canManageUsers(admin), true);
  assert.equal(canManageUsers(admin, { id: 2 }), true);
  assert.equal(canManageUsers(admin, { id: 1 }), false);
  assert.equal(canManageUsers(analyst, { id: 3 }), false);
});

test("canReplayNotification allows only admin on failed notifications", () => {
  const admin = { id: 1, role: "admin" };
  const analyst = { id: 2, role: "analyst" };

  assert.equal(canReplayNotification(admin, "failed"), true);
  assert.equal(canReplayNotification(admin, "sent"), false);
  assert.equal(canReplayNotification(admin, "pending"), false);
  assert.equal(canReplayNotification(analyst, "failed"), false);
});

test("canCancelOrder enforces status, ownership for customer, read-only for analyst, and full access for admin", () => {
  const admin = { id: 1, role: "admin" };
  const analyst = { id: 2, role: "analyst" };
  const customer = { id: 10, role: "customer" };

  const activeOwnOrder = { status: "payment_pending", user_id: 10 };
  const activeForeignOrder = { status: "payment_pending", user_id: 99 };
  const cancelledOrder = { status: "cancelled", user_id: 10 };

  assert.equal(canCancelOrder(customer, activeOwnOrder), true);
  assert.equal(canCancelOrder(customer, activeForeignOrder), false);
  assert.equal(canCancelOrder(customer, cancelledOrder), false);

  assert.equal(canCancelOrder(analyst, activeOwnOrder), false);
  assert.equal(canCancelOrder(analyst, activeForeignOrder), false);

  assert.equal(canCancelOrder(admin, activeOwnOrder), true);
  assert.equal(canCancelOrder(admin, activeForeignOrder), true);
  assert.equal(canCancelOrder(admin, cancelledOrder), false);
});
