"use client";

import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  ArrowDown,
  ArrowUp,
  ChevronDown,
  ChevronRight,
  EyeOff,
  GripVertical,
  IndentDecrease,
  IndentIncrease,
  Loader2,
  Plus,
  Save,
  Trash2,
} from "lucide-react";
import { toast } from "sonner";
import { ConfirmDialog } from "@/components/admin/page-builder/confirm-dialog";
import { useUnsavedChangesWarning } from "@/components/admin/page-builder/use-unsaved-changes";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { saveNavigationAction } from "@/lib/actions/navigation";
import { routeKeys } from "@/lib/i18n/routes";
import {
  navigationLabels,
  navItemSchema,
  routeKeyLabels,
  type NavigationKeyName,
} from "@/lib/navigation/schema";
import type { NavEditorItem } from "@/lib/queries/admin/navigation";
import { cn } from "@/lib/utils";

type PageOption = { id: string; title: string; isPublished: boolean };

const linkTypeLabels = {
  PAGE: "A page of the site",
  ROUTE: "A section (projects, news, donate…)",
  INTERNAL: "A custom path on this site",
  EXTERNAL: "An external website",
} as const;

const newKey = () => `new-${Math.random().toString(36).slice(2)}`;

export function NavigationEditor({
  menuKey,
  initialItems,
  pages,
}: {
  menuKey: NavigationKeyName;
  initialItems: NavEditorItem[];
  pages: PageOption[];
}) {
  const router = useRouter();
  const nested = navigationLabels[menuKey].nested;
  const [items, setItems] = useState(initialItems);
  const [baseline, setBaseline] = useState(() => JSON.stringify(initialItems));
  const [expanded, setExpanded] = useState<string | null>(null);
  const [toDelete, setToDelete] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [pending, start] = useTransition();
  const [announcement, setAnnouncement] = useState("");
  const dirty = JSON.stringify(items) !== baseline;
  useUnsavedChangesWarning(dirty);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const clientErrors = useMemo(() => {
    const out: Record<string, string> = {};
    items.forEach((item) => {
      const r = navItemSchema.safeParse(item);
      if (!r.success) out[item.key] = r.error.issues[0]?.message ?? "Invalid";
    });
    return out;
  }, [items]);

  const update = (key: string, patch: Partial<NavEditorItem>) =>
    setItems((prev) => prev.map((i) => (i.key === key ? { ...i, ...patch } : i)));

  const move = (key: string, to: number) =>
    setItems((prev) => {
      const from = prev.findIndex((i) => i.key === key);
      if (from < 0 || to < 0 || to >= prev.length) return prev;
      const next = [...prev];
      const [x] = next.splice(from, 1);
      next.splice(to, 0, x!);
      if (next[0]) next[0] = { ...next[0], depth: 0 };
      setAnnouncement(`${x!.label.it || "Link"} moved to position ${to + 1} of ${next.length}.`);
      return next;
    });

  const onDragEnd = (e: DragEndEvent) => {
    if (!e.over || e.active.id === e.over.id) return;
    const to = items.findIndex((i) => i.key === e.over!.id);
    move(String(e.active.id), to);
  };

  const add = () => {
    const item: NavEditorItem = {
      key: newKey(),
      label: { it: "", en: "" },
      linkType: "PAGE",
      pageId: undefined,
      openInNewTab: false,
      isVisible: true,
      depth: 0,
    };
    setItems((prev) => [...prev, item]);
    setExpanded(item.key);
  };

  const save = () =>
    start(async () => {
      if (Object.keys(clientErrors).length) {
        setErrors(clientErrors);
        setExpanded(Object.keys(clientErrors)[0] ?? null);
        toast.error("Some links are incomplete.");
        return;
      }
      const r = await saveNavigationAction({
        key: menuKey,
        items: items.map(({ key: _k, ...rest }) => ({
          ...rest,
          label: { it: rest.label.it, en: rest.label.en || undefined },
        })),
      });
      if (!r.ok) {
        toast.error(r.message);
        return;
      }
      setErrors({});
      setBaseline(JSON.stringify(items));
      toast.success(r.message ?? "Saved");
      router.refresh();
    });

  return (
    <div className="grid gap-4">
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          {navigationLabels[menuKey].description} Changes go live when you save. Links to unpublished pages
          are hidden automatically.
        </p>
        <div className="flex gap-2">
          <Button type="button" variant="outline" onClick={add} disabled={items.length >= 40}>
            <Plus aria-hidden /> Add link
          </Button>
          <Button type="button" onClick={save} disabled={pending || !dirty}>
            {pending ? <Loader2 className="animate-spin" aria-hidden /> : <Save aria-hidden />}
            Save menu
          </Button>
        </div>
      </div>

      {items.length === 0 ? (
        <div className="rounded-xl border border-dashed bg-background p-10 text-center text-sm text-muted-foreground">
          This menu is empty. Add a link to get started.
        </div>
      ) : (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={items.map((i) => i.key)} strategy={verticalListSortingStrategy}>
            <ol className="grid gap-2" aria-label={`${navigationLabels[menuKey].name} links`}>
              {items.map((item, index) => (
                <NavRow
                  key={item.key}
                  item={item}
                  index={index}
                  count={items.length}
                  pages={pages}
                  nested={nested}
                  canIndent={nested && index > 0}
                  expanded={expanded === item.key}
                  error={errors[item.key] ? clientErrors[item.key] : undefined}
                  onToggle={() => setExpanded((k) => (k === item.key ? null : item.key))}
                  onChange={(patch) => update(item.key, patch)}
                  onMove={(to) => move(item.key, to)}
                  onDelete={() => setToDelete(item.key)}
                />
              ))}
            </ol>
          </SortableContext>
        </DndContext>
      )}

      <ConfirmDialog
        open={!!toDelete}
        onOpenChange={(o) => !o && setToDelete(null)}
        title="Remove this link?"
        description="Nested links under it move up one level. The change goes live when you save the menu."
        confirmLabel="Remove link"
        destructive
        onConfirm={() => {
          setItems((prev) => {
            const i = prev.findIndex((x) => x.key === toDelete);
            if (i < 0) return prev;
            const next = prev.filter((x) => x.key !== toDelete);
            // Children of a removed top-level item become top-level items.
            if (prev[i]!.depth === 0)
              for (let j = i; j < next.length && next[j]!.depth === 1; j++)
                next[j] = { ...next[j]!, depth: 0 };
            return next;
          });
          setToDelete(null);
        }}
      />
    </div>
  );
}

function NavRow({
  item,
  index,
  count,
  pages,
  nested,
  canIndent,
  expanded,
  error,
  onToggle,
  onChange,
  onMove,
  onDelete,
}: {
  item: NavEditorItem;
  index: number;
  count: number;
  pages: PageOption[];
  nested: boolean;
  canIndent: boolean;
  expanded: boolean;
  error?: string;
  onToggle: () => void;
  onChange: (patch: Partial<NavEditorItem>) => void;
  onMove: (to: number) => void;
  onDelete: () => void;
}) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } =
    useSortable({ id: item.key });
  const id = `nav-${item.key}`;
  const label = item.label.it || "New link";
  const target =
    item.linkType === "PAGE"
      ? (pages.find((p) => p.id === item.pageId)?.title ?? "No page chosen")
      : item.linkType === "ROUTE"
        ? (routeKeyLabels[item.routeKey ?? ""] ?? "No section chosen")
        : item.href || "No address";
  const page = item.linkType === "PAGE" ? pages.find((p) => p.id === item.pageId) : undefined;

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        "rounded-xl border bg-background",
        item.depth === 1 && "ml-6 sm:ml-10",
        isDragging && "z-10 shadow-lg",
        error && "border-destructive",
      )}
    >
      <div className="flex items-center gap-2 p-2">
        <button
          type="button"
          ref={setActivatorNodeRef}
          {...attributes}
          {...listeners}
          aria-label={`Drag to reorder: ${label}`}
          className="flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50"
        >
          <GripVertical className="size-4" aria-hidden />
        </button>
        <button
          type="button"
          onClick={onToggle}
          aria-expanded={expanded}
          aria-controls={`${id}-panel`}
          className="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
          {expanded ? (
            <ChevronDown className="size-4 shrink-0" aria-hidden />
          ) : (
            <ChevronRight className="size-4 shrink-0" aria-hidden />
          )}
          <span className="min-w-0">
            <span className="flex flex-wrap items-center gap-2">
              <span className="font-medium">{label}</span>
              {item.label.en ? (
                <span className="text-xs text-muted-foreground">EN: {item.label.en}</span>
              ) : (
                <Badge variant="outline">EN missing</Badge>
              )}
              {!item.isVisible ? (
                <Badge variant="secondary" className="gap-1">
                  <EyeOff className="size-3" aria-hidden /> Hidden
                </Badge>
              ) : null}
              {page && !page.isPublished ? <Badge variant="outline">Page not live</Badge> : null}
            </span>
            <span className="block truncate text-xs text-muted-foreground">→ {target}</span>
            {error ? <span className="block text-xs text-destructive">{error}</span> : null}
          </span>
        </button>
        <div className="flex shrink-0 gap-0.5">
          {nested ? (
            item.depth === 0 ? (
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                disabled={!canIndent}
                onClick={() => onChange({ depth: 1 })}
                aria-label={`Nest ${label} under the previous link`}
              >
                <IndentIncrease aria-hidden />
              </Button>
            ) : (
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                onClick={() => onChange({ depth: 0 })}
                aria-label={`Move ${label} to the top level`}
              >
                <IndentDecrease aria-hidden />
              </Button>
            )
          ) : null}
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            disabled={index === 0}
            onClick={() => onMove(index - 1)}
            aria-label={`Move ${label} up`}
          >
            <ArrowUp aria-hidden />
          </Button>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            disabled={index === count - 1}
            onClick={() => onMove(index + 1)}
            aria-label={`Move ${label} down`}
          >
            <ArrowDown aria-hidden />
          </Button>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            className="text-destructive"
            onClick={onDelete}
            aria-label={`Remove ${label}`}
          >
            <Trash2 aria-hidden />
          </Button>
        </div>
      </div>
      {expanded ? (
        <div id={`${id}-panel`} className="grid gap-4 border-t p-4 sm:grid-cols-2">
          <div className="grid gap-1.5">
            <Label htmlFor={`${id}-it`}>Label (Italian)</Label>
            <Input
              id={`${id}-it`}
              value={item.label.it}
              maxLength={60}
              onChange={(e) => onChange({ label: { ...item.label, it: e.target.value } })}
            />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`${id}-en`}>Label (English)</Label>
            <Input
              id={`${id}-en`}
              value={item.label.en ?? ""}
              maxLength={60}
              placeholder="Falls back to Italian"
              onChange={(e) => onChange({ label: { ...item.label, en: e.target.value } })}
            />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor={`${id}-type`}>Links to</Label>
            <Select
              value={item.linkType}
              onValueChange={(v) =>
                onChange({
                  linkType: v as NavEditorItem["linkType"],
                  pageId: undefined,
                  routeKey: undefined,
                  href: undefined,
                })
              }
            >
              <SelectTrigger id={`${id}-type`} className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(linkTypeLabels).map(([v, l]) => (
                  <SelectItem key={v} value={v}>
                    {l}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid gap-1.5">
            {item.linkType === "PAGE" ? (
              <>
                <Label htmlFor={`${id}-target`}>Page</Label>
                <Select value={item.pageId ?? ""} onValueChange={(v) => onChange({ pageId: v })}>
                  <SelectTrigger id={`${id}-target`} className="w-full">
                    <SelectValue placeholder="Choose a page…" />
                  </SelectTrigger>
                  <SelectContent>
                    {pages.map((p) => (
                      <SelectItem key={p.id} value={p.id}>
                        {p.title}
                        {p.isPublished ? "" : " (not live)"}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </>
            ) : item.linkType === "ROUTE" ? (
              <>
                <Label htmlFor={`${id}-target`}>Section</Label>
                <Select value={item.routeKey ?? ""} onValueChange={(v) => onChange({ routeKey: v })}>
                  <SelectTrigger id={`${id}-target`} className="w-full">
                    <SelectValue placeholder="Choose…" />
                  </SelectTrigger>
                  <SelectContent>
                    {routeKeys.map((k) => (
                      <SelectItem key={k} value={k}>
                        {routeKeyLabels[k] ?? k}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </>
            ) : (
              <>
                <Label htmlFor={`${id}-target`}>
                  {item.linkType === "EXTERNAL" ? "Web address" : "Path"}
                </Label>
                <Input
                  id={`${id}-target`}
                  value={item.href ?? ""}
                  maxLength={500}
                  inputMode="url"
                  placeholder={item.linkType === "EXTERNAL" ? "https://…" : "/projects or /chi-siamo#team"}
                  onChange={(e) => onChange({ href: e.target.value })}
                />
              </>
            )}
          </div>
          <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
            <Label htmlFor={`${id}-visible`}>Show in menu</Label>
            <Switch
              id={`${id}-visible`}
              checked={item.isVisible}
              onCheckedChange={(c) => onChange({ isVisible: c })}
            />
          </div>
          <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
            <Label htmlFor={`${id}-blank`}>Open in a new tab</Label>
            <Switch
              id={`${id}-blank`}
              checked={!!item.openInNewTab}
              onCheckedChange={(c) => onChange({ openInNewTab: c })}
            />
          </div>
        </div>
      ) : null}
    </li>
  );
}
