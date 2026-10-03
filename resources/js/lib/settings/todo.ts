/** "[[TODO: ...]]" marks client facts we do not know yet. Never invent them. */
export const TODO_PATTERN = /\[\[TODO:[^\]]*\]\]/;

export function isTodo(value: unknown): boolean {
  return typeof value === "string" && TODO_PATTERN.test(value);
}

/** Returns undefined for TODO placeholders so the public site can hide the field. */
export function known<T>(value: T): T | undefined {
  return isTodo(value) ? undefined : value;
}
