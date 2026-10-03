"use client";

import { useEffect, useRef, useState } from "react";
import { toNestErrors } from "@hookform/resolvers";
import { FormProvider, useForm, type FieldError, type Resolver } from "react-hook-form";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { blockRegistry, type BlockType } from "@/lib/blocks/registry";
import { BlockField } from "./block-fields";
import { describedBlock, validateBlockValue, type BlockValue } from "./validate";

function makeResolver(type: BlockType): Resolver<BlockValue> {
  return (values, _context, options) => {
    const result = validateBlockValue(type, values);
    if (result.ok) return { values: result.cleaned, errors: {} };
    const flat: Record<string, FieldError> = {};
    for (const [path, message] of Object.entries(result.errors)) flat[path] = { type: "validation", message };
    return { values: {}, errors: toNestErrors(flat, options) };
  };
}

/**
 * Typed editor for one block, GENERATED from the block's Zod schemas (content + settings).
 * "Apply" validates with the same schemas as the server; an incomplete block can still be kept
 * as a draft ("Keep as incomplete") — it is flagged in the builder and blocks publishing.
 */
export function BlockForm({
  type,
  initial,
  onApply,
  onCancel,
  formId,
  onDraftChange,
}: {
  type: BlockType;
  initial: BlockValue;
  onApply: (value: BlockValue, valid: boolean) => void;
  onCancel: () => void;
  formId: string;
  /** Called with the in-progress (possibly invalid) values on every edit — drives the live preview. */
  onDraftChange?: (value: BlockValue) => void;
}) {
  const fields = describedBlock(type);
  const [showKeep, setShowKeep] = useState(false);
  const form = useForm<BlockValue>({
    defaultValues: structuredClone(initial),
    resolver: makeResolver(type),
    mode: "onSubmit",
    reValidateMode: "onChange",
  });
  const def = blockRegistry[type];

  const draftListener = useRef(onDraftChange);
  useEffect(() => {
    draftListener.current = onDraftChange;
  });
  const { subscribe } = form;
  useEffect(
    () =>
      subscribe({
        formState: { values: true },
        callback: ({ values }) => draftListener.current?.(structuredClone(values) as BlockValue),
      }),
    [subscribe],
  );

  const settingsEntries = Object.entries(fields.settings);
  return (
    <FormProvider {...form}>
      <form
        id={formId}
        noValidate
        className="grid gap-6"
        onSubmit={form.handleSubmit(
          (value) => onApply(value, true),
          () => setShowKeep(true),
        )}
      >
        <p className="text-sm text-muted-foreground">{def.description}</p>
        <section aria-labelledby={`${formId}-content`} className="grid gap-4">
          <h3 id={`${formId}-content`} className="sr-only">
            Content
          </h3>
          {Object.entries(fields.content).map(([k, spec]) => (
            <BlockField key={k} name={`content.${k}`} fieldKey={k} spec={spec} />
          ))}
          {Object.keys(fields.content).length === 0 ? (
            <p className="text-sm text-muted-foreground">This block has no content — only layout settings.</p>
          ) : null}
        </section>
        {settingsEntries.length ? (
          <section aria-labelledby={`${formId}-settings`} className="grid gap-4 border-t pt-4">
            <h3 id={`${formId}-settings`} className="text-sm font-semibold">
              Layout &amp; style
            </h3>
            {settingsEntries.map(([k, spec]) => (
              <BlockField key={k} name={`settings.${k}`} fieldKey={k} spec={spec} />
            ))}
          </section>
        ) : null}
        {showKeep && Object.keys(form.formState.errors).length > 0 ? (
          <div
            role="alert"
            className="flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
          >
            <AlertTriangle className="size-4 shrink-0" aria-hidden />
            <span className="flex-1">
              Some fields need attention. You can keep this block as an incomplete draft and finish it later —
              it can&apos;t be published until it&apos;s fixed.
            </span>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => onApply(validateBlockValue(type, form.getValues()).cleaned, false)}
            >
              Keep as incomplete
            </Button>
          </div>
        ) : null}
        <div className="sticky bottom-0 -mx-4 flex justify-end gap-2 border-t bg-background px-4 py-3">
          <Button type="button" variant="ghost" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="submit">Apply changes</Button>
        </div>
      </form>
    </FormProvider>
  );
}
