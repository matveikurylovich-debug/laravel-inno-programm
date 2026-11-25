export type CartLine = {
  productId: number;
  storeId: number;
  locationId: number;
  locationLabel: string;
  name: string;
  price: number;
  quantity: number;
};

const KEY = "cart";
const EMPTY_CART: CartLine[] = [];
let cachedRaw: string | null | undefined;
let cachedLines: CartLine[] = EMPTY_CART;

export function subscribeCart(onStoreChange: () => void): () => void {
  window.addEventListener("cart-changed", onStoreChange);
  window.addEventListener("storage", onStoreChange);
  return () => {
    window.removeEventListener("cart-changed", onStoreChange);
    window.removeEventListener("storage", onStoreChange);
  };
}

export function readCart(): CartLine[] {
  if (typeof window === "undefined") {
    return EMPTY_CART;
  }

  const raw = localStorage.getItem(KEY);
  if (cachedRaw === raw) {
    return cachedLines;
  }

  cachedRaw = raw;
  cachedLines = parseCart(raw);
  return cachedLines;
}

function parseCart(raw: string | null): CartLine[] {
  try {
    const parsed = JSON.parse(raw ?? "[]") as unknown;
    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter((line): line is CartLine => {
      return line !== null
        && typeof line === "object"
        && typeof line.productId === "number"
        && typeof line.storeId === "number"
        && typeof line.locationId === "number"
        && typeof line.locationLabel === "string"
        && typeof line.name === "string"
        && typeof line.price === "number"
        && typeof line.quantity === "number";
    });
  } catch {
    return EMPTY_CART;
  }
}

export function writeCart(lines: CartLine[]): void {
  localStorage.setItem(KEY, JSON.stringify(lines));
  window.dispatchEvent(new Event("cart-changed"));
}

export function addToCart(line: Omit<CartLine, "quantity"> & { quantity?: number }): void {
  const quantity = line.quantity ?? 1;
  const current = readCart();
  const existing = current.find((item) => item.productId === line.productId && item.locationId === line.locationId);

  if (existing) {
    existing.quantity += quantity;
    writeCart(current);
    return;
  }

  writeCart([...current, { ...line, quantity }]);
}

export function setQuantity(productId: number, locationId: number, quantity: number): void {
  writeCart(
    readCart()
      .map((line) => (line.productId === productId && line.locationId === locationId ? { ...line, quantity } : line))
      .filter((line) => line.quantity > 0),
  );
}

export function clearCart(): void {
  writeCart([]);
}
