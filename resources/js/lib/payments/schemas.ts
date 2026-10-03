import { z } from "zod";

/**
 * Donation input + amount rules. Client-safe (used by the DonateWidget and the API route).
 * Amounts are entered in MAJOR units (euros / rupees) and stored in minor units (cents / paisa).
 */
export const onlineProviders = ["STRIPE", "PAYPAL", "ESEWA", "KHALTI"] as const;
export type OnlineProvider = (typeof onlineProviders)[number];

export const currencyFor: Record<OnlineProvider, "EUR" | "NPR"> = {
  STRIPE: "EUR",
  PAYPAL: "EUR",
  ESEWA: "NPR",
  KHALTI: "NPR",
};

/** Min/max per currency in major units. Khalti's minimum is Rs 10. */
export const amountLimits = {
  EUR: { min: 2, max: 10_000 },
  NPR: { min: 10, max: 500_000 },
} as const;

/** Default NPR presets — `[[TODO: confirm NPR presets with the client]]` (design-system §8.10). */
export const DEFAULT_NPR_PRESETS = [500, 1000, 2500, 5000];

export const frequencies = ["once", "monthly"] as const;
export type Frequency = (typeof frequencies)[number];

export function toMinor(major: number): number {
  return Math.round(major * 100);
}

export function formatMoney(minor: number, currency: string, locale: string): string {
  const major = minor / 100;
  return new Intl.NumberFormat(locale === "en" ? "en-GB" : "it-IT", {
    style: "currency",
    currency,
    minimumFractionDigits: major % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(major);
}

const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max, "tooLong")
    .optional()
    .transform((v) => (v ? v : undefined));

export const donationInputSchema = z
  .object({
    provider: z.enum(onlineProviders),
    amount: z.number({ error: "invalidAmount" }).positive("invalidAmount"),
    frequency: z.enum(frequencies).default("once"),
    projectId: z.string().trim().max(64).optional(),
    campaignId: z.string().trim().max(64).optional(),
    donorName: optionalText(120),
    donorEmail: z
      .string()
      .trim()
      .max(254)
      .optional()
      .transform((v) => (v ? v.toLowerCase() : undefined))
      .pipe(z.email("invalidEmail").optional()),
    message: optionalText(500),
    isAnonymous: z.boolean().default(false),
    newsletter: z.boolean().default(false),
    consent: z.literal(true, { error: "consentRequired" }),
  })
  .superRefine((v, ctx) => {
    const currency = currencyFor[v.provider];
    const { min, max } = amountLimits[currency];
    if (v.amount < min || v.amount > max) {
      ctx.addIssue({ code: "custom", path: ["amount"], message: "amountOutOfRange" });
    }
    if (Math.abs(v.amount * 100 - Math.round(v.amount * 100)) > 1e-6) {
      ctx.addIssue({ code: "custom", path: ["amount"], message: "invalidAmount" });
    }
    if (v.frequency === "monthly" && v.provider !== "STRIPE") {
      ctx.addIssue({ code: "custom", path: ["frequency"], message: "monthlyCardOnly" });
    }
    if (v.newsletter && !v.donorEmail) {
      ctx.addIssue({ code: "custom", path: ["donorEmail"], message: "required" });
    }
  });
export type DonationInput = z.input<typeof donationInputSchema>;
export type DonationData = z.output<typeof donationInputSchema>;
