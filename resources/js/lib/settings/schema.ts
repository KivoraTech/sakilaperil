import { z } from "zod";
import { localizedString, optionalLocalizedString } from "@/lib/i18n/localized";

/**
 * Zod schemas for the JSON columns of SiteSettings (prisma/schema.prisma).
 * Unknown facts are stored as "[[TODO: ...]]" strings until the client confirms them;
 * the public site must render these gracefully (lib/settings/todo.ts).
 */
const https = z
  .string()
  .trim()
  .regex(/^https:\/\//, "Use an https:// URL")
  .max(1000);

export const organizationSchema = z.object({
  legalName: z.string().trim().max(200),
  taxCode: z.string().trim().max(40), // Italian codice fiscale (used for 5×1000)
  registrationNumber: z.string().trim().max(100).optional(),
  registeredOffice: z.string().trim().max(300),
  foundedYear: z.number().int().min(1900).max(2100).optional(),
});

export const contactSchema = z.object({
  email: z.string().trim().max(200),
  phone: z.string().trim().max(40).optional(),
  whatsapp: z.string().trim().max(40).optional(),
  address: optionalLocalizedString.optional(),
  mapEmbedUrl: https.optional(),
  latitude: z.number().min(-90).max(90).optional(),
  longitude: z.number().min(-180).max(180).optional(),
});

export const socialPlatforms = ["facebook", "instagram", "youtube", "tiktok", "linkedin", "x"] as const;
export const socialLinksSchema = z.array(z.object({ platform: z.enum(socialPlatforms), url: https })).max(10);

export const bankDetailsSchema = z.object({
  accountHolder: z.string().trim().max(200),
  iban: z.string().trim().max(50),
  bic: z.string().trim().max(20).optional(),
  bankName: z.string().trim().max(200).optional(),
  reference: optionalLocalizedString.optional(),
  qrImageId: z.string().max(64).optional(),
});

export const nepalPaymentsSchema = z.object({
  esewa: z
    .object({ qrImageId: z.string().max(64).optional(), merchantId: z.string().max(100).optional() })
    .optional(),
  khalti: z
    .object({ qrImageId: z.string().max(64).optional(), merchantId: z.string().max(100).optional() })
    .optional(),
});

export const donationSettingsSchema = z.object({
  currency: z.enum(["EUR"]).default("EUR"),
  presetAmounts: z.array(z.number().int().min(1).max(100_000)).min(1).max(6),
  defaultAmount: z.number().int().min(1).max(100_000),
  impactNotes: z
    .array(z.object({ amount: z.number().int().min(1), text: localizedString }))
    .max(10)
    .default([]),
});

export const impactStatsSchema = z
  .array(
    z.object({
      key: z.string().trim().min(1).max(40),
      value: z.number().min(0),
      suffix: z.string().trim().max(8).optional(),
      label: localizedString,
      progress: z.number().min(0).max(1).optional(),
    }),
  )
  .max(8);

export const seoSettingsSchema = z.object({
  titleTemplate: localizedString, // e.g. "%s · Sakila per il Nepal"
  defaultTitle: localizedString,
  defaultDescription: localizedString,
  ogImageId: z.string().max(64).optional(),
});

export const appearanceSchema = z.object({
  /** Approved presentation options only — never raw CSS. */
  headerStyle: z.enum(["transparent-on-hero", "solid"]).default("transparent-on-hero"),
  enableMotion: z.boolean().default(true),
  accent: z.enum(["sindoor", "marigold", "himalaya"]).default("sindoor"),
  /** Optional MediaAsset ids (builder, additive). Unset = the bundled /logo.png and app/icon.png. */
  logoImageId: z.string().max(64).optional(),
  faviconImageId: z.string().max(64).optional(),
});

export const analyticsSchema = z.object({
  plausibleDomain: z.string().trim().max(200).optional(),
  gaMeasurementId: z
    .string()
    .trim()
    .regex(/^G-[A-Z0-9]{4,20}$/, "Format G-XXXXXXX")
    .optional(),
});

const formMessage = z.object({ success: localizedString.optional(), intro: localizedString.optional() });
export const formMessagesSchema = z.object({
  contact: formMessage.optional(),
  volunteer: formMessage.optional(),
  membership: formMessage.optional(),
  partner: formMessage.optional(),
  newsletter: formMessage.optional(),
  donation: z
    .object({ thankYou: localizedString.optional(), cancelled: localizedString.optional() })
    .optional(),
});

export const featuresSchema = z.object({
  /** True while the site shows the demo's placeholder copy/photos; the public site shows a small notice. */
  demoContent: z.boolean().optional(),
  fiveXMille: z.object({ enabled: z.boolean(), taxCode: z.string().max(40).optional() }).optional(),
});

export const siteSettingsSchema = z.object({
  orgName: z.string().trim().min(1).max(200),
  organization: organizationSchema,
  contact: contactSchema,
  socialLinks: socialLinksSchema,
  bankDetails: bankDetailsSchema,
  nepalPayments: nepalPaymentsSchema,
  donation: donationSettingsSchema,
  impactStats: impactStatsSchema,
  seo: seoSettingsSchema,
  appearance: appearanceSchema,
  analytics: analyticsSchema,
  formMessages: formMessagesSchema,
  features: featuresSchema,
});
export type SiteSettingsData = z.infer<typeof siteSettingsSchema>;
