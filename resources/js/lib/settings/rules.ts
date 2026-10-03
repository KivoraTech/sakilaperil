import { isTodo } from "./todo";

/**
 * Format checks for the facts an admin types in Settings (contact email, phones, IBAN, BIC).
 * Applied when SAVING a section (settingsSectionSchemas), not when reading stored settings, so
 * "[[TODO: …]]" placeholders and values saved before these rules keep rendering.
 * Mirrored in PHP by App\Settings\SettingsRules — keep both in sync.
 */
const EMAIL = /^[^\s@<>()[\],;:"]+@[^\s@<>()[\],;:"]+\.[A-Za-z]{2,}$/;
const PHONE = /^\+?[0-9 ()./-]+$/;
const BIC = /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/;

const blankOrTodo = (v: string | undefined): boolean => v === undefined || v.trim() === "" || isTodo(v);

export function isValidEmail(v: string): boolean {
  return v.length <= 200 && EMAIL.test(v.trim());
}

export function isValidPhone(v: string): boolean {
  const t = v.trim();
  return PHONE.test(t) && t.replace(/\D/g, "").length >= 6 && t.replace(/\D/g, "").length <= 15;
}

/** ISO 13616: country + 2 check digits + BBAN, mod-97 remainder 1. Spaces are ignored. */
export function isValidIban(v: string): boolean {
  const iban = v.replace(/\s+/g, "").toUpperCase();
  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(iban)) return false;
  const rearranged = iban.slice(4) + iban.slice(0, 4);
  let remainder = 0;
  for (const ch of rearranged) {
    const n = ch >= "A" ? ch.charCodeAt(0) - 55 : Number(ch);
    remainder = (remainder * (n > 9 ? 100 : 10) + n) % 97;
  }
  return remainder === 1;
}

export function isValidBic(v: string): boolean {
  return BIC.test(v.replace(/\s+/g, "").toUpperCase());
}

export const settingsRuleMessages = {
  email: "Enter a valid email address, like info@example.org",
  phone: "Enter a phone number with digits only (spaces, + and - are fine), like +39 02 1234 5678",
  iban: "This IBAN is not valid. Check it against the bank statement: one wrong character sends donations nowhere",
  bic: "A BIC/SWIFT code has 8 or 11 letters and digits, like ABCDITMM",
} as const;

export type SettingsIssue = { path: (string | number)[]; message: string };

export function contactIssues(contact: {
  email?: string;
  phone?: string;
  whatsapp?: string;
}): SettingsIssue[] {
  const issues: SettingsIssue[] = [];
  if (!blankOrTodo(contact.email) && !isValidEmail(contact.email ?? ""))
    issues.push({ path: ["contact", "email"], message: settingsRuleMessages.email });
  for (const key of ["phone", "whatsapp"] as const) {
    if (!blankOrTodo(contact[key]) && !isValidPhone(contact[key] ?? ""))
      issues.push({ path: ["contact", key], message: settingsRuleMessages.phone });
  }
  return issues;
}

export function bankIssues(bank: { iban?: string; bic?: string }): SettingsIssue[] {
  const issues: SettingsIssue[] = [];
  if (!blankOrTodo(bank.iban) && !isValidIban(bank.iban ?? ""))
    issues.push({ path: ["bankDetails", "iban"], message: settingsRuleMessages.iban });
  if (!blankOrTodo(bank.bic) && !isValidBic(bank.bic ?? ""))
    issues.push({ path: ["bankDetails", "bic"], message: settingsRuleMessages.bic });
  return issues;
}
