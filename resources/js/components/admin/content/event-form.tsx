"use client";

import { useForm, useWatch } from "react-hook-form";
import { deleteEventAction, saveEventAction, setEventStatusAction } from "@/lib/actions/events";
import type { ContentStatusValue } from "@/lib/content/common";
import type { MediaAssetDTO } from "@/lib/content/media";
import { eventFormSchema, eventTimezones, type EventFormValues } from "@/lib/content/schemas";
import { ContentEditor, EditorSection, type EditorPerms } from "./content-editor";
import {
  DateTimeField,
  MediaFormField,
  RichTextField,
  SelectField,
  SlugField,
  SwitchField,
  TextField,
} from "./fields";
import { resolverFor } from "./resolver";
import { SeoFields } from "./seo-fields";
import { TranslationTabs } from "./translation-tabs";

export function EventForm({
  id,
  status,
  initialValues,
  coverAsset,
  perms,
  publicSlug,
}: {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: EventFormValues;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPerms;
  publicSlug: string | null;
}) {
  const form = useForm<EventFormValues>({
    resolver: resolverFor(eventFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });
  const title = useWatch({ control: form.control, name: "translations.it.title" });
  const allDay = useWatch({ control: form.control, name: "allDay" });

  return (
    <ContentEditor
      form={form}
      id={id}
      kindLabel="event"
      title={id ? title || "Untitled event" : "New event"}
      basePath="/admin/events"
      status={status}
      publicUrl={publicSlug ? `/it/events/${publicSlug}` : null}
      previewPath={id ? `/preview/it/events/${id}` : null}
      perms={perms}
      readOnlyReason="Authors can edit their own events while they are drafts. Ask an editor to change this one."
      onSave={(values, intent) => saveEventAction(id, values, intent)}
      onStatus={id ? (s) => setEventStatusAction(id, s) : undefined}
      onDelete={id ? () => deleteEventAction(id) : undefined}
      main={
        <TranslationTabs>
          {(l) => (
            <>
              <EditorSection title="Content">
                <TextField name={`translations.${l}.title`} label="Title" maxLength={200} required />
                <SlugField
                  name={`translations.${l}.slug`}
                  sourceName={`translations.${l}.title`}
                  prefix={`/${l}/events/`}
                  autoFollow={!initialValues.translations[l].slug}
                />
                <TextField
                  name={`translations.${l}.locationName`}
                  label="Venue name"
                  placeholder={l === "it" ? "Sala civica" : "Community hall"}
                  maxLength={120}
                />
                <TextField
                  name={`translations.${l}.summary`}
                  label="Summary"
                  multiline
                  rows={2}
                  maxLength={400}
                />
                <RichTextField name={`translations.${l}.body`} label="Details" />
              </EditorSection>
              <SeoFields locale={l} />
            </>
          )}
        </TranslationTabs>
      }
      sidebar={
        <>
          <EditorSection title="When">
            <SwitchField name="allDay" label="All-day event" />
            <DateTimeField name="startsAt" label="Starts" dateOnly={allDay} required />
            <DateTimeField name="endsAt" label="Ends" dateOnly={allDay} />
            <SelectField
              name="timezone"
              label="Time zone"
              options={eventTimezones.map((z) => ({
                value: z,
                label: z === "Europe/Rome" ? "Italy (Europe/Rome)" : "Nepal (Asia/Kathmandu)",
              }))}
            />
          </EditorSection>
          <EditorSection
            title="Where"
            description="Fill in what applies: an in-person event needs the address, an online one only the online link. The place name (e.g. “Teatro Verdi”) is in the Content section."
          >
            <TextField
              name="address"
              label="Street address"
              maxLength={200}
              description="e.g. Via Roma 12, 20121 Milano. Shown on the event page."
            />
            <TextField
              name="mapUrl"
              label="Map link (optional)"
              type="url"
              placeholder="https://maps.google.com/…"
              description="In Google Maps, find the place, click Share and paste the link here. Adds an “Open map” button."
            />
            <TextField
              name="onlineUrl"
              label="Online event link (optional)"
              type="url"
              placeholder="https://"
              description="Zoom, Meet or YouTube link for people joining from home."
            />
          </EditorSection>
          <EditorSection title="Registration">
            <TextField
              name="registrationUrl"
              label="Registration link"
              type="url"
              placeholder="https://"
              description="Shown as a “Register” button."
            />
          </EditorSection>
          <EditorSection title="Cover image">
            <MediaFormField name="coverImageId" label="Image" initialAsset={coverAsset} />
          </EditorSection>
          <EditorSection title="Publishing">
            <DateTimeField
              name="publishedAt"
              label="Publish from"
              description="Leave empty to publish immediately."
            />
            <SwitchField name="isFeatured" label="Show on the home page" />
          </EditorSection>
        </>
      }
    />
  );
}
