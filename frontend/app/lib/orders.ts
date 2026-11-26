export type { Order, OrderItem, OrderPayment, PaginatedOrders } from "@/app/lib/api/orders";

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
