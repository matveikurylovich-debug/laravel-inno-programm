const ISO_CURRENCY_PATTERN = /^[A-Z]{3}$/;

export function formatMoney(amount: number | string, currency = "USD", locale = "ru-RU"): string {
  if (typeof amount === "string" && amount.trim() === "") {
    return amount;
  }

  const numeric = typeof amount === "number" ? amount : Number(amount);
  if (!Number.isFinite(numeric)) {
    return String(amount);
  }

  const normalizedCurrency = currency.trim().toUpperCase();
  const safeCurrency = ISO_CURRENCY_PATTERN.test(normalizedCurrency) ? normalizedCurrency : "USD";

  try {
    return new Intl.NumberFormat(locale, {
      style: "currency",
      currency: safeCurrency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(numeric);
  } catch {
    return new Intl.NumberFormat(locale, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(numeric);
  }
}
