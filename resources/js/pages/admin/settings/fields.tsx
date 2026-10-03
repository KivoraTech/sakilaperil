"use client";

import { useId } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { Controller, get, useFieldArray, useFormContext, useFormState } from "react-hook-form";
import { ImageChooser } from "@/components/admin/blocks/media-picker";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { isTodo } from "@/lib/settings/todo";

/** Small React Hook Form field kit for the Settings screen (builder-owned). */

function useError(name: string) {
  const { errors } = useFormState({ name });
  const e = get(errors, name) as { message?: string } | undefined;
  return e?.message;
}

function Shell({
  id,
  label,
  hint,
  error,
  todo,
  children,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  todo?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className="grid content-start gap-1.5">
      <Label htmlFor={id} className="flex flex-wrap items-center gap-2">
        {label}
        {todo ? (
          <Badge variant="outline" className="border-amber-400 text-amber-800">
            To confirm
          </Badge>
        ) : null}
      </Label>
      {children}
      {hint ? (
        <p id={`${id}-hint`} className="text-xs text-muted-foreground">
          {hint}
        </p>
      ) : null}
      {error ? (
        <p id={`${id}-error`} role="alert" className="text-xs font-medium text-destructive">
          {error}
        </p>
      ) : null}
    </div>
  );
}

const describedBy = (id: string, hint?: string, error?: string) =>
  [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;

export function TextField({
  name,
  label,
  hint,
  multiline,
  type = "text",
  placeholder,
  maxLength,
}: {
  name: string;
  label: string;
  hint?: string;
  multiline?: boolean;
  type?: "text" | "email" | "url" | "tel";
  placeholder?: string;
  maxLength?: number;
}) {
  const { control } = useFormContext();
  const id = useId();
  const error = useError(name);
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => {
        const value = (field.value as string | undefined) ?? "";
        const props = {
          id,
          value,
          onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
            field.onChange(e.target.value),
          onBlur: field.onBlur,
          placeholder,
          maxLength,
          "aria-invalid": error ? true : undefined,
          "aria-describedby": describedBy(id, hint, error),
        };
        return (
          <Shell id={id} label={label} hint={hint} error={error} todo={isTodo(value)}>
            {multiline ? <Textarea rows={3} {...props} /> : <Input type={type} {...props} />}
          </Shell>
        );
      }}
    />
  );
}

export function LocalizedField({
  name,
  label,
  hint,
  multiline,
  maxLength,
}: {
  name: string;
  label: string;
  hint?: string;
  multiline?: boolean;
  maxLength?: number;
}) {
  return (
    <fieldset className="grid min-w-0 gap-3 sm:grid-cols-2">
      <legend className="sr-only">{label}</legend>
      <TextField
        name={`${name}.it`}
        label={`${label} · Italiano`}
        hint={hint}
        multiline={multiline}
        maxLength={maxLength}
      />
      <TextField
        name={`${name}.en`}
        label={`${label} · English`}
        multiline={multiline}
        maxLength={maxLength}
        placeholder="Falls back to Italian"
      />
    </fieldset>
  );
}

export function NumberField({
  name,
  label,
  hint,
  min,
  max,
  step,
}: {
  name: string;
  label: string;
  hint?: string;
  min?: number;
  max?: number;
  step?: number | "any";
}) {
  const { control } = useFormContext();
  const id = useId();
  const error = useError(name);
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <Shell id={id} label={label} hint={hint} error={error}>
          <Input
            id={id}
            type="number"
            min={min}
            max={max}
            step={step ?? "any"}
            value={
              field.value === undefined || field.value === null || Number.isNaN(field.value)
                ? ""
                : String(field.value)
            }
            onChange={(e) => field.onChange(e.target.value === "" ? undefined : Number(e.target.value))}
            onBlur={field.onBlur}
            aria-invalid={error ? true : undefined}
            aria-describedby={describedBy(id, hint, error)}
            className="max-w-48"
          />
        </Shell>
      )}
    />
  );
}

export function SwitchField({ name, label, hint }: { name: string; label: string; hint?: string }) {
  const { control } = useFormContext();
  const id = useId();
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <div className="flex items-start justify-between gap-4 rounded-lg border p-3">
          <div className="grid min-w-0 gap-0.5">
            <Label htmlFor={id}>{label}</Label>
            {hint ? (
              <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                {hint}
              </p>
            ) : null}
          </div>
          <Switch
            id={id}
            checked={!!field.value}
            onCheckedChange={field.onChange}
            aria-describedby={hint ? `${id}-hint` : undefined}
          />
        </div>
      )}
    />
  );
}

export function SelectField({
  name,
  label,
  hint,
  options,
}: {
  name: string;
  label: string;
  hint?: string;
  options: { value: string; label: string; swatch?: string }[];
}) {
  const { control } = useFormContext();
  const id = useId();
  const error = useError(name);
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <Shell id={id} label={label} hint={hint} error={error}>
          <Select value={(field.value as string | undefined) ?? ""} onValueChange={field.onChange}>
            <SelectTrigger id={id} className="w-full sm:w-72" aria-describedby={describedBy(id, hint, error)}>
              <SelectValue placeholder="Choose…" />
            </SelectTrigger>
            <SelectContent>
              {options.map((o) => (
                <SelectItem key={o.value} value={o.value}>
                  {o.swatch ? (
                    <span
                      aria-hidden
                      className="size-3 rounded-full border"
                      style={{ background: o.swatch }}
                    />
                  ) : null}
                  {o.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </Shell>
      )}
    />
  );
}

export function ImageField({ name, label, hint }: { name: string; label: string; hint?: string }) {
  const { control } = useFormContext();
  const id = useId();
  const error = useError(name);
  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <div className="grid content-start gap-1.5">
          <span className="text-sm font-medium" id={`${id}-label`}>
            {label}
          </span>
          <ImageChooser
            id={id}
            label={label}
            assetId={(field.value as string | undefined) || undefined}
            optional
            invalid={!!error}
            describedBy={describedBy(id, hint, error)}
            onChange={(v) => field.onChange(v ?? "")}
          />
          {hint ? (
            <p id={`${id}-hint`} className="text-xs text-muted-foreground">
              {hint}
            </p>
          ) : null}
          {error ? (
            <p id={`${id}-error`} role="alert" className="text-xs font-medium text-destructive">
              {error}
            </p>
          ) : null}
        </div>
      )}
    />
  );
}

/** Repeatable list of object rows (social links, impact numbers, notes). */
export function ListField({
  name,
  label,
  hint,
  itemLabel,
  max,
  newItem,
  children,
}: {
  name: string;
  label: string;
  hint?: string;
  itemLabel: string;
  max: number;
  newItem: () => Record<string, unknown>;
  children: (itemName: string, index: number) => React.ReactNode;
}) {
  const { control } = useFormContext();
  const { fields, append, remove, move } = useFieldArray({ control, name });
  const error = useError(name);
  const id = useId();
  return (
    <section aria-labelledby={`${id}-title`} className="grid gap-3">
      <div>
        <h3 id={`${id}-title`} className="text-sm font-semibold">
          {label}{" "}
          <span className="font-normal text-muted-foreground">
            ({fields.length}/{max})
          </span>
        </h3>
        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        {error ? (
          <p role="alert" className="text-xs font-medium text-destructive">
            {error}
          </p>
        ) : null}
      </div>
      {fields.length === 0 ? <p className="text-sm text-muted-foreground">None yet.</p> : null}
      <ol className="grid gap-3">
        {fields.map((f, i) => (
          <li key={f.id} className="rounded-lg border bg-muted/30 p-3">
            <div className="mb-3 flex items-center justify-between">
              <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {itemLabel} {i + 1}
              </span>
              <div className="flex gap-1">
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  disabled={i === 0}
                  onClick={() => move(i, i - 1)}
                  aria-label={`Move ${itemLabel.toLowerCase()} ${i + 1} up`}
                >
                  <ArrowUp aria-hidden />
                </Button>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  disabled={i === fields.length - 1}
                  onClick={() => move(i, i + 1)}
                  aria-label={`Move ${itemLabel.toLowerCase()} ${i + 1} down`}
                >
                  <ArrowDown aria-hidden />
                </Button>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  className="text-destructive"
                  onClick={() => remove(i)}
                  aria-label={`Remove ${itemLabel.toLowerCase()} ${i + 1}`}
                >
                  <Trash2 aria-hidden />
                </Button>
              </div>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">{children(`${name}.${i}`, i)}</div>
          </li>
        ))}
      </ol>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="justify-self-start"
        disabled={fields.length >= max}
        onClick={() => append(newItem())}
      >
        <Plus aria-hidden /> Add {itemLabel.toLowerCase()}
      </Button>
    </section>
  );
}
