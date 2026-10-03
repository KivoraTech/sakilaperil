import { z } from "zod";

/**
 * Public form schemas — shared by the React Hook Form client (zodResolver) and the API route
 * handlers (server re-validation). Client-safe: no server imports.
 *
 * Error messages are i18n KEYS under `forms.errors.*` (messages/*.json), not user-facing text.
 */
export const formErrorKeys = [
  "required",
  "invalidEmail",
  "tooLong",
  "consentRequired",
  "invalidUrl",
  "invalid",
] as const;
export type FormErrorKey = (typeof formErrorKeys)[number];

const text = (max: number) => z.string().trim().max(max, "tooLong");
const required = (max: number) => text(max).min(1, "required");
const optional = (max: number) =>
  text(max)
    .optional()
    .transform((v) => (v ? v : undefined));

/** Optional `<select>`: the empty "—" option posts "", which means "not chosen". */
const optionalEnum = <const T extends readonly [string, ...string[]]>(values: T) =>
  z.preprocess((v) => (v === "" ? undefined : v), z.enum(values, { error: "invalid" }).optional());

export const emailField = z
  .string()
  .trim()
  .min(1, "required")
  .max(254, "tooLong")
  .pipe(z.email("invalidEmail"))
  .transform((v) => v.toLowerCase());

const phoneField = text(40)
  .regex(/^[+()\d\s./-]*$/, "invalid")
  .optional()
  .transform((v) => (v ? v : undefined));

/** GDPR: the privacy checkbox must be ticked; we store the timestamp server-side. */
const consentField = z.literal(true, { error: "consentRequired" });

/** Anti-spam metadata posted with every public form (validated by lib/spam, not stored). */
export const spamMetaSchema = z.object({
  hp_extra: z.string().max(500).optional(),
  startedAt: z.number().int().nonnegative().optional(),
  turnstileToken: z.string().max(4096).optional(),
  source: z
    .string()
    .trim()
    .max(60)
    // e.g. "footer", "donate-page", "block:<sectionId>"
    .regex(/^[a-z0-9:_-]*$/i)
    .optional(),
});

export const contactSubjects = [
  "info",
  "donations",
  "volunteering",
  "partnership",
  "press",
  "other",
] as const;
export const contactSchema = z.object({
  name: required(120),
  email: emailField,
  phone: phoneField,
  subject: optionalEnum(contactSubjects),
  message: required(5000),
  consent: consentField,
});
export type ContactInput = z.input<typeof contactSchema>;

export const volunteerSchema = z.object({
  firstName: required(80),
  lastName: required(80),
  email: emailField,
  phone: phoneField,
  country: optional(80),
  city: optional(120),
  skills: optional(1000),
  availability: optional(500),
  message: optional(3000),
  consent: consentField,
});
export type VolunteerInput = z.input<typeof volunteerSchema>;

export const membershipTypes = ["ordinary", "supporting"] as const;
export const membershipSchema = z.object({
  firstName: required(80),
  lastName: required(80),
  email: emailField,
  phone: phoneField,
  address: optional(300),
  city: optional(120),
  country: optional(80),
  membershipType: z.enum(membershipTypes, { error: "required" }),
  message: optional(3000),
  consent: consentField,
});
export type MembershipInput = z.input<typeof membershipSchema>;

export const partnershipTypes = ["restaurant", "company", "school", "association", "other"] as const;
export const partnerSchema = z.object({
  organization: required(200),
  contactName: required(120),
  email: emailField,
  phone: phoneField,
  website: text(500)
    .refine((v) => v === "" || /^https?:\/\/[^\s]+\.[^\s]+$/i.test(v), "invalidUrl")
    .optional()
    .transform((v) => (v ? v : undefined)),
  partnershipType: optionalEnum(partnershipTypes),
  message: required(5000),
  consent: consentField,
});
export type PartnerInput = z.input<typeof partnerSchema>;

export const newsletterSchema = z.object({
  email: emailField,
  consent: consentField,
});
export type NewsletterInput = z.input<typeof newsletterSchema>;

export const formSchemas = {
  contact: contactSchema,
  volunteer: volunteerSchema,
  membership: membershipSchema,
  partner: partnerSchema,
} as const;
export type FormType = keyof typeof formSchemas;
export const formTypes = Object.keys(formSchemas) as FormType[];

export function isFormType(value: unknown): value is FormType {
  return typeof value === "string" && value in formSchemas;
}
