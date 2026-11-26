"use client";

import { useSyncExternalStore } from "react";
import { readCart, subscribeCart, type CartLine } from "@/app/lib/cart";

const serverCart: CartLine[] = [];

export function useCart(): CartLine[] {
  return useSyncExternalStore(
    subscribeCart,
    () => readCart(),
    () => serverCart,
  );
}
