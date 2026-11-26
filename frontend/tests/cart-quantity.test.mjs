import test from "node:test";
import assert from "node:assert/strict";
import {
  addToCart,
  cartStorageKey,
  clearCart,
  readCart,
  removeLine,
  resetCartOnLogout,
  setCartUser,
  setQuantity,
} from "../app/lib/cart.ts";
import { clampQuantity, normalizeQuantityDraft } from "../app/lib/quantity.ts";

function installMockBrowserStorage() {
  const store = new Map();
  globalThis.localStorage = {
    getItem: (key) => (store.has(key) ? store.get(key) : null),
    setItem: (key, value) => {
      store.set(key, String(value));
    },
    removeItem: (key) => {
      store.delete(key);
    },
  };
  globalThis.window = {
    dispatchEvent: () => true,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
  };
  return store;
}

test("cartStorageKey scopes cart by user id and falls back to guest", () => {
  installMockBrowserStorage();
  setCartUser(null);
  assert.equal(cartStorageKey(), "cart:guest");
  assert.equal(cartStorageKey(42), "cart:42");
  assert.equal(cartStorageKey(null), "cart:guest");
});

test("user-scoped cart isolates users and switches cleanly on logout", () => {
  const store = installMockBrowserStorage();
  store.set("cart", JSON.stringify([{ legacy: true }]));

  setCartUser(7);
  assert.equal(store.has("cart"), false);

  addToCart({
    productId: 101,
    storeId: 1,
    locationId: 5,
    locationLabel: "Минск, Победителей 1",
    name: "Кроссовки",
    price: 120,
    quantity: 2,
  });
  assert.equal(readCart().length, 1);
  assert.equal(readCart()[0].quantity, 2);
  assert.equal(store.has("cart:7"), true);

  setCartUser(8);
  assert.deepEqual(readCart(), []);

  setCartUser(7);
  assert.equal(readCart().length, 1);
  setQuantity(101, 5, 4);
  assert.equal(readCart()[0].quantity, 4);

  resetCartOnLogout();
  assert.equal(cartStorageKey(), "cart:guest");
  assert.deepEqual(readCart(), []);

  addToCart({
    productId: 202,
    storeId: 1,
    locationId: 5,
    locationLabel: "Минск",
    name: "Куртка",
    price: 250,
  });
  assert.equal(readCart().length, 1);
  removeLine(202, 5);
  assert.deepEqual(readCart(), []);

  clearCart(7);
  assert.deepEqual(readCart(7), []);
});

test("QuantityInput draft normalization clamps 1..1000 and never deletes item on empty input", () => {
  assert.equal(clampQuantity(1), 1);
  assert.equal(clampQuantity(0), 1);
  assert.equal(clampQuantity(-15), 1);
  assert.equal(clampQuantity(1500), 1000);
  assert.equal(clampQuantity(4.9), 4);
  assert.equal(clampQuantity(Number.NaN), 1);

  // Clearing the input keeps the existing valid quantity instead of returning 0
  assert.equal(normalizeQuantityDraft("", 3), 3);
  assert.equal(normalizeQuantityDraft("   ", 5), 5);
  assert.equal(normalizeQuantityDraft("abc", 2), 2);
  assert.equal(normalizeQuantityDraft("2.5", 4), 4);

  // Invalid drafts keep the previous quantity instead of silently rewriting it
  assert.equal(normalizeQuantityDraft("0", 3), 3);
  assert.equal(normalizeQuantityDraft("-7", 3), 3);
  assert.equal(normalizeQuantityDraft("12", 3), 12);
  assert.equal(normalizeQuantityDraft("9999", 3), 3);
});

test("guest cart merges into the user cart on login", () => {
  installMockBrowserStorage();
  setCartUser(null);
  addToCart({
    productId: 7,
    storeId: 1,
    locationId: 2,
    locationLabel: "Минск",
    name: "Ножи",
    price: 40,
    quantity: 2,
  });

  setCartUser(4);
  addToCart({
    productId: 9,
    storeId: 1,
    locationId: 2,
    locationLabel: "Минск",
    name: "Вилка",
    price: 10,
    quantity: 1,
  });

  setCartUser(null);
  addToCart({
    productId: 7,
    storeId: 1,
    locationId: 2,
    locationLabel: "Минск",
    name: "Ножи",
    price: 40,
    quantity: 1,
  });
  addToCart({
    productId: 3,
    storeId: 1,
    locationId: 2,
    locationLabel: "Минск",
    name: "Ложка",
    price: 8,
  });

  setCartUser(4);
  const lines = readCart();
  const knives = lines.find((line) => line.productId === 7);
  const fork = lines.find((line) => line.productId === 9);
  const spoon = lines.find((line) => line.productId === 3);
  assert.equal(knives?.quantity, 3);
  assert.equal(fork?.quantity, 1);
  assert.equal(spoon?.quantity, 1);
  assert.equal(readCart(null).length, 0);
});
