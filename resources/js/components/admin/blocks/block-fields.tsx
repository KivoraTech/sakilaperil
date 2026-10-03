"use client";

import { useId } from "react";
import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { Controller, get, useFormContext, useFormState, useWatch } from "react-hook-form";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import type { RichTextDoc } from "@/lib/richtext/schema";
import { emptyValue, type FieldSpec } from "./introspect";
import { fieldHint, fieldLabel, optionLabel } from "./labels";
import { ImageChooser } from "./media-picker";
import { useReferences } from "./references-context";
import { RichTextField } from "./rich-text-field";

const NONE = "__none__";

function useFieldError(name: string): string | undefined {
  const { errors } = useFormState({ name });
  const e = get(errors, name) as { message?: string; root?: { message?: string } } | undefined;
  return e?.message ?? e?.root?.message;
}

function FieldShell({
  id,
  label,
  hint,
  error,
  optional,
  children,
  labelId,
  asGroup,
  className,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  optional?: boolean;
  children: React.ReactNode;
  labelId?: string;
  asGroup?: boolean;
  className?: string;
}) {
  const labelNode = (
    <span className="flex items-baseline gap-1.5">
      {label}
      {optional ? <span className="text-xs font-normal text-muted-foreground">(optional)</span> : null}
    </span>
  );
  return (
    <div className={cn("grid gap-1.5", className)}>
      {asGroup ? (
        <span id={labelId} className="text-sm font-medium">
          {labelNode}
        </span>
      ) : (
        <Label htmlFor={id} id={labelId}>
          {labelNode}
        </Label>
      )}
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

function describedBy(id: string, hint?: string, error?: string) {
  return [hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean).join(" ") || undefined;
}

/** Render one generated field (recursive). `name` is the RHF path, e.g. "content.items.0.name". */
export function BlockField({ name, fieldKey, spec }: { name: string; fieldKey: string; spec: FieldSpec }) {
  switch (spec.kind) {
    case "object":
      return <ObjectField name={name} fieldKey={fieldKey} spec={spec} />;
    case "array":
      return <ArrayField name={name} fieldKey={fieldKey} spec={spec} />;
    case "link":
      return <LinkField name={name} fieldKey={fieldKey} spec={spec} />;
    case "image":
      return <ImageField name={name} fieldKey={fieldKey} spec={spec} />;
    case "unknown":
      return null;
    default:
      return <LeafField name={name} fieldKey={fieldKey} spec={spec} />;
  }
}

function LeafField({ name, fieldKey, spec }: { name: string; fieldKey: string; spec: FieldSpec }) {
  const { control } = useFormContext();
  const refs = useReferences();
  const id = useId();
  const labelId = `${id}-label`;
  const error = useFieldError(name);
  const label = fieldLabel(fieldKey);
  const hint = fieldHint(fieldKey);
  const aria = describedBy(id, hint, error);

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => {
        switch (spec.kind) {
          case "text": {
            const v = (field.value as string | undefined) ?? "";
            const counter = spec.max ? `${v.length}/${spec.max}` : null;
            const props = {
              id,
              name: field.name,
              value: v,
              onChange: (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
                field.onChange(e.target.value),
              onBlur: field.onBlur,
              ref: field.ref,
              "aria-invalid": error ? true : undefined,
              "aria-describedby": aria,
              maxLength: spec.max,
            };
            return (
              <FieldShell id={id} label={label} hint={hint} error={error} optional={spec.optional}>
                {spec.multiline ? <Textarea rows={4} {...props} /> : <Input {...props} />}
                {counter && v.length > (spec.max ?? 0) * 0.8 ? (
                  <span className="justify-self-end text-xs text-muted-foreground tabular-nums">
                    {counter}
                  </span>
                ) : null}
              </FieldShell>
            );
          }
          case "number":
            return (
              <FieldShell id={id} label={label} hint={hint} error={error} optional={spec.optional}>
                <Input
                  id={id}
                  type="number"
                  inputMode={spec.int ? "numeric" : "decimal"}
                  min={spec.min}
                  max={spec.max}
                  step={spec.step ?? "any"}
                  value={field.value === undefined || field.value === null ? "" : String(field.value)}
                  onChange={(e) => field.onChange(e.target.value === "" ? undefined : Number(e.target.value))}
                  onBlur={field.onBlur}
                  ref={field.ref}
                  aria-invalid={error ? true : undefined}
                  aria-describedby={aria}
                  className="max-w-40"
                />
              </FieldShell>
            );
          case "boolean":
            return (
              <div className="flex items-start justify-between gap-4 rounded-lg border p-3">
                <div className="grid gap-1">
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
                  onCheckedChange={(c) => field.onChange(c)}
                  aria-describedby={aria}
                />
              </div>
            );
          case "enum":
            return (
              <FieldShell id={id} label={label} hint={hint} error={error} optional={spec.optional}>
                <Select
                  value={(field.value as string | undefined) ?? (spec.optional ? NONE : undefined)}
                  onValueChange={(v) => field.onChange(v === NONE ? undefined : v)}
                >
                  <SelectTrigger
                    id={id}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={aria}
                    className="w-full sm:w-72"
                  >
                    <SelectValue placeholder="Choose…" />
                  </SelectTrigger>
                  <SelectContent>
                    {spec.optional ? <SelectItem value={NONE}>Default</SelectItem> : null}
                    {spec.options.map((o) => (
                      <SelectItem key={o} value={o}>
                        {optionLabel(o)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </FieldShell>
            );
          case "enumArray": {
            const selected = new Set((field.value as string[] | undefined) ?? []);
            return (
              <FieldShell id={id} labelId={labelId} asGroup label={label} hint={hint} error={error}>
                <div
                  role="group"
                  aria-labelledby={labelId}
                  aria-describedby={aria}
                  className="grid gap-2 sm:grid-cols-2"
                >
                  {spec.options.map((o) => (
                    <label key={o} className="flex items-center gap-2 text-sm">
                      <Checkbox
                        checked={selected.has(o)}
                        onCheckedChange={(c) => {
                          const next = new Set(selected);
                          if (c) next.add(o);
                          else next.delete(o);
                          field.onChange(spec.options.filter((x) => next.has(x)));
                        }}
                      />
                      {optionLabel(o)}
                    </label>
                  ))}
                </div>
              </FieldShell>
            );
          }
          case "reference": {
            const options =
              spec.ref === "project"
                ? refs.projects
                : spec.ref === "album"
                  ? refs.albums
                  : refs.newsCategories;
            if (spec.multiple) {
              const selected = new Set((field.value as string[] | undefined) ?? []);
              return (
                <FieldShell id={id} labelId={labelId} asGroup label={label} hint={hint} error={error}>
                  {options.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Nothing to choose from yet.</p>
                  ) : (
                    <div
                      role="group"
                      aria-labelledby={labelId}
                      className="grid max-h-56 gap-2 overflow-y-auto rounded-lg border p-3"
                    >
                      {options.map((o) => (
                        <label key={o.id} className="flex items-center gap-2 text-sm">
                          <Checkbox
                            checked={selected.has(o.id)}
                            disabled={!selected.has(o.id) && !!spec.max && selected.size >= spec.max}
                            onCheckedChange={(c) => {
                              const next = [...selected].filter((x) => x !== o.id);
                              if (c) next.push(o.id);
                              field.onChange(next);
                            }}
                          />
                          <span>{o.label}</span>
                          {o.status && o.status !== "PUBLISHED" ? (
                            <span className="text-xs text-muted-foreground">({o.status.toLowerCase()})</span>
                          ) : null}
                        </label>
                      ))}
                    </div>
                  )}
                </FieldShell>
              );
            }
            return (
              <FieldShell id={id} label={label} hint={hint} error={error} optional={spec.optional}>
                <Select
                  value={(field.value as string | undefined) || NONE}
                  onValueChange={(v) => field.onChange(v === NONE ? undefined : v)}
                >
                  <SelectTrigger
                    id={id}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={aria}
                    className="w-full sm:w-80"
                  >
                    <SelectValue placeholder="Choose…" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={NONE}>None</SelectItem>
                    {options.map((o) => (
                      <SelectItem key={o.id} value={o.id}>
                        {o.label}
                        {o.status && o.status !== "PUBLISHED" ? ` (${o.status.toLowerCase()})` : ""}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </FieldShell>
            );
          }
          case "richText":
            return (
              <FieldShell
                id={id}
                labelId={labelId}
                asGroup
                label={label}
                hint={hint}
                error={error}
                optional={spec.optional}
              >
                <RichTextField
                  value={field.value as RichTextDoc | undefined}
                  onChange={field.onChange}
                  labelledBy={labelId}
                  invalid={!!error}
                />
              </FieldShell>
            );
          default:
            return <></>;
        }
      }}
    />
  );
}

function ImageField({ name, fieldKey, spec }: { name: string; fieldKey: string; spec: FieldSpec }) {
  const { control, setValue } = useFormContext();
  const id = useId();
  const value = useWatch({ control, name }) as { assetId?: string; alt?: string } | undefined;
  const assetError = useFieldError(`${name}.assetId`);
  const objectError = useFieldError(name);
  const error = assetError ?? objectError;
  const label = fieldLabel(fieldKey);
  const hint = fieldHint(fieldKey);
  return (
    <FieldShell id={id} asGroup label={label} hint={hint} error={error} optional={spec.optional}>
      <ImageChooser
        id={id}
        label={label}
        assetId={value?.assetId || undefined}
        optional={spec.optional}
        invalid={!!error}
        describedBy={describedBy(id, hint, error)}
        onChange={(assetId) =>
          setValue(
            name,
            assetId ? { ...(value ?? {}), assetId } : spec.optional ? undefined : { assetId: "" },
            {
              shouldDirty: true,
              shouldValidate: false,
            },
          )
        }
      />
      {value?.assetId ? (
        <Controller
          control={control}
          name={`${name}.alt`}
          render={({ field }) => (
            <div className="grid gap-1">
              <Label htmlFor={`${id}-alt`} className="text-xs text-muted-foreground">
                Alt text for this placement (optional — defaults to the image&apos;s own alt text)
              </Label>
              <Input
                id={`${id}-alt`}
                value={(field.value as string | undefined) ?? ""}
                onChange={(e) => field.onChange(e.target.value)}
                maxLength={300}
              />
            </div>
          )}
        />
      ) : null}
    </FieldShell>
  );
}

function LinkField({ name, fieldKey, spec }: { name: string; fieldKey: string; spec: FieldSpec }) {
  const { control, setValue } = useFormContext();
  const refs = useReferences();
  const id = useId();
  const value = useWatch({ control, name }) as { label?: string; href?: string } | undefined;
  const labelError = useFieldError(`${name}.label`);
  const hrefError = useFieldError(`${name}.href`);
  const title = fieldLabel(fieldKey);
  const listId = `${id}-links`;

  if (spec.optional && value === undefined) {
    return (
      <div className="grid gap-1.5">
        <span className="text-sm font-medium">{title}</span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="justify-self-start"
          onClick={() => setValue(name, { label: "", href: "" }, { shouldDirty: true })}
        >
          <Plus aria-hidden /> Add {title.toLowerCase()}
        </Button>
      </div>
    );
  }

  return (
    <fieldset className="grid min-w-0 gap-3 rounded-lg border p-3">
      <legend className="px-1 text-sm font-medium">
        {title}{" "}
        {spec.optional ? <span className="text-xs font-normal text-muted-foreground">(optional)</span> : null}
      </legend>
      <div className="grid gap-3 sm:grid-cols-2">
        <Controller
          control={control}
          name={`${name}.label`}
          render={({ field }) => (
            <FieldShell id={`${id}-label`} label="Button text" error={labelError}>
              <Input
                id={`${id}-label`}
                value={(field.value as string | undefined) ?? ""}
                onChange={(e) => field.onChange(e.target.value)}
                maxLength={80}
                aria-invalid={labelError ? true : undefined}
                aria-describedby={labelError ? `${id}-label-error` : undefined}
              />
            </FieldShell>
          )}
        />
        <Controller
          control={control}
          name={`${name}.href`}
          render={({ field }) => (
            <FieldShell
              id={`${id}-href`}
              label="Link"
              error={hrefError}
              hint="/page, #anchor, https://…, mailto: or tel:"
            >
              <Input
                id={`${id}-href`}
                list={listId}
                value={(field.value as string | undefined) ?? ""}
                onChange={(e) => field.onChange(e.target.value)}
                maxLength={500}
                inputMode="url"
                aria-invalid={hrefError ? true : undefined}
                aria-describedby={describedBy(`${id}-href`, "hint", hrefError)}
              />
            </FieldShell>
          )}
        />
        <datalist id={listId}>
          {refs.linkSuggestions.map((s) => (
            <option key={s.href} value={s.href}>
              {s.label}
            </option>
          ))}
        </datalist>
      </div>
      {spec.optional ? (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="justify-self-start text-destructive"
          onClick={() => setValue(name, undefined, { shouldDirty: true })}
        >
          <Trash2 aria-hidden /> Remove {title.toLowerCase()}
        </Button>
      ) : null}
    </fieldset>
  );
}

function ObjectField({
  name,
  fieldKey,
  spec,
}: {
  name: string;
  fieldKey: string;
  spec: Extract<FieldSpec, { kind: "object" }>;
}) {
  const { control, setValue } = useFormContext();
  const value = useWatch({ control, name });
  const title = fieldLabel(fieldKey);
  const hint = fieldHint(fieldKey);
  if (spec.optional && value === undefined) {
    return (
      <div className="grid gap-1.5">
        <span className="text-sm font-medium">{title}</span>
        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="justify-self-start"
          onClick={() => setValue(name, emptyValue({ ...spec, optional: false }), { shouldDirty: true })}
        >
          <Plus aria-hidden /> Add {title.toLowerCase()}
        </Button>
      </div>
    );
  }
  return (
    <fieldset className="grid min-w-0 gap-4 rounded-lg border p-3">
      <legend className="px-1 text-sm font-medium">{title}</legend>
      {hint ? <p className="-mt-2 text-xs text-muted-foreground">{hint}</p> : null}
      {Object.entries(spec.fields).map(([k, f]) => (
        <BlockField key={k} name={`${name}.${k}`} fieldKey={k} spec={f} />
      ))}
      {spec.optional ? (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="justify-self-start text-destructive"
          onClick={() => setValue(name, undefined, { shouldDirty: true })}
        >
          <Trash2 aria-hidden /> Remove {title.toLowerCase()}
        </Button>
      ) : null}
    </fieldset>
  );
}

function ArrayField({
  name,
  fieldKey,
  spec,
}: {
  name: string;
  fieldKey: string;
  spec: Extract<FieldSpec, { kind: "array" }>;
}) {
  const { control, setValue } = useFormContext();
  const items = ((useWatch({ control, name }) as unknown[] | undefined) ?? []) as unknown[];
  const error = useFieldError(name);
  const id = useId();
  const title = fieldLabel(fieldKey);
  const hint = fieldHint(fieldKey);
  const canAdd = spec.max === undefined || items.length < spec.max;
  const canRemove = spec.min === undefined || items.length > spec.min;
  const update = (next: unknown[]) => setValue(name, next, { shouldDirty: true });
  const move = (from: number, to: number) => {
    const next = [...items];
    const [x] = next.splice(from, 1);
    next.splice(to, 0, x);
    update(next);
  };
  const itemLabel = title.replace(/s$/, "");

  return (
    <section aria-labelledby={`${id}-title`} className="grid gap-3">
      <div className="flex items-baseline justify-between gap-2">
        <h4 id={`${id}-title`} className="text-sm font-medium">
          {title}{" "}
          <span className="text-xs font-normal text-muted-foreground">
            ({items.length}
            {spec.max ? ` of max ${spec.max}` : ""})
          </span>
        </h4>
      </div>
      {hint ? <p className="-mt-2 text-xs text-muted-foreground">{hint}</p> : null}
      {error ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {error}
        </p>
      ) : null}
      <ol className="grid gap-3">
        {items.map((_, i) => (
          <li key={i} className="rounded-lg border bg-muted/30 p-3">
            <div className="mb-3 flex items-center justify-between gap-2">
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
                  disabled={i === items.length - 1}
                  onClick={() => move(i, i + 1)}
                  aria-label={`Move ${itemLabel.toLowerCase()} ${i + 1} down`}
                >
                  <ArrowDown aria-hidden />
                </Button>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  disabled={!canRemove}
                  onClick={() => update(items.filter((__, j) => j !== i))}
                  aria-label={`Remove ${itemLabel.toLowerCase()} ${i + 1}`}
                  className="text-destructive"
                >
                  <Trash2 aria-hidden />
                </Button>
              </div>
            </div>
            {spec.item.kind === "object" ? (
              <div className="grid gap-4">
                {Object.entries(spec.item.fields).map(([k, f]) => (
                  <BlockField key={k} name={`${name}.${i}.${k}`} fieldKey={k} spec={f} />
                ))}
              </div>
            ) : (
              <BlockField name={`${name}.${i}`} fieldKey={fieldKey.replace(/s$/, "")} spec={spec.item} />
            )}
          </li>
        ))}
      </ol>
      <Button
        type="button"
        variant="outline"
        size="sm"
        className="justify-self-start"
        disabled={!canAdd}
        onClick={() =>
          update([...items, emptyValue({ ...spec.item, optional: false, defaultValue: undefined })])
        }
      >
        <Plus aria-hidden /> Add {itemLabel.toLowerCase()}
      </Button>
    </section>
  );
}
