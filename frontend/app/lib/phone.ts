const BELARUS_CALLING_CODE = "375";

export function phoneFromProfile(stored: string | null | undefined): string {
  return stored ?? "";
}

export function phoneToSubmit(stored: string, current: string, edited: boolean): string {
  if (!edited) {
    return stored;
  }

  return current;
}

// Keystrokes stay as typed. Belarus grouping is applied on blur, so backspace can remove ")".
export function applyPhoneChange(previous: string, next: string): string {
  if (next === "") {
    return "";
  }

  const removedClosingParen = previous.endsWith(")") && previous.slice(0, -1) === next;
  if (removedClosingParen || next === "+") {
    return next;
  }

  return next;
}

export function formatBelarusPhone(value: string): string {
  const trimmed = value.trim();
  if (trimmed === "") {
    return "";
  }

  const leadingPlus = trimmed.startsWith("+");
  const digits = trimmed.replace(/\D/g, "");
  if (digits === "") {
    return leadingPlus ? "+" : "";
  }

  if (leadingPlus && !digits.startsWith(BELARUS_CALLING_CODE)) {
    return `+${digits}`;
  }

  let national = digits;
  if (national.startsWith(BELARUS_CALLING_CODE)) {
    national = national.slice(BELARUS_CALLING_CODE.length);
  } else if (!leadingPlus && national.startsWith("80")) {
    national = national.slice(2);
  }

  national = national.slice(0, 9);

  let formatted = "+375";

  if (national.length > 0) {
    formatted += ` (${national.slice(0, 2)}`;
  }

  if (national.length >= 2) {
    formatted += ")";
  }

  if (national.length > 2) {
    formatted += ` ${national.slice(2, 5)}`;
  }

  if (national.length > 5) {
    formatted += `-${national.slice(5, 7)}`;
  }

  if (national.length > 7) {
    formatted += `-${national.slice(7, 9)}`;
  }

  return formatted;
}
