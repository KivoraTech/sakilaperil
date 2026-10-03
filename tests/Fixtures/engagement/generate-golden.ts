/**
 * Golden fixtures for the PHP port of the public form / donation validators (App\Engagement\…).
 * Runs the SAME Zod schemas the browser uses (resources/js/lib/{forms,payments}/schemas.ts) over a
 * set of inputs and records Zod's verdict, transformed output and `z.flattenError().fieldErrors`.
 * tests/Unit/Engagement/ValidationGoldenTest.php asserts the PHP validators produce identical results.
 *
 *   npx tsx tests/Fixtures/engagement/generate-golden.ts
 */
import { writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { z } from "zod";
import {
  contactSchema,
  membershipSchema,
  newsletterSchema,
  partnerSchema,
  spamMetaSchema,
  volunteerSchema,
} from "../../../resources/js/lib/forms/schemas";
import { donationInputSchema } from "../../../resources/js/lib/payments/schemas";

const long = (n: number, ch = "a") => ch.repeat(n);

const contactOk = {
  name: "  Anna Rossi ",
  email: " Anna@Example.IT ",
  message: "Ciao!",
  consent: true,
};

const cases: Record<string, unknown[]> = {
  contact: [
    contactOk,
    { ...contactOk, phone: "", subject: "" },
    { ...contactOk, phone: "+39 333 123.45/67", subject: "press" },
    { ...contactOk, phone: "abc", subject: "hack" },
    { ...contactOk, phone: long(41, "1") },
    { ...contactOk, phone: long(41, "x") },
    { name: "A", email: "x", message: "", consent: false },
    { name: "", email: "", message: "   ", consent: "true" },
    {},
    { name: null, email: 3, message: ["x"], consent: 1 },
    { ...contactOk, name: long(121) },
    { ...contactOk, name: "😀".repeat(61) },
    { ...contactOk, name: "😀".repeat(60) },
    { ...contactOk, message: long(5001) },
    { ...contactOk, email: `${long(250)}@b.it` },
    { ...contactOk, email: "a..b@example.org" },
    { ...contactOk, email: ".a@example.org" },
    { ...contactOk, email: "a@example" },
    { ...contactOk, email: "o'neil+tag@sub.example.co.uk" },
    { ...contactOk, email: "a@-example.org" },
    { ...contactOk, extra: "stripped", hp_extra: "x" },
    { ...contactOk, subject: null },
  ],
  volunteer: [
    { firstName: "Mario", lastName: "Bianchi", email: "m@b.it", consent: true },
    {
      firstName: "Mario",
      lastName: "Bianchi",
      email: "m@b.it",
      consent: true,
      country: "  ",
      city: "Torino",
      skills: long(1001),
      availability: "weekends",
      message: "",
    },
    { firstName: "", lastName: "", email: "", consent: false },
  ],
  membership: [
    { firstName: "A", lastName: "B", email: "a@b.it", membershipType: "ordinary", consent: true },
    { firstName: "A", lastName: "B", email: "a@b.it", membershipType: "", consent: true },
    { firstName: "A", lastName: "B", email: "a@b.it", membershipType: "gold", consent: true },
    { firstName: "A", lastName: "B", email: "a@b.it", consent: true, address: long(301) },
  ],
  partner: [
    { organization: "Ristorante", contactName: "Lu", email: "l@r.it", message: "Hi", consent: true },
    {
      organization: "Ristorante",
      contactName: "Lu",
      email: "l@r.it",
      message: "Hi",
      consent: true,
      website: "https://ristorante.it",
      partnershipType: "restaurant",
    },
    {
      organization: "R",
      contactName: "L",
      email: "l@r.it",
      message: "Hi",
      consent: true,
      website: "ristorante",
      partnershipType: "",
    },
    {
      organization: "R",
      contactName: "L",
      email: "l@r.it",
      message: "Hi",
      consent: true,
      website: "",
      partnershipType: "bank",
    },
    {
      organization: "R",
      contactName: "L",
      email: "l@r.it",
      message: "Hi",
      consent: true,
      website: `https://${long(500)}.it`,
    },
  ],
  newsletter: [
    { email: "News@Example.org", consent: true },
    { email: "bad", consent: false },
    { email: "", consent: true },
    {},
  ],
  spamMeta: [
    {},
    { hp_extra: "", startedAt: 1700000000000, source: "footer" },
    { source: "block:cmumk6sxq0001m81mfbpk73tw" },
    { source: "<script>" },
    { source: long(61) },
    { startedAt: -1 },
    { startedAt: 1.5 },
    { startedAt: "123" },
    { hp_extra: long(501) },
    { hp_extra: 5 },
    { turnstileToken: null },
    { source: "  donate-page  " },
  ],
  donation: [
    { provider: "STRIPE", amount: 25, consent: true },
    { provider: "STRIPE", amount: 25, frequency: "monthly", consent: true, donorEmail: " A@B.IT " },
    { provider: "PAYPAL", amount: 25, frequency: "monthly", consent: true },
    { provider: "ESEWA", amount: 5, consent: true },
    { provider: "ESEWA", amount: 1000, consent: true },
    { provider: "KHALTI", amount: 500001, consent: true },
    { provider: "STRIPE", amount: 1, consent: true },
    { provider: "STRIPE", amount: 10.555, consent: true },
    { provider: "STRIPE", amount: 10.55, consent: true },
    { provider: "STRIPE", amount: 0, consent: true },
    { provider: "STRIPE", amount: null, consent: true },
    { provider: "STRIPE", amount: "25", consent: true },
    { provider: "STRIPE", amount: 25, consent: false },
    { provider: "STRIPE", amount: 25, consent: true, newsletter: true },
    { provider: "STRIPE", amount: 25, consent: true, newsletter: true, donorEmail: "" },
    { provider: "STRIPE", amount: 25, consent: true, donorEmail: "nope" },
    { provider: "STRIPE", amount: 25, consent: true, donorName: "", message: "  hi  " },
    { provider: "STRIPE", amount: 25, consent: true, projectId: "  p1  ", campaignId: "" },
    { provider: "STRIPE", amount: 25, consent: true, projectId: long(65) },
    { provider: "STRIPE", amount: 25, consent: true, isAnonymous: "yes" },
    { provider: "BANK", amount: 25, consent: true },
    { provider: "STRIPE", amount: 25, frequency: "weekly", consent: true },
    { provider: "STRIPE", amount: -5, consent: false, frequency: "monthly" },
    {},
  ],
};

const schemas: Record<string, z.ZodType> = {
  contact: contactSchema,
  volunteer: volunteerSchema,
  membership: membershipSchema,
  partner: partnerSchema,
  newsletter: newsletterSchema,
  spamMeta: spamMetaSchema,
  donation: donationInputSchema,
};

const out: Record<string, unknown[]> = {};
for (const [name, inputs] of Object.entries(cases)) {
  out[name] = inputs.map((input) => {
    const r = schemas[name]!.safeParse(input);
    return r.success
      ? { input, success: true, data: r.data }
      : { input, success: false, fieldErrors: z.flattenError(r.error).fieldErrors };
  });
}

const target = resolve(dirname(fileURLToPath(import.meta.url)), "validation-golden.json");
writeFileSync(target, `${JSON.stringify(out, null, 2)}\n`);
console.log(`wrote ${target}`);
