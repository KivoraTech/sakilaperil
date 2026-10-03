"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { createPageTranslationAction } from "@/lib/actions/pages";
import { localeLabels, type Locale } from "@/lib/i18n/config";
import { slugify } from "@/lib/i18n/routes";

export function CreateTranslationForm({
  pageId,
  locale,
  isHome,
  copyFrom,
}: {
  pageId: string;
  locale: Locale;
  isHome: boolean;
  copyFrom: Locale | null;
}) {
  const router = useRouter();
  const [title, setTitle] = useState("");
  const [slug, setSlug] = useState(isHome ? "home" : "");
  const [slugTouched, setSlugTouched] = useState(false);
  const [copy, setCopy] = useState(!!copyFrom);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [pending, start] = useTransition();

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    start(async () => {
      const r = await createPageTranslationAction({
        pageId,
        locale,
        title,
        slug: isHome ? "home-page" : slug, // server forces "home" for the home page
        copyFrom: copy && copyFrom ? copyFrom : undefined,
      });
      if (!r.ok) {
        setErrors(r.fieldErrors ?? {});
        toast.error(r.message);
        return;
      }
      toast.success(`${localeLabels[locale]} version created`);
      router.refresh();
    });
  };

  return (
    <Card className="max-w-xl">
      <CardHeader>
        <CardTitle>Create the {localeLabels[locale]} version</CardTitle>
        <CardDescription>
          Until it exists and is published, visitors on /{locale} see the Italian page (marked as
          untranslated).
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="grid gap-4" noValidate>
          <div className="grid gap-1.5">
            <Label htmlFor="tr-title">Title ({localeLabels[locale]})</Label>
            <Input
              id="tr-title"
              value={title}
              required
              maxLength={160}
              onChange={(e) => {
                setTitle(e.target.value);
                if (!slugTouched && !isHome) setSlug(slugify(e.target.value));
              }}
              aria-invalid={!!errors.title || undefined}
            />
            {errors.title ? <p className="text-xs text-destructive">{errors.title[0]}</p> : null}
          </div>
          {!isHome ? (
            <div className="grid gap-1.5">
              <Label htmlFor="tr-slug">Address</Label>
              <Input
                id="tr-slug"
                value={slug}
                maxLength={80}
                onChange={(e) => {
                  setSlugTouched(true);
                  setSlug(e.target.value.toLowerCase());
                }}
                aria-invalid={!!errors.slug || undefined}
              />
              <p className="text-xs text-muted-foreground">
                /{locale}/{slug || "…"}
              </p>
              {errors.slug ? <p className="text-xs text-destructive">{errors.slug[0]}</p> : null}
            </div>
          ) : null}
          {copyFrom ? (
            <label className="flex items-center gap-2 text-sm">
              <Checkbox checked={copy} onCheckedChange={(c) => setCopy(!!c)} />
              Start from a copy of the {localeLabels[copyFrom]} sections (then translate the texts)
            </label>
          ) : null}
          <Button type="submit" disabled={pending || !title.trim()} className="justify-self-start">
            {pending ? "Creating…" : `Create ${localeLabels[locale]} version`}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
