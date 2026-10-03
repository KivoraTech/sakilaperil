"use client";

import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  ArrowLeft,
  CheckCircle2,
  Copy,
  ExternalLink,
  Eye,
  History,
  Loader2,
  MoreHorizontal,
  PanelRight,
  Plus,
  Rocket,
  Save,
  Trash2,
  Undo2,
  EyeOff,
} from "lucide-react";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { BlockForm } from "@/components/admin/blocks/block-form";
import { ImageChooser } from "@/components/admin/blocks/media-picker";
import { ReferencesProvider, type EditorReferences } from "@/components/admin/blocks/references-context";
import { validateBlockValue, type BlockValue } from "@/components/admin/blocks/validate";
import {
  copySectionsAction,
  deletePageAction,
  publishPageAction,
  restoreRevisionAction,
  savePageDraftAction,
  unpublishPageAction,
} from "@/lib/actions/pages";
import type { ActionResult } from "@/lib/actions/result";
import { blockRegistry, isBlockType, type BlockType } from "@/lib/blocks/registry";
import { localeLabels, type Locale } from "@/lib/i18n/config";
import { blockLabel } from "@/lib/pages/publish";
import { slugSchema } from "@/lib/pages/schemas";
import type { PageEditorData } from "@/lib/queries/admin/pages";
import { BlockPalette } from "./block-palette";
import { ConfirmDialog } from "./confirm-dialog";
import { LivePreviewPanel } from "./live-preview-panel";
import { SectionList, type BuilderSection } from "./section-list";
import { useLivePreview, useLivePreviewPreference, useMediaQuery } from "./use-live-preview";
import { useUnsavedChangesWarning } from "./use-unsaved-changes";

type Draft = NonNullable<PageEditorData["draft"]>;
type Meta = {
  title: string;
  slug: string;
  seoTitle: string;
  seoDescription: string;
  ogImageId: string;
  noIndex: boolean;
};

export type BuilderPermissions = { canPublish: boolean; canDelete: boolean; canCreate: boolean };

const newKey = () =>
  `new-${typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : Math.random().toString(36).slice(2)}`;

function serialise(sections: BuilderSection[], meta: Meta) {
  return JSON.stringify({
    s: sections.map(({ id, type, isVisible, content, settings }) => ({
      id,
      type,
      isVisible,
      content,
      settings,
    })),
    meta,
  });
}

function toSections(draft: Draft): BuilderSection[] {
  return draft.sections.map((s) => ({
    key: s.id,
    id: s.id,
    type: s.type,
    isVisible: s.isVisible,
    content: s.content,
    settings: s.settings,
    broken: s.broken,
  }));
}

function toMeta(draft: Draft): Meta {
  return {
    title: draft.title,
    slug: draft.slug,
    seoTitle: draft.seoTitle,
    seoDescription: draft.seoDescription,
    ogImageId: draft.ogImageId,
    noIndex: draft.noIndex,
  };
}

/** Search-result length guidance, shared with the news/project/event editors. */
const SEO_TITLE_RECOMMENDED = 60;
const SEO_DESCRIPTION_RECOMMENDED = 155;

function formatDate(iso: string) {
  return new Intl.DateTimeFormat("en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Europe/Rome",
  }).format(new Date(iso));
}

export function PageBuilder({
  pageId,
  pageKey,
  locale,
  draft,
  references,
  permissions,
  publicPath,
  previewPath,
  otherLocales,
  menuReferences,
}: {
  pageId: string;
  pageKey: string | null;
  locale: Locale;
  draft: Draft;
  references: EditorReferences;
  permissions: BuilderPermissions;
  /** Locale-prefixed public URL when this version is live. */
  publicPath: string | null;
  previewPath: string;
  otherLocales: Locale[];
  menuReferences: number;
}) {
  const router = useRouter();
  const isHome = pageKey === "home";
  const [sections, setSections] = useState(() => toSections(draft));
  const [meta, setMeta] = useState<Meta>(() => toMeta(draft));
  const [baseline, setBaseline] = useState(() => serialise(toSections(draft), toMeta(draft)));
  const [version, setVersion] = useState(draft.version);
  const [metaErrors, setMetaErrors] = useState<Record<string, string[]>>({});
  const [serverIssues, setServerIssues] = useState<Map<string, string>>(new Map());
  const [editingKey, setEditingKey] = useState<string | null>(null);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [insertAt, setInsertAt] = useState<number | null>(null);
  const [confirm, setConfirm] = useState<
    | null
    | { kind: "deleteSection"; key: string }
    | { kind: "unpublish" | "discard" | "deletePage" | "copy" | "publishEmpty" }
    | { kind: "restore"; revisionId: string }
  >(null);
  const [pending, startTransition] = useTransition();
  const [busy, setBusy] = useState<null | "save" | "publish" | "preview" | "other">(null);
  const [announcement, setAnnouncement] = useState("");
  /** In-progress (not yet applied) values of the block being edited, as last known valid. */
  const [inProgress, setInProgress] = useState<{ key: string; value: BlockValue } | null>(null);
  const [focusRequest, setFocusRequest] = useState<{ sectionKey: string; n: number } | null>(null);
  const [tab, setTab] = useState<"sections" | "page">("sections");

  // The builder stays mounted across saves (so the preview iframe keeps its scroll position).
  // When the server's draft changes for another reason (restore, discard, copy, publish), reset.
  const [syncedVersion, setSyncedVersion] = useState(draft.version);
  if (draft.version !== syncedVersion) {
    setSyncedVersion(draft.version);
    if (draft.version !== version) {
      const nextSections = toSections(draft);
      const nextMeta = toMeta(draft);
      setSections(nextSections);
      setMeta(nextMeta);
      setBaseline(serialise(nextSections, nextMeta));
      setVersion(draft.version);
      setServerIssues(new Map());
      setMetaErrors({});
      setEditingKey(null);
      setInProgress(null);
    }
  }

  // ── live preview ───────────────────────────────────────────────────
  const [previewPref, setPreviewPref] = useLivePreviewPreference();
  const splitCapable = useMediaQuery("(min-width: 1024px)");
  const split = previewPref && splitCapable;
  const previewSections = useMemo(
    () =>
      inProgress
        ? sections.map((s) =>
            s.key === inProgress.key
              ? { ...s, content: inProgress.value.content, settings: inProgress.value.settings }
              : s,
          )
        : sections,
    [sections, inProgress],
  );
  const preview = useLivePreview({
    pageId,
    locale,
    enabled: split,
    title: meta.title,
    sections: previewSections,
  });
  const embedSrc = `${previewPath}?embed=1&draft=${encodeURIComponent(preview.clientId)}`;
  const newTabHref = `${previewPath}?draft=${encodeURIComponent(preview.clientId)}`;
  const focusSection = (key: string) => setFocusRequest((f) => ({ sectionKey: key, n: (f?.n ?? 0) + 1 }));

  const dirty = serialise(sections, meta) !== baseline;
  useUnsavedChangesWarning(dirty);

  const liveRegion = (msg: string) => setAnnouncement(msg);

  // Client-side problems (same Zod schemas as the server) + issues reported by the server.
  const problems = useMemo(() => {
    const map = new Map<string, string>();
    const singletons = new Set<string>();
    for (const s of sections) {
      if (s.broken || !isBlockType(s.type)) {
        map.set(s.key, "This block type is unknown and cannot be edited");
        continue;
      }
      if (!s.isVisible) continue;
      if (blockRegistry[s.type].singleton) {
        if (singletons.has(s.type)) {
          map.set(s.key, `Only one ${blockLabel(s.type)} per page — delete or hide this one`);
          continue;
        }
        singletons.add(s.type);
      }
      const r = validateBlockValue(s.type, { content: s.content, settings: s.settings });
      if (!r.ok) {
        const [path, msg] = Object.entries(r.errors)[0]!;
        map.set(s.key, `${path.split(".").slice(1).join(" › ")}: ${msg}`);
      }
    }
    for (const s of sections) {
      const server = s.id ? serverIssues.get(s.id) : undefined;
      if (server && !map.has(s.key) && !dirty) map.set(s.key, server);
    }
    return map;
  }, [sections, serverIssues, dirty]);

  const slugError = useMemo(() => {
    if (isHome || meta.slug === draft.slug) return undefined;
    const r = slugSchema.safeParse(meta.slug);
    return r.success ? undefined : r.error.issues[0]?.message;
  }, [meta.slug, draft.slug, isHome]);

  const liveFieldsLocked = draft.isPublished && !permissions.canPublish;

  // ── section operations ─────────────────────────────────────────────
  const move = useCallback((key: string, to: number) => {
    setSections((prev) => {
      const from = prev.findIndex((s) => s.key === key);
      if (from < 0 || to < 0 || to >= prev.length || from === to) return prev;
      const next = [...prev];
      const [item] = next.splice(from, 1);
      next.splice(to, 0, item!);
      liveRegion(`${blockLabel(item!.type)} moved to position ${to + 1} of ${next.length}.`);
      return next;
    });
  }, []);

  const addBlock = (type: BlockType) => {
    const d = blockRegistry[type].defaults();
    const section: BuilderSection = {
      key: newKey(),
      type,
      isVisible: true,
      content: structuredClone(d.content) as Record<string, unknown>,
      settings: structuredClone(d.settings) as Record<string, unknown>,
    };
    setSections((prev) => {
      const next = [...prev];
      const index = insertAt === null ? next.length : insertAt + 1;
      next.splice(index, 0, section);
      return next;
    });
    setInsertAt(null);
    openEditor(section.key);
    liveRegion(`${blockRegistry[type].label} added.`);
  };

  const duplicate = (key: string) => {
    setSections((prev) => {
      const i = prev.findIndex((s) => s.key === key);
      if (i < 0) return prev;
      const src = prev[i]!;
      const copy: BuilderSection = {
        key: newKey(),
        type: src.type,
        isVisible: src.isVisible,
        content: structuredClone(src.content),
        settings: structuredClone({ ...src.settings, anchor: undefined }),
      };
      const next = [...prev];
      next.splice(i + 1, 0, copy);
      liveRegion(`${blockLabel(src.type)} duplicated.`);
      return next;
    });
  };

  const toggleVisible = (key: string) =>
    setSections((prev) =>
      prev.map((s) => {
        if (s.key !== key) return s;
        liveRegion(`${blockLabel(s.type)} ${s.isVisible ? "hidden" : "shown"}.`);
        return { ...s, isVisible: !s.isVisible };
      }),
    );

  const removeSection = (key: string) => {
    setSections((prev) => prev.filter((s) => s.key !== key));
    liveRegion("Section deleted.");
  };

  // ── server actions ─────────────────────────────────────────────────
  const handleError = (r: Extract<ActionResult<unknown>, { ok: false }>) => {
    if (r.fieldErrors) {
      const issues = new Map<string, string>();
      const metaErrs: Record<string, string[]> = {};
      for (const [k, v] of Object.entries(r.fieldErrors)) {
        if (k.startsWith("section:")) issues.set(k.slice(8), v.join(" — "));
        else metaErrs[k.replace(/^meta\./, "")] = v;
      }
      setServerIssues(issues);
      setMetaErrors(metaErrs);
    }
    toast.error(r.message);
  };

  const save = useCallback(async (): Promise<boolean> => {
    const payloadMeta = { ...meta };
    const result = await savePageDraftAction({
      pageId,
      locale,
      expectedVersion: version,
      meta: payloadMeta,
      sections: sections.map((s) => ({
        id: s.id,
        type: s.type,
        isVisible: s.isVisible,
        content: s.content,
        settings: s.settings,
      })),
    });
    if (!result.ok) {
      handleError(result);
      return false;
    }
    const saved = sections.map((s, i) => ({ ...s, key: s.key, id: result.data.sectionIds[i] }));
    setSections(saved);
    setVersion(result.data.version);
    setBaseline(serialise(saved, payloadMeta));
    setMetaErrors({});
    const issues = new Map<string, string>();
    for (const issue of result.data.issues) issues.set(issue.sectionId, issue.messages.join(" — "));
    setServerIssues(issues);
    return true;
  }, [meta, pageId, locale, version, sections]);

  const run = (kind: NonNullable<typeof busy>, fn: () => Promise<void>) => {
    setBusy(kind);
    startTransition(async () => {
      try {
        await fn();
      } finally {
        setBusy(null);
      }
    });
  };

  const onSave = () =>
    run("save", async () => {
      if (await save()) {
        toast.success(
          problems.size ? "Draft saved — some sections need attention before publishing" : "Draft saved",
        );
        router.refresh();
      }
    });

  /** Full-page preview in a new tab, showing the current (unsaved) editor state. */
  const onPreview = () => {
    // Open the tab synchronously (popup blockers), then point it at the preview once staged.
    const win = window.open("about:blank", "_blank");
    run("preview", async () => {
      if (!(await preview.flush())) {
        win?.close();
        toast.error(preview.error ?? "The preview could not be prepared. Please try again.");
        return;
      }
      if (win) {
        win.opener = null;
        win.location.href = newTabHref;
      } else {
        toast.message("Preview blocked by the browser", {
          action: { label: "Open", onClick: () => window.open(newTabHref, "_blank", "noopener") },
        });
      }
    });
  };

  const openEditor = (key: string) => {
    setInProgress(null);
    setEditingKey(key);
    if (split) focusSection(key);
  };
  const closeEditor = () => {
    setEditingKey(null);
    setInProgress(null);
  };
  const onBlockDraft = (key: string, type: BlockType, value: BlockValue) => {
    // Only valid in-progress values reach the preview; while a field is invalid the preview
    // keeps showing the last valid version of the block.
    const r = validateBlockValue(type, value);
    if (r.ok) setInProgress({ key, value: r.cleaned });
  };

  const onPublish = (confirmEmpty = false) => {
    // A page with no visible section is almost always a mistake: never for the home page, and only
    // after an explicit confirmation for the others (the server enforces the same rule).
    if (!sections.some((s) => s.isVisible)) {
      if (isHome) {
        toast.error("The home page needs at least one visible section before it can be published.");
        return;
      }
      if (!confirmEmpty) {
        setConfirm({ kind: "publishEmpty" });
        return;
      }
    }
    run("publish", async () => {
      if (dirty && !(await save())) return;
      const r = await publishPageAction({ pageId, locale, confirmEmpty });
      if (!r.ok) {
        if (r.fieldErrors?.sections?.includes("EMPTY_PAGE")) {
          setConfirm({ kind: "publishEmpty" });
          return;
        }
        if (r.fieldErrors?.sections) {
          toast.error(r.message);
          return;
        }
        return handleError(r);
      }
      setServerIssues(new Map());
      toast.success(`Published ${localeLabels[locale]} version`, {
        description: publicPath ?? undefined,
      });
      router.refresh();
    });
  };

  const simple = (fn: () => Promise<ActionResult<unknown>>, after?: () => void) =>
    run("other", async () => {
      const r = await fn();
      if (!r.ok) return handleError(r);
      toast.success(r.message ?? "Done");
      after?.();
      router.refresh();
    });

  // Ctrl/Cmd+S saves the draft.
  const saveRef = useRef(onSave);
  useEffect(() => {
    saveRef.current = onSave;
  });
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "s") {
        e.preventDefault();
        saveRef.current();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  const editing = editingKey ? sections.find((s) => s.key === editingKey) : undefined;
  const existingTypes = new Set(sections.map((s) => s.type));
  const statusBadge = !draft.isPublished ? (
    <Badge variant="secondary">Draft — not live</Badge>
  ) : dirty || draft.hasDraftChanges ? (
    <Badge variant="outline" className="border-amber-400 text-amber-800">
      Live · unpublished changes
    </Badge>
  ) : (
    <Badge className="gap-1 bg-emerald-700 text-white">
      <CheckCircle2 className="size-3" aria-hidden /> Live
    </Badge>
  );

  const editingIndex = editing ? sections.findIndex((s) => s.key === editing.key) : -1;
  const publishBlockedByProblems = permissions.canPublish && problems.size > 0;
  const pageSettingsHasErrors = !!slugError || Object.keys(metaErrors).length > 0;

  const renderBlockForm = (section: BuilderSection) => (
    <BlockForm
      key={section.key}
      formId={`block-${section.key}`}
      type={section.type as BlockType}
      initial={{ content: section.content, settings: section.settings }}
      onDraftChange={(value) => onBlockDraft(section.key, section.type as BlockType, value)}
      onCancel={closeEditor}
      onApply={(value, valid) => {
        setSections((prev) =>
          prev.map((s) =>
            s.key === section.key ? { ...s, content: value.content, settings: value.settings } : s,
          ),
        );
        closeEditor();
        if (!valid) toast.warning("Kept as incomplete — fix it before publishing");
      }}
    />
  );

  const sectionsPanel = (
    <section aria-labelledby="sections-heading" className="grid content-start gap-4">
      <div className="flex items-center justify-between gap-2">
        <h2 id="sections-heading" className="text-lg font-semibold">
          Sections <span className="text-sm font-normal text-muted-foreground">({sections.length})</span>
        </h2>
        <Button
          type="button"
          onClick={() => {
            setInsertAt(null);
            setPaletteOpen(true);
          }}
        >
          <Plus aria-hidden /> Add section
        </Button>
      </div>
      {sections.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-10 text-center">
          <p className="font-medium">This page has no sections yet.</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Start with a Hero, then add text, photos, projects or a donate section.
          </p>
          <Button type="button" className="mt-4" onClick={() => setPaletteOpen(true)}>
            <Plus aria-hidden /> Add the first section
          </Button>
        </div>
      ) : (
        <SectionList
          sections={sections}
          problems={problems}
          onEdit={openEditor}
          onMove={move}
          onDuplicate={duplicate}
          onToggleVisible={toggleVisible}
          onDelete={(key) => setConfirm({ kind: "deleteSection", key })}
          onAddBelow={(i) => {
            setInsertAt(i);
            setPaletteOpen(true);
          }}
          duplicateDisabled={(s) => !isBlockType(s.type) || !!blockRegistry[s.type].singleton || !!s.broken}
        />
      )}
      <p className="text-xs text-muted-foreground">
        Tip: drag the handle (or focus it and press Space, then the arrow keys) to reorder. Hidden sections
        stay in the draft but are not published. Press Ctrl/⌘ + S to save.
      </p>
    </section>
  );

  const pageSettingsPanel = (
    <div className="grid content-start gap-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Page</CardTitle>
          <CardDescription>
            {localeLabels[locale]} version
            {draft.publishedAt ? ` · last published ${formatDate(draft.publishedAt)}` : ""}
          </CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
          <MetaField
            id="meta-title"
            label="Page title"
            hint="Shown in the browser tab and in search results (unless you set a search result title)."
            error={metaErrors.title?.[0]}
          >
            <Input
              id="meta-title"
              value={meta.title}
              maxLength={160}
              onChange={(e) => setMeta({ ...meta, title: e.target.value })}
              aria-invalid={!!metaErrors.title || undefined}
            />
          </MetaField>
          <MetaField
            id="meta-slug"
            label="Web address"
            error={slugError ?? metaErrors.slug?.[0]}
            hint={
              isHome
                ? `The home page is always at /${locale}.`
                : liveFieldsLocked
                  ? "Only editors can change the address of a live page."
                  : draft.isPublished
                    ? `Changing the address of a live page takes effect immediately on save. /${locale}/${meta.slug || "…"}`
                    : `/${locale}/${meta.slug || "…"}`
            }
          >
            <Input
              id="meta-slug"
              value={isHome ? "home" : meta.slug}
              disabled={isHome || liveFieldsLocked}
              maxLength={80}
              onChange={(e) => setMeta({ ...meta, slug: e.target.value.toLowerCase() })}
              aria-invalid={!!(slugError ?? metaErrors.slug) || undefined}
            />
          </MetaField>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Search &amp; sharing</CardTitle>
          <CardDescription>Shown by Google and when the page is shared.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4">
          <MetaField
            id="meta-seo-title"
            label="Search result title"
            hint={`${meta.seoTitle.length}/${SEO_TITLE_RECOMMENDED} recommended · leave empty to use the page title`}
            error={metaErrors.seoTitle?.[0]}
          >
            <Input
              id="meta-seo-title"
              value={meta.seoTitle}
              maxLength={70}
              onChange={(e) => setMeta({ ...meta, seoTitle: e.target.value })}
            />
          </MetaField>
          <MetaField
            id="meta-seo-description"
            label="Search result description"
            hint={`${meta.seoDescription.length}/${SEO_DESCRIPTION_RECOMMENDED} recommended · the short text under the title in Google`}
            error={metaErrors.seoDescription?.[0]}
          >
            <Textarea
              id="meta-seo-description"
              rows={3}
              value={meta.seoDescription}
              maxLength={160}
              onChange={(e) => setMeta({ ...meta, seoDescription: e.target.value })}
            />
          </MetaField>
          <div className="grid gap-1.5">
            <span className="text-sm font-medium">Social sharing image</span>
            <ImageChooser
              label="sharing image"
              assetId={meta.ogImageId || undefined}
              optional
              onChange={(id) => setMeta({ ...meta, ogImageId: id ?? "" })}
            />
          </div>
          <div className="flex items-start justify-between gap-3">
            <div className="grid gap-0.5">
              <Label htmlFor="meta-noindex">Hide from search engines</Label>
              <p className="text-xs text-muted-foreground">
                Google won&apos;t list this page and it is left out of the sitemap. Visitors with the link can
                still open it.
              </p>
            </div>
            <Switch
              id="meta-noindex"
              checked={meta.noIndex}
              disabled={liveFieldsLocked}
              onCheckedChange={(c) => setMeta({ ...meta, noIndex: c })}
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <History className="size-4" aria-hidden /> Published versions
          </CardTitle>
          <CardDescription>Restore an older version into the draft, then publish it.</CardDescription>
        </CardHeader>
        <CardContent>
          {draft.revisions.length === 0 ? (
            <p className="text-sm text-muted-foreground">Not published yet.</p>
          ) : (
            <ul className="grid gap-2 text-sm">
              {draft.revisions.map((r) => (
                <li key={r.id} className="flex items-center justify-between gap-2">
                  <span className="min-w-0">
                    <span className="block truncate">{formatDate(r.createdAt)}</span>
                    <span className="text-xs text-muted-foreground">
                      {r.createdBy ?? "Unknown"}
                      {r.isLive ? " · live" : ""}
                    </span>
                  </span>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={pending}
                    onClick={() => setConfirm({ kind: "restore", revisionId: r.id })}
                  >
                    Restore
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </div>
  );

  return (
    <ReferencesProvider value={references}>
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>

      {/* Action bar */}
      <div className="sticky top-14 z-20 -mx-4 mb-6 border-b bg-background/95 px-4 py-3 backdrop-blur md:-mx-8 md:px-8">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            {statusBadge}
            {dirty ? (
              <span className="text-xs font-medium text-amber-700" role="status">
                Unsaved changes
              </span>
            ) : (
              <span className="text-xs text-muted-foreground">All changes saved</span>
            )}
            {problems.size ? (
              <span
                id="publish-blocked-reason"
                className="text-xs text-amber-800"
                data-testid="publish-blocked-reason"
              >
                {problems.size} section{problems.size === 1 ? "" : "s"} need{problems.size === 1 ? "s" : ""}{" "}
                attention
                {publishBlockedByProblems ? " — fix before publishing" : ""}
              </span>
            ) : null}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {publicPath ? (
              <Button type="button" variant="ghost" asChild>
                <a href={publicPath} target="_blank" rel="noopener noreferrer">
                  <ExternalLink aria-hidden /> View live page
                </a>
              </Button>
            ) : null}
            <Button
              type="button"
              variant={split ? "secondary" : "outline"}
              className="hidden lg:inline-flex"
              aria-pressed={split}
              onClick={() => setPreviewPref(!split)}
            >
              <PanelRight aria-hidden /> Live preview
            </Button>
            {!split ? (
              <Button type="button" variant="outline" onClick={onPreview} disabled={pending}>
                {busy === "preview" ? <Loader2 className="animate-spin" aria-hidden /> : <Eye aria-hidden />}
                Preview
              </Button>
            ) : null}
            <Button type="button" variant="outline" onClick={onSave} disabled={pending || !dirty}>
              {busy === "save" ? <Loader2 className="animate-spin" aria-hidden /> : <Save aria-hidden />}
              Save draft
            </Button>
            {permissions.canPublish ? (
              <Button
                type="button"
                onClick={() => onPublish()}
                disabled={
                  pending || problems.size > 0 || (!dirty && draft.isPublished && !draft.hasDraftChanges)
                }
                aria-describedby={publishBlockedByProblems ? "publish-blocked-reason" : undefined}
              >
                {busy === "publish" ? (
                  <Loader2 className="animate-spin" aria-hidden />
                ) : (
                  <Rocket aria-hidden />
                )}
                {dirty ? "Save & publish" : "Publish"}
              </Button>
            ) : (
              <span className="text-xs text-muted-foreground">An editor will publish your changes.</span>
            )}
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label="More page actions"
                  disabled={pending}
                >
                  <MoreHorizontal aria-hidden />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuItem onSelect={onPreview}>
                  <Eye aria-hidden /> Preview in a new tab
                </DropdownMenuItem>
                {otherLocales.map((l) => (
                  <DropdownMenuItem key={l} onSelect={() => setConfirm({ kind: "copy" })}>
                    <Copy aria-hidden /> Replace with {localeLabels[l]} sections
                  </DropdownMenuItem>
                ))}
                {draft.isPublished && draft.hasDraftChanges ? (
                  <DropdownMenuItem onSelect={() => setConfirm({ kind: "discard" })}>
                    <Undo2 aria-hidden /> Discard unpublished changes
                  </DropdownMenuItem>
                ) : null}
                {permissions.canPublish && draft.isPublished && !isHome ? (
                  <DropdownMenuItem onSelect={() => setConfirm({ kind: "unpublish" })}>
                    <EyeOff aria-hidden /> Unpublish
                  </DropdownMenuItem>
                ) : null}
                {permissions.canDelete && !pageKey ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      variant="destructive"
                      onSelect={() => setConfirm({ kind: "deletePage" })}
                    >
                      <Trash2 aria-hidden /> Delete page
                    </DropdownMenuItem>
                  </>
                ) : null}
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>
      </div>

      {split ? (
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(22rem,30rem)_minmax(0,1fr)]">
          <div className="min-w-0">
            {editing && isBlockType(editing.type) ? (
              <section
                data-testid="block-editor"
                aria-labelledby="block-editor-title"
                className="grid gap-4 rounded-xl border bg-background p-4"
              >
                <div className="grid gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="-ml-2 justify-self-start"
                    onClick={() => {
                      if (inProgress && !window.confirm("Discard the changes to this section?")) return;
                      closeEditor();
                    }}
                  >
                    <ArrowLeft aria-hidden /> All sections
                  </Button>
                  <h2 id="block-editor-title" className="text-lg font-semibold">
                    Edit {blockLabel(editing.type)}
                  </h2>
                  <p className="text-sm text-muted-foreground">
                    Section {editingIndex + 1} of {sections.length} · the preview updates as you type. Click
                    “Apply changes” to keep them, then save the draft.
                  </p>
                </div>
                {renderBlockForm(editing)}
              </section>
            ) : (
              <Tabs value={tab} onValueChange={(v) => setTab(v as "sections" | "page")}>
                <TabsList className="w-full">
                  <TabsTrigger value="sections">Sections</TabsTrigger>
                  <TabsTrigger value="page">
                    Page settings
                    {pageSettingsHasErrors ? (
                      <>
                        <span className="size-2 rounded-full bg-destructive" aria-hidden />
                        <span className="sr-only">(has errors)</span>
                      </>
                    ) : null}
                  </TabsTrigger>
                </TabsList>
                <TabsContent value="sections" className="mt-4">
                  {sectionsPanel}
                </TabsContent>
                <TabsContent value="page" className="mt-4">
                  {pageSettingsPanel}
                </TabsContent>
              </Tabs>
            )}
          </div>
          <LivePreviewPanel
            className="sticky top-[8.25rem] h-[calc(100dvh-9.25rem)] min-h-[28rem]"
            src={embedSrc}
            newTabHref={newTabHref}
            status={preview.status}
            error={preview.error}
            stagedAt={preview.stagedAt}
            refreshToken={preview.refreshToken}
            focusRequest={focusRequest}
            dirty={dirty || inProgress !== null}
            onRetry={() => void preview.retry()}
            onOpenNewTab={onPreview}
          />
        </div>
      ) : (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
          {sectionsPanel}
          <aside aria-label="Page settings">{pageSettingsPanel}</aside>
        </div>
      )}

      <BlockPalette
        open={paletteOpen}
        onOpenChange={setPaletteOpen}
        onPick={addBlock}
        existingTypes={existingTypes}
      />

      <Sheet open={!split && !!editing} onOpenChange={(o) => !o && closeEditor()}>
        <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl" data-testid="block-editor">
          {editing && isBlockType(editing.type) ? (
            <>
              <SheetHeader className="px-4">
                <SheetTitle>Edit {blockLabel(editing.type)}</SheetTitle>
                <SheetDescription>
                  Section {editingIndex + 1} of {sections.length} · click “Apply changes” to keep them, then
                  save the draft.
                </SheetDescription>
              </SheetHeader>
              <div className="px-4 pb-4">{renderBlockForm(editing)}</div>
            </>
          ) : null}
        </SheetContent>
      </Sheet>

      <ConfirmDialog
        open={confirm?.kind === "deleteSection"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title="Delete this section?"
        description="It will be removed from the draft. The live page is not affected until you publish."
        confirmLabel="Delete section"
        destructive
        onConfirm={() => {
          if (confirm?.kind === "deleteSection") removeSection(confirm.key);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "unpublish"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={`Unpublish the ${localeLabels[locale]} version?`}
        description="Visitors will get a “page not found” and menu links to it will disappear. The draft is kept and you can publish again later."
        confirmLabel="Unpublish"
        destructive
        onConfirm={() => {
          setConfirm(null);
          simple(() => unpublishPageAction({ pageId, locale }));
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "publishEmpty"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title="Publish an empty page?"
        description="This page has no visible sections, so visitors would see only the header and footer. Add or show a section first, or publish it empty if that is what you want."
        confirmLabel="Publish empty page"
        destructive
        onConfirm={() => {
          setConfirm(null);
          onPublish(true);
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "discard"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title="Discard unpublished changes?"
        description="The draft will be replaced by the version that is live now. This cannot be undone."
        confirmLabel="Discard changes"
        destructive
        onConfirm={() => {
          setConfirm(null);
          simple(
            () => restoreRevisionAction({ pageId, locale }),
            () => setBaseline(serialise(sections, meta)),
          );
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "restore"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title="Restore this version into the draft?"
        description="Your current draft sections, title and SEO text will be replaced. Publish afterwards to make it live."
        confirmLabel="Restore"
        onConfirm={() => {
          const revisionId = confirm?.kind === "restore" ? confirm.revisionId : undefined;
          setConfirm(null);
          simple(
            () => restoreRevisionAction({ pageId, locale, revisionId }),
            () => setBaseline(serialise(sections, meta)),
          );
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "copy"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title={`Replace these sections with the ${otherLocales.map((l) => localeLabels[l]).join("/")} ones?`}
        description="All sections of this language version are replaced by a copy (texts stay in the other language until you translate them)."
        confirmLabel="Replace sections"
        destructive
        onConfirm={() => {
          setConfirm(null);
          const from = otherLocales[0];
          if (from)
            simple(
              () => copySectionsAction({ pageId, from, to: locale }),
              () => setBaseline(serialise(sections, meta)),
            );
        }}
      />
      <ConfirmDialog
        open={confirm?.kind === "deletePage"}
        onOpenChange={(o) => !o && setConfirm(null)}
        title="Delete this page permanently?"
        description={
          menuReferences > 0
            ? `All language versions, drafts and history are deleted, and ${menuReferences} menu link(s) to it are removed. This cannot be undone.`
            : "All language versions, drafts and history are deleted. This cannot be undone."
        }
        confirmLabel="Delete page"
        destructive
        onConfirm={() => {
          setConfirm(null);
          run("other", async () => {
            const r = await deletePageAction({ pageId });
            if (!r.ok) return handleError(r);
            setBaseline(serialise(sections, meta));
            toast.success("Page deleted");
            router.push("/admin/pages");
          });
        }}
      />
    </ReferencesProvider>
  );
}

function MetaField({
  id,
  label,
  hint,
  error,
  children,
}: {
  id: string;
  label: string;
  hint?: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      {error ? (
        <p role="alert" className="text-xs font-medium text-destructive">
          {error}
        </p>
      ) : null}
    </div>
  );
}
