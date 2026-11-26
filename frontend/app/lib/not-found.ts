export function isMissingResourceMessage(message: string): boolean {
  return /не найден/i.test(message);
}
