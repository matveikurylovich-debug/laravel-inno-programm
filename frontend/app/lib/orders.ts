export type OrderItem = {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: number | string;
  subtotal: number | string;
};

export type OrderPayment = {
  status: string;
  stripe_client_secret: string | null;
};

export type Order = {
  id: string;
  status: string;
  payment_status: string;
  total_amount: number | string;
  currency: string;
  created_at?: string;
  items?: OrderItem[];
  payment?: OrderPayment | null;
};

export function money(value: number | string, currency = "USD"): string {
  const number = Number(value);
  if (Number.isNaN(number)) {
    return String(value);
  }

  return new Intl.NumberFormat("ru-RU", {
    style: "currency",
    currency: currency.toUpperCase(),
    minimumFractionDigits: 2,
  }).format(number);
}

export function statusLabel(status: string): string {
  const labels: Record<string, string> = {
    created: "Создан",
    stock_reserved: "Резерв на складе",
    payment_pending: "Ожидает оплату",
    paid: "Оплачен",
    confirmed: "Подтверждён",
    shipped: "Отправлен",
    completed: "Завершён",
    cancelled: "Отменён",
    payment_failed: "Оплата не прошла",
  };

  return labels[status] ?? status;
}

export function isFinalStatus(status: string): boolean {
  return ["confirmed", "shipped", "completed", "cancelled", "payment_failed"].includes(status);
}

export function canCancel(status: string): boolean {
  return ["created", "stock_reserved", "payment_pending"].includes(status);
}
