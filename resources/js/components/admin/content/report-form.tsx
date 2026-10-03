"use client";

import { useForm, useWatch } from "react-hook-form";
import { deleteReportAction, saveReportAction, setReportStatusAction } from "@/lib/actions/reports";
import type { ContentStatusValue } from "@/lib/content/common";
import type { MediaAssetDTO } from "@/lib/content/media";
import {
  reportFormSchema,
  reportTypeLabels,
  reportTypes,
  type ReportFormValues,
} from "@/lib/content/schemas";
import { ContentEditor, EditorSection, type EditorPerms } from "./content-editor";
import { DateTimeField, MediaFormField, SelectField, TextField } from "./fields";
import { resolverFor } from "./resolver";
import { TranslationTabs } from "./translation-tabs";

export function ReportForm({
  id,
  status,
  initialValues,
  assets,
  perms,
}: {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: ReportFormValues;
  assets: Record<string, MediaAssetDTO>;
  perms: EditorPerms;
}) {
  const form = useForm<ReportFormValues>({
    resolver: resolverFor(reportFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });
  const title = useWatch({ control: form.control, name: "translations.it.title" });
  const a = (id: string | null | undefined) => (id ? (assets[id] ?? null) : null);

  return (
    <ContentEditor
      form={form}
      id={id}
      kindLabel="report"
      title={id ? title || "Untitled report" : "New report"}
      basePath="/admin/reports"
      status={status}
      publicUrl="/it/transparency"
      perms={perms}
      onSave={(values, intent) => saveReportAction(id, values, intent)}
      onStatus={id ? (s) => setReportStatusAction(id, s) : undefined}
      onDelete={id ? () => deleteReportAction(id) : undefined}
      main={
        <>
          <EditorSection title="Document" description="The PDF visitors download from the Documents page.">
            <MediaFormField
              name="fileId"
              label="PDF (Italian / default)"
              accept="DOCUMENT"
              initialAsset={a(initialValues.fileId)}
              description="Required before publishing."
            />
          </EditorSection>
          <TranslationTabs>
            {(l) => (
              <EditorSection title={l === "it" ? "Italian text" : "English text"}>
                <TextField
                  name={`translations.${l}.title`}
                  label="Title"
                  maxLength={200}
                  required
                  placeholder={l === "it" ? "Bilancio sociale 2025" : "Annual report 2025"}
                />
                <TextField
                  name={`translations.${l}.description`}
                  label="Summary"
                  description="A few lines on what the document covers."
                  multiline
                  rows={4}
                  maxLength={1000}
                />
                {l === "en" ? (
                  <MediaFormField
                    name="translations.en.fileId"
                    label="English PDF (optional)"
                    accept="DOCUMENT"
                    initialAsset={a(initialValues.translations.en.fileId)}
                    description="If empty, English visitors get the default PDF."
                  />
                ) : null}
              </EditorSection>
            )}
          </TranslationTabs>
        </>
      }
      sidebar={
        <>
          <EditorSection title="Details">
            <SelectField
              name="type"
              label="Type"
              options={reportTypes.map((t) => ({ value: t, label: reportTypeLabels[t] }))}
            />
            <TextField name="year" label="Year" type="number" required />
            <TextField name="position" label="Order within the year" type="number" />
          </EditorSection>
          <EditorSection title="Cover image" description="Optional thumbnail of the document.">
            <MediaFormField name="coverImageId" label="Image" initialAsset={a(initialValues.coverImageId)} />
          </EditorSection>
          <EditorSection title="Publishing">
            <DateTimeField name="publishedAt" label="Publication date" />
          </EditorSection>
        </>
      }
    />
  );
}
