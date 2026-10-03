import { useState } from "react";
import { useRouter } from "next/navigation";
import { FilePlus2, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { createSystemPageAction } from "@/lib/actions/pages";

const LABELS: Record<string, { title: string; why: string }> = {
  home: { title: "Home page", why: "Without it the website opens on a “page not found”." },
  privacy: { title: "Privacy page", why: "Every form links to it next to the consent box." },
};

/**
 * Shown when the installation has no content import (migrations only): the pages the site relies on
 * do not exist and the regular "New page" dialog cannot create them.
 */
export function MissingPages({ keys }: { keys: string[] }) {
  const router = useRouter();
  const [busy, setBusy] = useState<string | null>(null);
  if (!keys.length) return null;

  const create = async (key: string) => {
    setBusy(key);
    const r = await createSystemPageAction({ key });
    setBusy(null);
    if (!r.ok) {
      toast.error(r.message);
      return;
    }
    toast.success(r.message ?? "Page created");
    router.push(`/admin/pages/${r.data.id}`);
  };

  return (
    <section
      aria-labelledby="missing-pages"
      className="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950"
    >
      <h2 id="missing-pages" className="font-semibold">
        This website has no {keys.map((k) => LABELS[k]?.title.toLowerCase() ?? k).join(" and no ")} yet
      </h2>
      <p className="mt-1 text-sm">
        Create {keys.length > 1 ? "them" : "it"} here as an empty draft, add sections and publish. (If you
        have the content package, your developer can load everything at once with{" "}
        <code>php artisan sakila:load-content</code>.)
      </p>
      <ul className="mt-3 grid gap-2">
        {keys.map((key) => (
          <li key={key} className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <Button type="button" size="sm" disabled={busy !== null} onClick={() => create(key)}>
              {busy === key ? <Loader2 className="animate-spin" aria-hidden /> : <FilePlus2 aria-hidden />}
              Create the {LABELS[key]?.title.toLowerCase() ?? key}
            </Button>
            <span className="text-sm">{LABELS[key]?.why}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
