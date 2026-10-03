"use client";

import { useState } from "react";
import { useFormContext, useFormState, useWatch } from "react-hook-form";
import { Copy } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

/**
 * Italian / English tabs for per-locale fields (ADR-007). Italian is required; English is
 * optional (`translations.en.enabled`) and public pages fall back to Italian when it is off.
 * Tabs with validation errors show a dot, and the first tab with errors is selected after
 * a failed submit.
 */
export function TranslationTabs({ children }: { children: (locale: "it" | "en") => React.ReactNode }) {
  const { control, getValues, setValue } = useFormContext();
  const { errors, submitCount } = useFormState({ control });
  const enEnabled = useWatch({ control, name: "translations.en.enabled" }) as boolean;
  const [tab, setTab] = useState<"it" | "en">("it");
  const trErrors = (errors.translations ?? {}) as Record<string, unknown>;
  const hasIt = !!trErrors.it;
  const hasEn = !!trErrors.en;

  // After a failed submit, jump to the first language with errors (state adjusted during
  // render, see https://react.dev/learn/you-might-not-need-an-effect).
  const [seenSubmit, setSeenSubmit] = useState(submitCount);
  if (submitCount !== seenSubmit) {
    setSeenSubmit(submitCount);
    if (hasIt) setTab("it");
    else if (hasEn) setTab("en");
  }

  const copyFromItalian = () => {
    const it = getValues("translations.it") as Record<string, unknown>;
    const en = getValues("translations.en") as Record<string, unknown>;
    const next: Record<string, unknown> = { ...en, enabled: true };
    for (const [k, v] of Object.entries(it)) {
      const cur = en[k];
      const empty =
        cur === "" ||
        cur === null ||
        cur === undefined ||
        (typeof cur === "object" && !(cur as { content?: unknown[] }).content?.length);
      if (empty) next[k] = v;
    }
    setValue("translations.en", next, { shouldDirty: true });
  };

  const dot = (
    <span className="ml-1 inline-block size-1.5 rounded-full bg-destructive" aria-label="has errors" />
  );

  return (
    <Tabs value={tab} onValueChange={(v) => setTab(v as "it" | "en")} className="gap-4">
      <TabsList aria-label="Language">
        <TabsTrigger value="it">Italiano{hasIt ? dot : null}</TabsTrigger>
        <TabsTrigger value="en">
          English{!enEnabled ? <span className="ml-1 text-xs text-muted-foreground">(off)</span> : null}
          {hasEn ? dot : null}
        </TabsTrigger>
      </TabsList>
      <TabsContent value="it" className="grid gap-5">
        {children("it")}
      </TabsContent>
      <TabsContent value="en" className="grid gap-5">
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/30 p-3">
          <div className="flex items-center gap-3">
            <Switch
              id="en-enabled"
              checked={!!enEnabled}
              onCheckedChange={(v) => setValue("translations.en.enabled", v, { shouldDirty: true })}
            />
            <Label htmlFor="en-enabled" className="font-normal">
              English version
            </Label>
          </div>
          <Button type="button" variant="outline" size="sm" onClick={copyFromItalian}>
            <Copy aria-hidden /> Fill empty fields from Italian
          </Button>
          {!enEnabled ? (
            <p className="w-full text-sm text-muted-foreground">
              Off: English visitors see the Italian text. Turning it off on a saved item removes the English
              version.
            </p>
          ) : null}
        </div>
        {enEnabled ? children("en") : null}
      </TabsContent>
    </Tabs>
  );
}
