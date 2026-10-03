"use client";

import { useMemo, useState } from "react";
import { Search } from "lucide-react";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { blockRegistry, blockTypes, type BlockCategory, type BlockType } from "@/lib/blocks/registry";

const CATEGORY_LABELS: Record<BlockCategory, string> = {
  hero: "Opening",
  content: "Text & stories",
  media: "Photos",
  collections: "Projects, news & reports",
  engagement: "Donate & get involved",
  layout: "Layout",
};
const CATEGORY_ORDER: BlockCategory[] = ["hero", "content", "media", "collections", "engagement", "layout"];

export function BlockPalette({
  open,
  onOpenChange,
  onPick,
  existingTypes,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onPick: (type: BlockType) => void;
  existingTypes: Set<string>;
}) {
  const [q, setQ] = useState("");
  const groups = useMemo(() => {
    const needle = q.trim().toLowerCase();
    return CATEGORY_ORDER.map((cat) => ({
      cat,
      types: blockTypes.filter((t) => {
        const d = blockRegistry[t];
        if (d.category !== cat) return false;
        return (
          !needle || d.label.toLowerCase().includes(needle) || d.description.toLowerCase().includes(needle)
        );
      }),
    })).filter((g) => g.types.length > 0);
  }, [q]);

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => {
        onOpenChange(o);
        if (!o) setQ("");
      }}
    >
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Add a section</DialogTitle>
          <DialogDescription>
            Choose what kind of section to add. You can edit, move or hide it afterwards.
          </DialogDescription>
        </DialogHeader>
        <div className="relative">
          <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            aria-label="Search section types"
            placeholder="Search section types…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            className="pl-8"
            autoFocus
          />
        </div>
        {groups.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">No section types match.</p>
        ) : null}
        {groups.map((g) => (
          <section key={g.cat} aria-labelledby={`palette-${g.cat}`}>
            <h3
              id={`palette-${g.cat}`}
              className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
              {CATEGORY_LABELS[g.cat]}
            </h3>
            <ul className="grid gap-2 sm:grid-cols-2">
              {g.types.map((t) => {
                const d = blockRegistry[t];
                const disabled = !!("singleton" in d && d.singleton) && existingTypes.has(t);
                return (
                  <li key={t}>
                    <button
                      type="button"
                      disabled={disabled}
                      onClick={() => {
                        onPick(t);
                        onOpenChange(false);
                        setQ("");
                      }}
                      className="grid h-full w-full gap-0.5 rounded-lg border p-3 text-left outline-none hover:border-primary/50 hover:bg-muted/50 focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      <span className="text-sm font-medium">{d.label}</span>
                      <span className="text-xs text-muted-foreground">
                        {disabled ? "Already on this page (only one allowed)" : d.description}
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </section>
        ))}
      </DialogContent>
    </Dialog>
  );
}
