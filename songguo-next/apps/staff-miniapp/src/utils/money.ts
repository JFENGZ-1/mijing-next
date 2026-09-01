export function centsToDecimal(value: number | string | null | undefined): string | null {
  if (value == null || value === "") return null;
  const cents = Number(value);
  if (!Number.isFinite(cents)) return null;
  return (cents / 100).toFixed(2);
}

export function decimalToCents(value: number | string | null | undefined): number | null {
  if (value == null || String(value).trim() === "") return null;
  const amount = Number(value);
  if (!Number.isFinite(amount)) return null;
  return Math.round(amount * 100);
}
