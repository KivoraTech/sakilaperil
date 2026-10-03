"use client";

import { useState, useTransition } from "react";
import { useForm, useWatch } from "react-hook-form";
import { Plus } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  createNewsCategoryAction,
  deleteNewsAction,
  saveNewsAction,
  setNewsStatusAction,
} from "@/lib/actions/news";
import type { ContentStatusValue } from "@/lib/content/common";
import type { MediaAssetDTO } from "@/lib/content/media";
import { newsFormSchema, type NewsFormValues } from "@/lib/content/schemas";
import { ContentEditor, EditorSection, type EditorPerms } from "./content-editor";
import {
  DateTimeField,
  MediaFormField,
  RichTextField,
  SelectField,
  SlugField,
  SwitchField,
  TagsField,
  TextField,
} from "./fields";
import { resolverFor } from "./resolver";
import { SeoFields } from "./seo-fields";
import { TranslationTabs } from "./translation-tabs";

type Option = { value: string; label: string };

export function NewsForm({
  id,
  status,
  initialValues,
  coverAsset,
  options,
  perms,
  canManageCategories,
  publicSlug,
}: {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: NewsFormValues;
  coverAsset: MediaAssetDTO | null;
  options: { categories: Option[]; projects: Option[]; authors: Option[] };
  perms: EditorPerms;
  canManageCategories: boolean;
  publicSlug: string | null;
}) {
  const form = useForm<NewsFormValues>({
    resolver: resolverFor(newsFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });
  const [categories, setCategories] = useState(options.categories);
  const title = useWatch({ control: form.control, name: "translations.it.title" });

  return (
    <ContentEditor
      form={form}
      id={id}
      kindLabel="post"
      title={id ? title || "Untitled post" : "New post"}
      basePath="/admin/news"
      status={status}
      publicUrl={publicSlug ? `/it/news/${publicSlug}` : null}
      previewPath={id ? `/preview/it/news/${id}` : null}
      perms={perms}
      readOnlyReason="Authors can edit their own posts while they are drafts. Ask an editor to change this post."
      onSave={(values, intent) => saveNewsAction(id, values, intent)}
      onStatus={id ? (s) => setNewsStatusAction(id, s) : undefined}
      onDelete={id ? () => deleteNewsAction(id) : undefined}
      main={
        <TranslationTabs>
          {(l) => (
            <>
              <EditorSection title="Content">
                <TextField name={`translations.${l}.title`} label="Title" maxLength={200} required />
                <SlugField
                  name={`translations.${l}.slug`}
                  sourceName={`translations.${l}.title`}
                  prefix={`/${l}/news/`}
                  autoFollow={!initialValues.translations[l].slug}
                />
                <TextField
                  name={`translations.${l}.excerpt`}
                  label="Excerpt"
                  description="One or two sentences shown in lists and link previews."
                  multiline
                  rows={2}
                  maxLength={300}
                />
                <RichTextField name={`translations.${l}.body`} label="Body" />
              </EditorSection>
              <SeoFields locale={l} />
            </>
          )}
        </TranslationTabs>
      }
      sidebar={
        <>
          <EditorSection title="Publishing">
            <DateTimeField
              name="publishedAt"
              label="Publication date"
              description="Shown on the post. A future date schedules it."
            />
            {options.authors.length ? (
              <SelectField name="authorId" label="Author" options={options.authors} emptyLabel="—" nullable />
            ) : null}
            <SwitchField name="isFeatured" label="Featured" description="Highlight on the home page." />
          </EditorSection>
          <EditorSection title="Cover image">
            <MediaFormField name="coverImageId" label="Image" initialAsset={coverAsset} />
          </EditorSection>
          <EditorSection title="Organise">
            <div className="grid gap-2">
              <SelectField
                name="categoryId"
                label="Category"
                options={categories}
                emptyLabel="No category"
                nullable
              />
              {canManageCategories ? (
                <NewCategoryButton
                  onCreated={(c) => {
                    setCategories((prev) => [...prev, c]);
                    form.setValue("categoryId", c.value, { shouldDirty: true });
                  }}
                />
              ) : null}
            </div>
            <TagsField name="tags" label="Tags" description="Press Enter after each tag." />
            <SelectField
              name="projectId"
              label="Related project"
              options={options.projects}
              emptyLabel="None"
              nullable
            />
          </EditorSection>
        </>
      }
    />
  );
}

function NewCategoryButton({ onCreated }: { onCreated: (c: Option) => void }) {
  const [open, setOpen] = useState(false);
  const [nameIt, setNameIt] = useState("");
  const [nameEn, setNameEn] = useState("");
  const [pending, start] = useTransition();
  const create = () =>
    start(async () => {
      const res = await createNewsCategoryAction({ nameIt, nameEn });
      if (!res.ok) return void toast.error(res.message);
      toast.success(res.message ?? "Category created");
      onCreated({ value: res.data.id, label: res.data.name });
      setOpen(false);
      setNameIt("");
      setNameEn("");
    });
  return (
    <>
      <Button
        type="button"
        variant="link"
        size="sm"
        className="h-auto justify-self-start p-0"
        onClick={() => setOpen(true)}
      >
        <Plus aria-hidden /> New category
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>New category</DialogTitle>
            <DialogDescription>For example “Dal campo” / “From the field”.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div className="grid gap-1">
              <Label htmlFor="cat-it">Name (Italian) *</Label>
              <Input id="cat-it" value={nameIt} onChange={(e) => setNameIt(e.target.value)} maxLength={60} />
            </div>
            <div className="grid gap-1">
              <Label htmlFor="cat-en">Name (English)</Label>
              <Input id="cat-en" value={nameEn} onChange={(e) => setNameEn(e.target.value)} maxLength={60} />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="button" disabled={!nameIt.trim() || pending} onClick={create}>
              Create
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
