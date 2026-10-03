"use client";

import Link from "next/link";
import { useForm, useWatch } from "react-hook-form";
import { deleteProjectAction, saveProjectAction, setProjectStatusAction } from "@/lib/actions/projects";
import type { ContentStatusValue } from "@/lib/content/common";
import type { MediaAssetDTO } from "@/lib/content/media";
import { currencies, projectFormSchema, projectStages, type ProjectFormValues } from "@/lib/content/schemas";
import { ContentEditor, EditorAdvanced, EditorSection, type EditorPerms } from "./content-editor";
import {
  DateTimeField,
  MediaFormField,
  RichTextField,
  SelectField,
  SlugField,
  SwitchField,
  TextField,
} from "./fields";
import { StatusBadge } from "./list-ui";
import { resolverFor } from "./resolver";
import { SeoFields } from "./seo-fields";
import { TranslationTabs } from "./translation-tabs";

const stageLabels: Record<(typeof projectStages)[number], string> = {
  PLANNED: "Planned",
  ACTIVE: "Active",
  COMPLETED: "Completed",
};

export function ProjectForm({
  id,
  status,
  initialValues,
  coverAsset,
  perms,
  publicSlug,
  albums = [],
}: {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: ProjectFormValues;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPerms;
  publicSlug: string | null;
  albums?: { id: string; title: string; status: string }[];
}) {
  const form = useForm<ProjectFormValues>({
    resolver: resolverFor(projectFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });
  const title = useWatch({ control: form.control, name: "translations.it.title" });

  return (
    <ContentEditor
      form={form}
      id={id}
      kindLabel="project"
      title={id ? title || "Untitled project" : "New project"}
      basePath="/admin/projects"
      status={status}
      publicUrl={publicSlug ? `/it/projects/${publicSlug}` : null}
      previewPath={id ? `/preview/it/projects/${id}` : null}
      perms={perms}
      readOnlyReason="Authors can edit their own projects while they are drafts. Ask an editor to change this one."
      onSave={(values, intent) => saveProjectAction(id, values, intent)}
      onStatus={id ? (s) => setProjectStatusAction(id, s) : undefined}
      onDelete={id ? () => deleteProjectAction(id) : undefined}
      main={
        <TranslationTabs>
          {(l) => (
            <>
              <EditorSection title="Content">
                <TextField name={`translations.${l}.title`} label="Title" maxLength={200} required />
                <SlugField
                  name={`translations.${l}.slug`}
                  sourceName={`translations.${l}.title`}
                  prefix={`/${l}/projects/`}
                  autoFollow={!initialValues.translations[l].slug}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name={`translations.${l}.sector`}
                    label="Sector"
                    placeholder={l === "it" ? "Istruzione" : "Education"}
                    maxLength={60}
                  />
                  <TextField
                    name={`translations.${l}.metaLine`}
                    label="Key facts line"
                    description="Short line under the title, e.g. “86 students · since 2024”."
                    maxLength={120}
                  />
                </div>
                <TextField
                  name={`translations.${l}.summary`}
                  label="Summary"
                  description="Shown on project cards."
                  multiline
                  rows={3}
                  maxLength={400}
                />
                <RichTextField name={`translations.${l}.body`} label="Full description" />
              </EditorSection>
              <SeoFields locale={l} />
            </>
          )}
        </TranslationTabs>
      }
      sidebar={
        <>
          <EditorSection title="Status of the work">
            <SelectField
              name="stage"
              label="Stage"
              options={projectStages.map((s) => ({ value: s, label: stageLabels[s] }))}
            />
            <div className="grid grid-cols-2 gap-3">
              <DateTimeField name="startDate" label="Start" dateOnly />
              <DateTimeField name="endDate" label="End" dateOnly />
            </div>
          </EditorSection>
          <EditorSection
            title="Fundraising goal"
            description="Shows a progress bar on the project page. Write whole amounts without cents, e.g. 15000 for €15,000. Leave the goal empty to hide the bar."
          >
            <SelectField
              name="currency"
              label="Currency"
              options={currencies.map((c) => ({ value: c, label: c }))}
            />
            <div className="grid grid-cols-2 gap-3">
              <TextField name="goalAmount" label="Amount needed" inputMode="decimal" placeholder="15000" />
              <TextField name="raisedAmount" label="Collected so far" inputMode="decimal" placeholder="0" />
            </div>
          </EditorSection>
          <EditorSection title="Where in Nepal">
            <TextField
              name="district"
              label="District or area"
              placeholder="e.g. Sindhupalchok"
              maxLength={100}
            />
          </EditorSection>
          <EditorSection title="Cover image">
            <MediaFormField name="coverImageId" label="Image" initialAsset={coverAsset} />
          </EditorSection>
          <EditorSection title="Publishing">
            <DateTimeField
              name="publishedAt"
              label="Publication date"
              description="Shown on the site. Pick a future date to publish it automatically later."
            />
            <SwitchField
              name="isFeatured"
              label="Show on the home page"
              description="Featured projects are highlighted on the home page."
            />
          </EditorSection>
          <EditorAdvanced description="Map position and list order. Most projects don't need these.">
            <div className="grid grid-cols-2 gap-3">
              <TextField
                name="latitude"
                label="Latitude"
                inputMode="decimal"
                description="From Google Maps, e.g. 27.95"
              />
              <TextField name="longitude" label="Longitude" inputMode="decimal" description="e.g. 85.68" />
            </div>
            <TextField
              name="position"
              label="Position in lists"
              type="number"
              description="1 is shown first. Leave as it is to keep the default order."
            />
          </EditorAdvanced>
          {id ? (
            <EditorSection title="Photo albums" description="Albums linked to this project (set in Gallery).">
              {albums.length ? (
                <ul className="grid gap-1.5 text-sm">
                  {albums.map((a) => (
                    <li key={a.id} className="flex items-center justify-between gap-2">
                      <Link
                        prefetch={false}
                        href={`/admin/gallery/${a.id}`}
                        className="truncate underline-offset-2 hover:underline"
                      >
                        {a.title}
                      </Link>
                      <StatusBadge status={a.status as ContentStatusValue} />
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-muted-foreground">No albums yet.</p>
              )}
              <Link
                prefetch={false}
                href="/admin/gallery/new"
                className="text-sm font-medium text-primary underline-offset-2 hover:underline"
              >
                Create an album
              </Link>
            </EditorSection>
          ) : null}
        </>
      }
    />
  );
}
