"use client";

import { useSyncExternalStore } from "react";
import Link from "next/link";
import { CheckCircle2, Circle, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { ChecklistItem } from "@/lib/queries/admin/dashboard";

const STORAGE_KEY = "sakila.admin.gettingStarted.dismissed";
const listeners = new Set<() => void>();

function readDismissed(): boolean {
  try {
    return window.localStorage.getItem(STORAGE_KEY) === "1";
  } catch {
    return false;
  }
}

function subscribe(l: () => void) {
  listeners.add(l);
  return () => listeners.delete(l);
}

/**
 * "Getting started" steps derived from real data. Hidden once every step is done, or when the
 * user dismisses it (remembered in this browser only).
 */
export function GettingStarted({ items }: { items: ChecklistItem[] }) {
  // Server snapshot = "dismissed" so the card never flashes in before hydration decides.
  const dismissed = useSyncExternalStore(subscribe, readDismissed, () => true);
  const doneCount = items.filter((i) => i.done).length;
  if (dismissed || !items.length || doneCount === items.length) return null;

  const dismiss = () => {
    try {
      window.localStorage.setItem(STORAGE_KEY, "1");
    } catch {
      // Storage blocked: hide for this visit only.
    }
    listeners.forEach((l) => l());
  };

  return (
    <section aria-labelledby="getting-started" className="mb-6 rounded-xl border bg-background p-5 shadow-xs">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 id="getting-started" className="font-semibold">
            Getting started
          </h2>
          <p className="text-sm text-muted-foreground">
            {doneCount} of {items.length} done. These steps make the website ready for visitors.
          </p>
        </div>
        <Button variant="ghost" size="icon" onClick={dismiss} aria-label="Hide getting started">
          <X />
        </Button>
      </div>
      <div
        className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
        role="progressbar"
        aria-label="Getting started progress"
        aria-valuemin={0}
        aria-valuemax={items.length}
        aria-valuenow={doneCount}
      >
        <div className="h-full bg-primary" style={{ width: `${(doneCount / items.length) * 100}%` }} />
      </div>
      <ol className="mt-4 grid gap-2">
        {items.map((i) => (
          <li key={i.id}>
            <Link
              prefetch={false}
              href={i.href}
              className="flex items-start gap-3 rounded-lg p-2 outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
            >
              {i.done ? (
                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" aria-hidden />
              ) : (
                <Circle className="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden />
              )}
              <span>
                <span className={i.done ? "text-muted-foreground line-through" : "font-medium"}>
                  {i.label}
                </span>
                <span className="sr-only">{i.done ? " (done)" : " (to do)"}</span>
                {!i.done ? <span className="block text-sm text-muted-foreground">{i.hint}</span> : null}
              </span>
            </Link>
          </li>
        ))}
      </ol>
    </section>
  );
}
