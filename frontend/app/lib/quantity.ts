export const MIN_QUANTITY = 1;
export const MAX_QUANTITY = 1000;

export function clampQuantity(value: number, min = MIN_QUANTITY, max = MAX_QUANTITY): number {
  const safeMin = Math.max(MIN_QUANTITY, Math.trunc(min));
  const safeMax = Math.max(safeMin, Math.min(MAX_QUANTITY, Math.trunc(max)));

  if (!Number.isFinite(value)) {
    return safeMin;
  }

  const integer = Math.trunc(value);
  if (integer < safeMin) {
    return safeMin;
  }
  if (integer > safeMax) {
    return safeMax;
  }

  return integer;
}

export function quantityDraftError(draft: string, min = MIN_QUANTITY, max = MAX_QUANTITY): string {
  const trimmed = draft.trim();
  if (trimmed === "") {
    return "Укажите количество.";
  }

  if (!/^\d+$/.test(trimmed)) {
    return "Введите целое число.";
  }

  const parsed = Number(trimmed);
  if (parsed < min || parsed > max) {
    return `Количество должно быть от ${min} до ${max}.`;
  }

  return "";
}

export function normalizeQuantityDraft(
  draft: string,
  fallback: number,
  min = MIN_QUANTITY,
  max = MAX_QUANTITY,
): number {
  if (quantityDraftError(draft, min, max) !== "") {
    return clampQuantity(fallback, min, max);
  }

  return Number(draft.trim());
}
