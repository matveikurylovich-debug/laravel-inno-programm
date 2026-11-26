export type CartLine = {
  productId: number;
  storeId: number;
  locationId: number;
  locationLabel: string;
  name: string;
  price: number;
  quantity: number;
  currency?: string;
};

export type CartUserId = number | string | null | undefined;

const LEGACY_KEY = "cart";
const EMPTY_CART: CartLine[] = [];

let activeUserId: string | null = null;
let cachedScopeKey: string | null = null;
let cachedRaw: string | null | undefined;
let cachedLines: CartLine[] = EMPTY_CART;

function normalizeScopeId(userId: CartUserId): string | null {
  if (userId === null || userId === undefined || userId === "") {
    return null;
  }
  return String(userId);
}

export function cartStorageKey(userId?: CartUserId): string {
  const resolved = userId === undefined ? activeUserId : normalizeScopeId(userId);
  return `cart:${resolved ?? "guest"}`;
}

function notifyCartChanged(): void {
  if (typeof window !== "undefined" && typeof window.dispatchEvent === "function") {
    window.dispatchEvent(new Event("cart-changed"));
  }
}

function lineKey(line: CartLine): string {
  return `${line.productId}:${line.locationId}`;
}

function mergeCartLines(base: CartLine[], extra: CartLine[]): CartLine[] {
  const next = base.map((line) => ({ ...line }));

  for (const line of extra) {
    const index = next.findIndex((item) => lineKey(item) === lineKey(line));
    if (index >= 0) {
      const current = next[index];
      if (!current) {
        continue;
      }
      next[index] = {
        ...current,
        quantity: Math.min(1000, current.quantity + line.quantity),
      };
      continue;
    }

    next.push(line);
  }

  return next;
}

export function setCartUser(userId: CartUserId): void {
  const next = normalizeScopeId(userId);
  if (activeUserId === next) {
    return;
  }

  const previous = activeUserId;
  if (
    previous === null &&
    next !== null &&
    typeof localStorage !== "undefined" &&
    typeof localStorage.getItem === "function" &&
    typeof localStorage.setItem === "function"
  ) {
    const guestLines = parseCart(localStorage.getItem("cart:guest"));
    const userLines = parseCart(localStorage.getItem(`cart:${next}`));
    localStorage.setItem(`cart:${next}`, JSON.stringify(mergeCartLines(userLines, guestLines)));
    localStorage.removeItem("cart:guest");
  }

  activeUserId = next;
  cachedScopeKey = null;
  cachedRaw = undefined;

  if (typeof localStorage !== "undefined" && typeof localStorage.removeItem === "function") {
    localStorage.removeItem(LEGACY_KEY);
  }

  notifyCartChanged();
}

export function resetCartOnLogout(): void {
  if (typeof localStorage !== "undefined" && typeof localStorage.removeItem === "function") {
    localStorage.removeItem(LEGACY_KEY);
    localStorage.removeItem(cartStorageKey(null));
  }

  activeUserId = null;
  cachedScopeKey = null;
  cachedRaw = undefined;
  cachedLines = EMPTY_CART;
  notifyCartChanged();
}

export function getActiveCartUser(): string | null {
  return activeUserId;
}

export function subscribeCart(onStoreChange: () => void): () => void {
  if (typeof window === "undefined") {
    return () => undefined;
  }

  window.addEventListener("cart-changed", onStoreChange);
  window.addEventListener("storage", onStoreChange);
  return () => {
    window.removeEventListener("cart-changed", onStoreChange);
    window.removeEventListener("storage", onStoreChange);
  };
}

function parseCart(raw: string | null): CartLine[] {
  if (!raw) {
    return EMPTY_CART;
  }

  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) {
      return EMPTY_CART;
    }

    const valid = parsed.filter((line): line is CartLine => {
      if (line === null || typeof line !== "object") {
        return false;
      }
      const record = line as Record<string, unknown>;
      return (
        typeof record.productId === "number" &&
        typeof record.storeId === "number" &&
        typeof record.locationId === "number" &&
        typeof record.locationLabel === "string" &&
        typeof record.name === "string" &&
        typeof record.price === "number" &&
        typeof record.quantity === "number" &&
        record.quantity > 0
      );
    });

    return valid.length === 0 ? EMPTY_CART : valid;
  } catch {
    return EMPTY_CART;
  }
}

export function readCart(userId?: CartUserId): CartLine[] {
  if (typeof window === "undefined" || typeof localStorage === "undefined") {
    return EMPTY_CART;
  }

  const key = cartStorageKey(userId);
  const raw = typeof localStorage.getItem === "function" ? localStorage.getItem(key) : null;

  if (userId === undefined && cachedScopeKey === key && cachedRaw === raw) {
    return cachedLines;
  }

  const parsed = parseCart(raw);
  if (userId === undefined) {
    cachedScopeKey = key;
    cachedRaw = raw;
    cachedLines = parsed;
  }

  return parsed;
}

export function writeCart(lines: CartLine[], userId?: CartUserId): void {
  if (typeof localStorage === "undefined" || typeof localStorage.setItem !== "function") {
    return;
  }

  const key = cartStorageKey(userId);
  const serialized = JSON.stringify(lines);
  localStorage.setItem(key, serialized);

  if (userId === undefined || key === cartStorageKey()) {
    cachedScopeKey = key;
    cachedRaw = serialized;
    cachedLines = lines.length === 0 ? EMPTY_CART : lines;
  }

  notifyCartChanged();
}

export function addToCart(
  line: Omit<CartLine, "quantity"> & { quantity?: number },
  userId?: CartUserId,
): void {
  const quantity = Math.max(1, Math.min(1000, Math.trunc(line.quantity ?? 1)));
  const current = readCart(userId);
  const existingIndex = current.findIndex(
    (item) => item.productId === line.productId && item.locationId === line.locationId,
  );

  if (existingIndex >= 0) {
    const next = current.map((item, idx) =>
      idx === existingIndex
        ? { ...item, quantity: Math.min(1000, item.quantity + quantity) }
        : item,
    );
    writeCart(next, userId);
    return;
  }

  writeCart([...current, { ...line, quantity }], userId);
}

export function setQuantity(
  productId: number,
  locationId: number,
  quantity: number,
  userId?: CartUserId,
): void {
  const normalized = Math.trunc(quantity);
  const current = readCart(userId);
  const next = current
    .map((line) =>
      line.productId === productId && line.locationId === locationId
        ? { ...line, quantity: Math.min(1000, normalized) }
        : line,
    )
    .filter((line) => line.quantity > 0);

  writeCart(next, userId);
}

export function removeLine(productId: number, locationId: number, userId?: CartUserId): void {
  writeCart(
    readCart(userId).filter(
      (line) => !(line.productId === productId && line.locationId === locationId),
    ),
    userId,
  );
}

export function clearCart(userId?: CartUserId): void {
  writeCart([], userId);
}

export function removeLines(lines: CartLine[], userId?: CartUserId): void {
  const keys = new Set(lines.map((line) => `${line.productId}:${line.locationId}`));
  writeCart(
    readCart(userId).filter((line) => !keys.has(`${line.productId}:${line.locationId}`)),
    userId,
  );
}
