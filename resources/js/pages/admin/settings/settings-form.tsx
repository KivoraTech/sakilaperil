"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { toNestErrors } from "@hookform/resolvers";
import { Loader2, Save } from "lucide-react";
import {
  Controller,
  FormProvider,
  get,
  useForm,
  useFormContext,
  useFormState,
  type FieldError,
  type Resolver,
} from "react-hook-form";
import { toast } from "sonner";
import { useUnsavedChangesWarning } from "@/components/admin/page-builder/use-unsaved-changes";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { updateSettingsAction } from "@/lib/actions/settings";
import { settingsSectionSchemas, stripEmpty, type SettingsSection } from "@/lib/settings/sections";
import { socialPlatforms } from "@/lib/settings/schema";
import {
  ImageField,
  ListField,
  LocalizedField,
  NumberField,
  SelectField,
  SwitchField,
  TextField,
} from "./fields";

type Values = Record<string, unknown>;

function resolverFor(section: SettingsSection): Resolver<Values> {
  return (values, _ctx, options) => {
    const r = settingsSectionSchemas[section].safeParse(stripEmpty(values));
    if (r.success) return { values: values, errors: {} };
    const flat: Record<string, FieldError> = {};
    for (const issue of r.error.issues) {
      const path = issue.path.map(String).join(".");
      if (!flat[path]) flat[path] = { type: "validation", message: issue.message };
    }
    return { values: {}, errors: toNestErrors(flat, options) };
  };
}

/** The slice of settings each section edits (default values for its form). */
export function SettingsForm({ section, initial }: { section: SettingsSection; initial: Values }) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const form = useForm<Values>({ defaultValues: initial, resolver: resolverFor(section) });
  useUnsavedChangesWarning(form.formState.isDirty);

  const onSubmit = form.handleSubmit(
    (values) =>
      start(async () => {
        const r = await updateSettingsAction({ section, data: values });
        if (!r.ok) {
          for (const [path, msgs] of Object.entries(r.fieldErrors ?? {}))
            form.setError(path, { message: msgs[0] });
          toast.error(r.message);
          return;
        }
        toast.success(r.message ?? "Saved");
        form.reset(values);
        router.refresh();
      }),
    () => toast.error("Please check the highlighted fields."),
  );

  return (
    <FormProvider {...form}>
      <form onSubmit={onSubmit} noValidate className="grid gap-8">
        {section === "organization" ? <OrganizationFields /> : null}
        {section === "contact" ? <ContactFields /> : null}
        {section === "donations" ? <DonationFields /> : null}
        {section === "impact" ? <ImpactFields /> : null}
        {section === "seo" ? <SeoFields /> : null}
        {section === "appearance" ? <AppearanceFields /> : null}
        {section === "messages" ? <MessageFields /> : null}
        <div className="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
          {form.formState.isDirty ? (
            <span className="text-xs font-medium text-amber-700">Unsaved changes</span>
          ) : null}
          <Button type="submit" disabled={pending || !form.formState.isDirty}>
            {pending ? <Loader2 className="animate-spin" aria-hidden /> : <Save aria-hidden />}
            Save settings
          </Button>
        </div>
      </form>
    </FormProvider>
  );
}

function Group({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="grid gap-4 rounded-xl border bg-background p-4 md:p-6">
      <div>
        <h2 className="font-semibold">{title}</h2>
        {description ? <p className="text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {children}
    </section>
  );
}

/** Collapsed area for technical fields most volunteers never need. */
function Advanced({ description, children }: { description: string; children: React.ReactNode }) {
  return (
    <details className="rounded-lg border px-3 py-2">
      <summary className="cursor-pointer text-sm font-medium outline-none focus-visible:ring-3 focus-visible:ring-ring/50">
        Advanced
        <span className="block text-xs font-normal text-muted-foreground">{description}</span>
      </summary>
      <div className="mt-3 grid gap-4">{children}</div>
    </details>
  );
}

const todoHint =
  "Fields marked “To confirm” still hold an example value, so the website hides them until you fill them in.";

function OrganizationFields() {
  return (
    <>
      <Group title="Association" description={todoHint}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="orgName" label="Public name" maxLength={200} />
          <TextField name="organization.legalName" label="Legal name" maxLength={200} />
          <TextField
            name="organization.taxCode"
            label="Tax code (codice fiscale)"
            hint="The association's tax code, shown in the footer and on receipts."
            maxLength={40}
          />
          <TextField
            name="organization.registrationNumber"
            label="Registration number"
            hint="Your entry in the Italian third-sector register (RUNTS), if you have one."
            maxLength={100}
          />
          <TextField name="organization.registeredOffice" label="Registered office" maxLength={300} />
          <NumberField name="organization.foundedYear" label="Founded in" min={1900} max={2100} step={1} />
        </div>
      </Group>
      <Group
        title="5×1000"
        description="Italian taxpayers can give 5×1000 of their taxes to you. When on, the donate section explains how."
      >
        <SwitchField name="features.fiveXMille.enabled" label="Show the 5×1000 box" />
        <TextField
          name="features.fiveXMille.taxCode"
          label="Tax code to write on the tax return"
          hint="Usually the same as your codice fiscale."
          maxLength={40}
        />
      </Group>
      <Group title="Example content notice">
        <SwitchField
          name="features.demoContent"
          label="Show the “example content” notice on the website"
          hint="Keep this on while texts and photos are still examples. Turn it off once your real content is in."
        />
      </Group>
    </>
  );
}

function ContactFields() {
  return (
    <>
      <Group title="Contact details" description={todoHint}>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="contact.email" label="Email" type="email" maxLength={200} />
          <TextField name="contact.phone" label="Phone" type="tel" maxLength={40} />
          <TextField name="contact.whatsapp" label="WhatsApp" type="tel" maxLength={40} />
        </div>
        <LocalizedField name="contact.address" label="Address" multiline />
      </Group>
      <Group title="Map on the contact page">
        <TextField
          name="contact.mapEmbedUrl"
          label="Map link"
          type="url"
          maxLength={1000}
          hint='In Google Maps: find your address, click Share → Embed a map, and copy only the address inside src="…" (it starts with https://).'
        />
        <Advanced description="Exact map position, used by search engines. Optional.">
          <div className="grid gap-4 sm:grid-cols-2">
            <NumberField name="contact.latitude" label="Latitude" min={-90} max={90} />
            <NumberField name="contact.longitude" label="Longitude" min={-180} max={180} />
          </div>
        </Advanced>
      </Group>
      <Group title="Social profiles">
        <ListField
          name="socialLinks"
          label="Profiles"
          itemLabel="Profile"
          max={10}
          newItem={() => ({ platform: "facebook", url: "" })}
        >
          {(n) => (
            <>
              <SelectField
                name={`${n}.platform`}
                label="Platform"
                options={socialPlatforms.map((p) => ({
                  value: p,
                  label: p === "x" ? "X (Twitter)" : p[0]!.toUpperCase() + p.slice(1),
                }))}
              />
              <TextField name={`${n}.url`} label="Link to your page" type="url" placeholder="https://…" />
            </>
          )}
        </ListField>
      </Group>
    </>
  );
}

function PresetAmountsField() {
  const { control } = useFormContext();
  const { errors } = useFormState({ name: "donation.presetAmounts" });
  const error = (get(errors, "donation.presetAmounts") as { message?: string } | undefined)?.message;
  return (
    <Controller
      control={control}
      name="donation.presetAmounts"
      render={({ field }) => (
        <div className="grid gap-1.5">
          <Label htmlFor="preset-amounts">Suggested amounts (€)</Label>
          <Input
            id="preset-amounts"
            defaultValue={((field.value as number[] | undefined) ?? []).join(", ")}
            onChange={(e) =>
              field.onChange(
                e.target.value
                  .split(/[,\s]+/)
                  .filter(Boolean)
                  .map((x) => Number(x)),
              )
            }
            aria-describedby="preset-amounts-hint"
            className="max-w-72"
          />
          <p id="preset-amounts-hint" className="text-xs text-muted-foreground">
            Up to 6 whole amounts separated by commas, e.g. 10, 25, 50, 100.
          </p>
          {error ? <p className="text-xs font-medium text-destructive">{error}</p> : null}
        </div>
      )}
    />
  );
}

function DonationFields() {
  return (
    <>
      <Group
        title="Bank transfer"
        description={`Always available, even without card payments configured. ${todoHint}`}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField name="bankDetails.accountHolder" label="Account holder" maxLength={200} />
          <TextField name="bankDetails.iban" label="IBAN" maxLength={50} />
          <TextField name="bankDetails.bic" label="BIC / SWIFT" maxLength={20} />
          <TextField name="bankDetails.bankName" label="Bank name" maxLength={200} />
        </div>
        <LocalizedField
          name="bankDetails.reference"
          label="Payment reason (causale)"
          hint="What donors write in the transfer, e.g. “Donazione Sakila per il Nepal”."
        />
        <ImageField
          name="bankDetails.qrImageId"
          label="Bank transfer QR code"
          hint="Optional. A QR code donors scan with their banking app (EPC/SEPA QR)."
        />
      </Group>
      <Group
        title="Nepal: eSewa & Khalti"
        description="QR codes and merchant IDs shown to donors in Nepal. API keys are set on the server, never here."
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <ImageField name="nepalPayments.esewa.qrImageId" label="eSewa QR code" />
          <TextField name="nepalPayments.esewa.merchantId" label="eSewa merchant ID" maxLength={100} />
          <ImageField name="nepalPayments.khalti.qrImageId" label="Khalti QR code" />
          <TextField name="nepalPayments.khalti.merchantId" label="Khalti merchant ID" maxLength={100} />
        </div>
      </Group>
      <Group title="Donation form">
        <PresetAmountsField />
        <NumberField
          name="donation.defaultAmount"
          label="Pre-selected amount (€)"
          min={1}
          max={100000}
          step={1}
        />
        <ListField
          name="donation.impactNotes"
          label="What a gift does"
          itemLabel="Note"
          max={10}
          newItem={() => ({ amount: 10, text: { it: "", en: "" } })}
        >
          {(n) => (
            <>
              <NumberField name={`${n}.amount`} label="Amount (€)" min={1} step={1} />
              <div className="sm:col-span-2">
                <LocalizedField name={`${n}.text`} label="Text" />
              </div>
            </>
          )}
        </ListField>
      </Group>
    </>
  );
}

function ImpactFields() {
  return (
    <Group
      title="Impact numbers"
      description="Only real, verified figures. Placeholder numbers from the demo must be replaced before launch."
    >
      <ListField
        name="impactStats"
        label="Figures"
        itemLabel="Figure"
        max={8}
        newItem={() => ({ key: "", value: 0, suffix: "", label: { it: "", en: "" } })}
      >
        {(n) => (
          <>
            <NumberField name={`${n}.value`} label="Number" hint="Just the number, e.g. 1200." min={0} />
            <TextField
              name={`${n}.suffix`}
              label="After the number"
              hint="Optional, e.g. + or %"
              maxLength={8}
            />
            <NumberField
              name={`${n}.progress`}
              label="Progress bar fill (optional)"
              hint="Use a decimal: 0.75 fills the bar to 75%."
              min={0}
              max={1}
              step={0.01}
            />
            <TextField
              name={`${n}.key`}
              label="Short name (not shown on the site)"
              hint="One word to tell figures apart, e.g. families."
              maxLength={40}
            />
            <div className="sm:col-span-2">
              <LocalizedField name={`${n}.label`} label="Label" />
            </div>
          </>
        )}
      </ListField>
    </Group>
  );
}

function SeoFields() {
  return (
    <>
      <Group
        title="Google & social sharing"
        description="Used for any page that has no Google text of its own."
      >
        <LocalizedField name="seo.defaultTitle" label="Website title on Google" />
        <LocalizedField name="seo.defaultDescription" label="Website description on Google" multiline />
        <ImageField
          name="seo.ogImageId"
          label="Picture shown when a link is shared"
          hint="Used on WhatsApp, Facebook and similar. A landscape photo, ideally 1200×630 pixels."
        />
        <Advanced description="How page titles are built. Change only if you know why.">
          <LocalizedField
            name="seo.titleTemplate"
            label="Page title pattern"
            hint="%s is replaced by the page title, e.g. “%s · Sakila per il Nepal”."
          />
        </Advanced>
      </Group>
      <Group title="Visitor statistics" description="Optional. Leave empty to turn statistics off.">
        <Advanced description="Codes from Plausible or Google Analytics, usually set up by your web person.">
          <TextField
            name="analytics.plausibleDomain"
            label="Plausible domain"
            placeholder="sakilaperilnepal.org"
          />
          <TextField name="analytics.gaMeasurementId" label="Google Analytics 4 ID" placeholder="G-XXXXXXX" />
        </Advanced>
      </Group>
    </>
  );
}

function AppearanceFields() {
  return (
    <>
      <Group title="Brand images">
        <div className="grid gap-4 sm:grid-cols-2">
          <ImageField
            name="appearance.logoImageId"
            label="Logo"
            hint="Leave empty to use the bundled logo."
          />
          <ImageField name="appearance.faviconImageId" label="Favicon" hint="Square PNG, at least 512×512." />
        </div>
      </Group>
      <Group title="Style" description="Only approved options that keep the brand consistent and accessible.">
        <SelectField
          name="appearance.accent"
          label="Accent colour"
          options={[
            { value: "sindoor", label: "Sindoor red", swatch: "#B5452F" },
            { value: "marigold", label: "Marigold", swatch: "#E09A2B" },
            { value: "himalaya", label: "Himalaya blue", swatch: "#1E3A5F" },
          ]}
        />
        <SelectField
          name="appearance.headerStyle"
          label="Top menu bar"
          options={[
            { value: "transparent-on-hero", label: "Transparent over the opening photo" },
            { value: "solid", label: "Always solid" },
          ]}
        />
        <SwitchField
          name="appearance.enableMotion"
          label="Animations on the website"
          hint="Photos and text gently appear as visitors scroll. Visitors who ask their device for less motion never see animations."
        />
      </Group>
    </>
  );
}

function MessageFields() {
  const forms = [
    { key: "contact", label: "Contact form" },
    { key: "volunteer", label: "Volunteer form" },
    { key: "membership", label: "Membership form" },
    { key: "partner", label: "Partner form" },
    { key: "newsletter", label: "Newsletter sign-up" },
  ];
  return (
    <>
      {forms.map((f) => (
        <Group key={f.key} title={f.label}>
          <LocalizedField name={`formMessages.${f.key}.intro`} label="Intro text" multiline />
          <LocalizedField name={`formMessages.${f.key}.success`} label="Thank-you message" multiline />
        </Group>
      ))}
      <Group title="Donations">
        <LocalizedField name="formMessages.donation.thankYou" label="Thank-you message" multiline />
        <LocalizedField name="formMessages.donation.cancelled" label="Payment cancelled message" multiline />
      </Group>
    </>
  );
}
