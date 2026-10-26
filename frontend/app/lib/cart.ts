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

export function readCart(): CartLine[] {
  if (typeof window === "undefined") {
    return [];
  }

  try {
    const parsed = JSON.parse(localStorage.getItem(KEY) ?? "[]") as unknown;
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
    return [];
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
