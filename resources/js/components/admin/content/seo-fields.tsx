"use client";

import { useFormContext, useWatch } from "react-hook-form";
import { EditorSection } from "./content-editor";
import { TextField } from "./fields";

const siteHost = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost").host;
  } catch {
    return "localhost";
  }
})();

/** Search engines cut longer text; the fields allow a little more but warn past these. */
export const SEO_TITLE_RECOMMENDED = 60;
export const SEO_DESCRIPTION_RECOMMENDED = 155;

function counter(value: string | undefined, recommended: number) {
  const n = (value ?? "").length;
  return n > recommended
    ? `${n}/${recommended} characters: Google may cut the end`
    : `${n}/${recommended} characters`;
}

/** SEO title/description for one locale, with a live search-result preview and counters. */
export function SeoFields({ locale }: { locale: "it" | "en" }) {
  const { control } = useFormContext();
  const [title, seoTitle, seoDescription, excerpt, slug] = useWatch({
    control,
    name: [
      `translations.${locale}.title`,
      `translations.${locale}.seoTitle`,
      `translations.${locale}.seoDescription`,
      `translations.${locale}.excerpt`,
      `translations.${locale}.slug`,
    ],
  }) as (string | undefined)[];
  const shownTitle = seoTitle || title || "Title";
  const shownDesc = seoDescription || excerpt || "";

  return (
    <EditorSection
      title="How it looks on Google"
      description="Optional. If left empty, Google is shown the title and the short summary."
    >
      <TextField
        name={`translations.${locale}.seoTitle`}
        label="Title on Google"
        maxLength={70}
        description={counter(seoTitle, SEO_TITLE_RECOMMENDED)}
      />
      <TextField
        name={`translations.${locale}.seoDescription`}
        label="Description on Google"
        multiline
        rows={2}
        maxLength={170}
        description={counter(seoDescription, SEO_DESCRIPTION_RECOMMENDED)}
      />
      <div className="rounded-lg border bg-background p-3" aria-label="Search result preview">
        <p className="truncate text-xs text-emerald-800">
          {siteHost} › {locale} › {slug || "…"}
        </p>
        <p className="truncate text-base text-blue-800">{shownTitle}</p>
        <p className="line-clamp-2 text-sm text-muted-foreground">{shownDesc || "No description yet."}</p>
      </div>
    </EditorSection>
  );
}
