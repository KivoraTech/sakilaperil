import { z } from "zod";
import {
  analyticsSchema,
  appearanceSchema,
  bankDetailsSchema,
  contactSchema,
  donationSettingsSchema,
  featuresSchema,
  formMessagesSchema,
  impactStatsSchema,
  nepalPaymentsSchema,
  organizationSchema,
  seoSettingsSchema,
  socialLinksSchema,
  type SiteSettingsData,
} from "./schema";
import { bankIssues, contactIssues } from "./rules";

/**
 * The Settings screen is split into sections; each saves only its own SiteSettings columns.
 * Pure (client + server). The action validates with `settingsSectionSchemas[section]`.
 */
export const settingsSections = [
  "organization",
  "contact",
  "donations",
  "impact",
  "seo",
  "appearance",
  "messages",
] as const;
export type SettingsSection = (typeof settingsSections)[number];

export const settingsSectionLabels: Record<SettingsSection, { title: string; description: string }> = {
  organization: {
    title: "Organisation",
    description: "Name and legal details shown in the footer, the privacy page and on receipts.",
  },
  contact: {
    title: "Contacts & social",
    description: "Email, phone, address, the map and links to your social pages.",
  },
  donations: {
    title: "Donations & bank",
    description: "Bank details for transfers, QR codes, eSewa/Khalti and the suggested amounts.",
  },
  impact: {
    title: "Impact numbers",
    description: "Your key figures (e.g. children in school), shown by the “Impact numbers” sections.",
  },
  seo: {
    title: "Google & sharing",
    description: "How the site appears on Google and when links are shared, plus visitor statistics.",
  },
  appearance: { title: "Look & feel", description: "Logo, browser icon, accent colour and animations." },
  messages: { title: "Form messages", description: "Texts shown above forms and after someone sends one." },
};

export const settingsSectionSchemas = {
  organization: z.object({
    orgName: z.string().trim().min(1, "Required").max(200),
    organization: organizationSchema,
    features: featuresSchema,
  }),
  contact: z.object({ contact: contactSchema, socialLinks: socialLinksSchema }).superRefine((v, ctx) => {
    for (const issue of contactIssues(v.contact)) ctx.addIssue({ code: "custom", ...issue });
  }),
  donations: z
    .object({
      bankDetails: bankDetailsSchema,
      nepalPayments: nepalPaymentsSchema,
      donation: donationSettingsSchema,
    })
    .superRefine((v, ctx) => {
      for (const issue of bankIssues(v.bankDetails)) ctx.addIssue({ code: "custom", ...issue });
      if (!v.donation.presetAmounts.includes(v.donation.defaultAmount))
        ctx.addIssue({
          code: "custom",
          path: ["donation", "defaultAmount"],
          message: "The default amount must be one of the suggested amounts",
        });
    }),
  impact: z.object({ impactStats: impactStatsSchema }).superRefine((v, ctx) => {
    const seen = new Set<string>();
    v.impactStats.forEach((s, i) => {
      if (seen.has(s.key))
        ctx.addIssue({ code: "custom", path: ["impactStats", i, "key"], message: "Keys must be unique" });
      seen.add(s.key);
    });
  }),
  seo: z.object({ seo: seoSettingsSchema, analytics: analyticsSchema }),
  appearance: z.object({ appearance: appearanceSchema }),
  messages: z.object({ formMessages: formMessagesSchema }),
} satisfies Record<SettingsSection, z.ZodType>;

export type SettingsSectionInput<S extends SettingsSection> = z.input<(typeof settingsSectionSchemas)[S]>;

/**
 * Remove empty values coming from form inputs so optional fields validate: "" strings,
 * NaN numbers and nested objects left empty (e.g. an untouched `features.fiveXMille`).
 * Required fields become undefined → "Required". Top-level section objects (depth 1, e.g.
 * `nepalPayments`) are kept even when empty because their schemas require the object.
 */
export function stripEmpty(value: unknown, depth = 0): unknown {
  if (Array.isArray(value)) return value.map((v) => stripEmpty(v, depth + 1)).filter((v) => v !== undefined);
  if (typeof value === "string") return value.trim() === "" ? undefined : value;
  if (typeof value === "number") return Number.isNaN(value) ? undefined : value;
  if (value && typeof value === "object") {
    const out: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(value)) {
      const c = stripEmpty(v, depth + 1);
      if (c !== undefined) out[k] = c;
    }
    return depth >= 2 && Object.keys(out).length === 0 ? undefined : out;
  }
  return value;
}

/** The slice of settings each section form edits (called on the server, so it must not live in a client file). */
export function sectionValues(section: SettingsSection, s: SiteSettingsData): Record<string, unknown> {
  switch (section) {
    case "organization":
      return { orgName: s.orgName, organization: s.organization, features: s.features };
    case "contact":
      return { contact: s.contact, socialLinks: s.socialLinks };
    case "donations":
      return { bankDetails: s.bankDetails, nepalPayments: s.nepalPayments, donation: s.donation };
    case "impact":
      return { impactStats: s.impactStats };
    case "seo":
      return { seo: s.seo, analytics: s.analytics };
    case "appearance":
      return { appearance: s.appearance };
    case "messages":
      return { formMessages: s.formMessages };
  }
}
