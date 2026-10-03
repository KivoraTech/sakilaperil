"use client";

import { useEffect, useId, useState } from "react";
import { useFormContext, useWatch } from "react-hook-form";
import { Wand2, X } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { RichTextEditor } from "@/components/admin/editors/RichTextEditor";
import { MediaField } from "@/components/admin/media/MediaField";
import type { MediaAssetDTO } from "@/lib/content/media";
import { slugify } from "@/lib/i18n/routes";
import type { RichTextDoc } from "@/lib/richtext/schema";

/**
 * React Hook Form field wrappers for the content editors. Each renders an accessible
 * label, description and error message (FormItem/FormLabel/FormControl/FormMessage).
 * `name` is a dotted RHF path (e.g. "translations.it.title").
 */
type Base = { name: string; label: string; description?: React.ReactNode; className?: string };

export function TextField({
  name,
  label,
  description,
  className,
  placeholder,
  maxLength,
  type = "text",
  multiline,
  rows = 3,
  required,
  inputMode,
}: Base & {
  placeholder?: string;
  maxLength?: number;
  type?: "text" | "url" | "number" | "email";
  multiline?: boolean;
  rows?: number;
  required?: boolean;
  inputMode?: React.HTMLAttributes<HTMLInputElement>["inputMode"];
}) {
  const { control } = useFormContext();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>
            {label}
            {required ? <span aria-hidden> *</span> : null}
          </FormLabel>
          <FormControl>
            {multiline ? (
              <Textarea
                {...field}
                value={field.value ?? ""}
                rows={rows}
                maxLength={maxLength}
                placeholder={placeholder}
              />
            ) : (
              <Input
                {...field}
                value={field.value ?? ""}
                type={type}
                maxLength={maxLength}
                placeholder={placeholder}
                inputMode={inputMode}
                aria-required={required || undefined}
              />
            )}
          </FormControl>
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function SwitchField({ name, label, description, className }: Base) {
  const { control } = useFormContext();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <div className="flex items-center gap-3">
            <FormControl>
              <Switch checked={!!field.value} onCheckedChange={field.onChange} />
            </FormControl>
            <FormLabel className="font-normal">{label}</FormLabel>
          </div>
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

/** Native select (fully accessible, small). Empty option maps to `null` for id fields. */
export function SelectField({
  name,
  label,
  description,
  className,
  options,
  emptyLabel,
  nullable,
}: Base & {
  options: { value: string; label: string }[];
  emptyLabel?: string;
  /** When true, "" is stored as null. */
  nullable?: boolean;
}) {
  const { control } = useFormContext();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <select
              name={field.name}
              ref={field.ref}
              onBlur={field.onBlur}
              value={field.value ?? ""}
              onChange={(e) => field.onChange(nullable && e.target.value === "" ? null : e.target.value)}
              className="h-8 w-full rounded-lg border border-input bg-background px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive"
            >
              {emptyLabel !== undefined ? <option value="">{emptyLabel}</option> : null}
              {options.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </FormControl>
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

const pad = (n: number) => String(n).padStart(2, "0");
export function isoToLocalInput(iso: string | null | undefined, dateOnly = false): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  return dateOnly ? date : `${date}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
export function localInputToIso(v: string): string {
  if (!v) return "";
  const d = new Date(v.length === 10 ? `${v}T00:00` : v);
  return Number.isNaN(d.getTime()) ? "" : d.toISOString();
}

/** Stores an ISO string ("" when empty); shows a local date(-time) input. */
export function DateTimeField({
  name,
  label,
  description,
  className,
  dateOnly,
  required,
}: Base & { dateOnly?: boolean; required?: boolean }) {
  const { control } = useFormContext();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>
            {label}
            {required ? <span aria-hidden> *</span> : null}
          </FormLabel>
          <FormControl>
            <Input
              type={dateOnly ? "date" : "datetime-local"}
              name={field.name}
              ref={field.ref}
              onBlur={field.onBlur}
              value={isoToLocalInput(field.value, dateOnly)}
              onChange={(e) => field.onChange(localInputToIso(e.target.value))}
              aria-required={required || undefined}
            />
          </FormControl>
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

/**
 * Slug input that follows the title until the editor changes it by hand (new items only).
 * `prefix` shows the public URL shape, e.g. "/it/news/".
 */
export function SlugField({
  name,
  sourceName,
  label = "Slug (URL)",
  prefix,
  autoFollow,
  className,
}: {
  name: string;
  sourceName: string;
  label?: string;
  prefix?: string;
  autoFollow: boolean;
  className?: string;
}) {
  const { control, setValue, getFieldState } = useFormContext();
  const source = useWatch({ control, name: sourceName }) as string | undefined;
  const [follow, setFollow] = useState(autoFollow);

  useEffect(() => {
    if (follow && source !== undefined) {
      setValue(name, slugify(source), { shouldDirty: true, shouldValidate: getFieldState(name).invalid });
    }
  }, [follow, source, name, setValue, getFieldState]);

  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>{label}</FormLabel>
          <div className="flex items-center gap-2">
            <div className="flex min-w-0 flex-1 items-center rounded-lg border border-input bg-background focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50">
              {prefix ? (
                <span className="shrink-0 pl-2.5 font-mono text-xs text-muted-foreground" aria-hidden>
                  {prefix}
                </span>
              ) : null}
              <FormControl>
                <input
                  {...field}
                  value={field.value ?? ""}
                  onChange={(e) => {
                    setFollow(false);
                    field.onChange(e.target.value.toLowerCase().replace(/\s+/g, "-"));
                  }}
                  maxLength={80}
                  spellCheck={false}
                  className="h-8 min-w-0 flex-1 bg-transparent px-1 font-mono text-sm outline-none"
                />
              </FormControl>
            </div>
            <Button
              type="button"
              variant="outline"
              size="icon"
              title="Generate from title"
              aria-label="Generate slug from title"
              onClick={() =>
                setValue(name, slugify(source ?? ""), { shouldDirty: true, shouldValidate: true })
              }
            >
              <Wand2 />
            </Button>
          </div>
          <FormDescription>
            Lowercase letters, numbers and dashes. Changing it changes the public link.
          </FormDescription>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function MediaFormField({
  name,
  label,
  description,
  className,
  accept = "IMAGE",
  initialAsset,
}: Base & { accept?: "IMAGE" | "DOCUMENT"; initialAsset?: MediaAssetDTO | null }) {
  const { control } = useFormContext();
  const id = useId();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <FormItem className={className} role="group" aria-labelledby={id}>
          <span id={id} className="text-sm font-medium">
            {label}
          </span>
          <MediaField
            value={field.value}
            onChange={(v) => field.onChange(v)}
            accept={accept}
            initialAsset={initialAsset}
            invalid={!!fieldState.error}
          />
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function RichTextField({ name, label, description, className }: Base) {
  const { control } = useFormContext();
  const labelId = useId();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <FormItem className={className}>
          <span id={labelId} className="text-sm font-medium">
            {label}
          </span>
          <RichTextEditor
            value={field.value as RichTextDoc}
            onChange={field.onChange}
            labelledBy={labelId}
            invalid={!!fieldState.error}
          />
          {description ? <FormDescription>{description}</FormDescription> : null}
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

/** Free-text tags (Enter or comma to add). */
export function TagsField({ name, label, description, className, max = 10 }: Base & { max?: number }) {
  const { control } = useFormContext();
  const [draft, setDraft] = useState("");
  const inputId = useId();
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => {
        const tags = (field.value as string[]) ?? [];
        const add = () => {
          const t = draft.trim().replace(/,$/, "").slice(0, 40);
          if (t && !tags.some((x) => x.toLowerCase() === t.toLowerCase()) && tags.length < max)
            field.onChange([...tags, t]);
          setDraft("");
        };
        return (
          <FormItem className={className}>
            <FormLabel htmlFor={inputId}>{label}</FormLabel>
            <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-input bg-background p-1.5">
              {tags.map((t) => (
                <Badge key={t} variant="secondary" className="gap-1 pr-0.5">
                  {t}
                  <button
                    type="button"
                    aria-label={`Remove tag ${t}`}
                    className="rounded-full p-0.5 hover:bg-background"
                    onClick={() => field.onChange(tags.filter((x) => x !== t))}
                  >
                    <X className="size-3" />
                  </button>
                </Badge>
              ))}
              <input
                id={inputId}
                value={draft}
                onChange={(e) => setDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter" || e.key === ",") {
                    e.preventDefault();
                    add();
                  } else if (e.key === "Backspace" && !draft && tags.length) {
                    field.onChange(tags.slice(0, -1));
                  }
                }}
                onBlur={add}
                placeholder={tags.length >= max ? "" : "Add a tag…"}
                disabled={tags.length >= max}
                className="h-6 min-w-24 flex-1 bg-transparent px-1 text-sm outline-none"
              />
            </div>
            {description ? <FormDescription>{description}</FormDescription> : null}
            <FormMessage />
          </FormItem>
        );
      }}
    />
  );
}
