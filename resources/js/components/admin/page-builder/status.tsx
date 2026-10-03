import { Badge } from "@/components/ui/badge";
import type { LocaleState } from "@/lib/queries/admin/pages";

/** Compact per-locale status chips: "IT · Live", "EN · Draft", "IT · Changes". */
export function LocaleStatusChips({ locales }: { locales: LocaleState[] }) {
  return (
    <span className="flex flex-wrap gap-1.5">
      {locales.map((l) => {
        const code = l.locale.toUpperCase();
        if (!l.isPublished)
          return (
            <Badge key={l.locale} variant="secondary" title={`${code}: draft, not live`}>
              {code} · Draft
            </Badge>
          );
        if (l.hasDraftChanges)
          return (
            <Badge
              key={l.locale}
              variant="outline"
              className="border-amber-400 text-amber-800"
              title={`${code}: live, with unpublished changes`}
            >
              {code} · Live + changes
            </Badge>
          );
        return (
          <Badge key={l.locale} className="bg-emerald-700 text-white" title={`${code}: live`}>
            {code} · Live
          </Badge>
        );
      })}
    </span>
  );
}
