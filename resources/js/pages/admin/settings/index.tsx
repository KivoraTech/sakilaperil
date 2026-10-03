import Link from "next/link";
import { AlertTriangle } from "lucide-react";
import { AdminPageHeader } from "@/components/admin/layout/page-header";
import { ReferencesProvider } from "@/components/admin/blocks/references-context";
import { Head } from "@inertiajs/react";
import type { BuilderReferences } from "@/lib/queries/admin/pages";
import type { SiteSettingsData } from "@/lib/settings/schema";
import {
  sectionValues,
  settingsSectionLabels,
  settingsSections,
  type SettingsSection,
} from "@/lib/settings/sections";
import { cn } from "@/lib/utils";
import { SettingsForm } from "./settings-form";

type Props = {
  tab: string;
  settings: SiteSettingsData;
  invalid: string[];
  updatedAt: string | null;
  updatedBy: string | null;
  refs: BuilderReferences;
};

/** Port of app/admin/(shell)/settings/page.tsx (data from SettingsController). */
export default function SettingsPage({ tab, settings, invalid, updatedAt, updatedBy, refs }: Props) {
  const section: SettingsSection = (settingsSections as readonly string[]).includes(tab)
    ? (tab as SettingsSection)
    : "organization";

  return (
    <>
      <Head title="Settings" />
      <AdminPageHeader
        title="Settings"
        description={
          updatedAt
            ? `Last saved ${new Intl.DateTimeFormat("en-GB", { dateStyle: "medium", timeStyle: "short", timeZone: "Europe/Rome" }).format(new Date(updatedAt))}${updatedBy ? ` by ${updatedBy}` : ""}.`
            : "Site-wide details used across every page."
        }
      />
      {invalid.length ? (
        <div
          role="alert"
          className="mb-4 flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
        >
          <AlertTriangle className="size-4 shrink-0" aria-hidden />
          Some stored settings were invalid and are shown with defaults: {invalid.join(", ")}. Saving the
          section fixes them.
        </div>
      ) : null}
      <div className="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
        <nav aria-label="Settings sections" className="min-w-0 lg:sticky lg:top-20 lg:self-start">
          <ul className="flex gap-1 overflow-x-auto pb-1 lg:grid lg:overflow-visible">
            {settingsSections.map((s) => (
              <li key={s} className="shrink-0">
                <Link
                  prefetch={false}
                  href={`/admin/settings?tab=${s}`}
                  aria-current={s === section ? "page" : undefined}
                  className={cn(
                    "block rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground",
                    s === section && "bg-background font-medium text-foreground shadow-xs ring-1 ring-border",
                  )}
                >
                  {settingsSectionLabels[s].title}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
        <div className="min-w-0">
          <div className="mb-4">
            <h2 className="text-lg font-semibold">{settingsSectionLabels[section].title}</h2>
            <p className="text-sm text-muted-foreground">{settingsSectionLabels[section].description}</p>
          </div>
          <ReferencesProvider value={{ ...refs, linkSuggestions: [] }}>
            <SettingsForm
              key={`${section}:${updatedAt}`}
              section={section}
              initial={sectionValues(section, settings)}
            />
          </ReferencesProvider>
        </div>
      </div>
    </>
  );
}
