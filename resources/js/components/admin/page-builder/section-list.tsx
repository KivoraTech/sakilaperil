"use client";

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
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  Copy,
  Eye,
  EyeOff,
  GripVertical,
  MoreHorizontal,
  Pencil,
  Plus,
  Trash2,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { blockLabel } from "@/lib/pages/publish";
import { cn } from "@/lib/utils";
import { sectionSummary } from "@/components/admin/blocks/labels";

export type BuilderSection = {
  /** Stable client key (the DB id once saved). */
  key: string;
  id?: string;
  type: string;
  isVisible: boolean;
  content: Record<string, unknown>;
  settings: Record<string, unknown>;
  broken?: boolean;
};

export type SectionActions = {
  onEdit: (key: string) => void;
  onMove: (key: string, to: number) => void;
  onDuplicate: (key: string) => void;
  onToggleVisible: (key: string) => void;
  onDelete: (key: string) => void;
  onAddBelow: (index: number) => void;
};

export function SectionList({
  sections,
  problems,
  readOnly,
  duplicateDisabled,
  ...actions
}: SectionActions & {
  sections: BuilderSection[];
  /** key → short problem text for sections that are incomplete/invalid. */
  problems: Map<string, string>;
  readOnly?: boolean;
  duplicateDisabled: (s: BuilderSection) => boolean;
}) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const onDragEnd = (e: DragEndEvent) => {
    if (!e.over || e.active.id === e.over.id) return;
    const to = sections.findIndex((s) => s.key === e.over!.id);
    if (to >= 0) actions.onMove(String(e.active.id), to);
  };

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragEnd={onDragEnd}
      accessibility={{
        screenReaderInstructions: {
          draggable:
            "To pick up a section, press space or enter. Use the arrow keys to move it. Press space or enter again to drop it, or escape to cancel.",
        },
        announcements: {
          onDragStart: ({ active }) => `Picked up section ${indexOf(sections, active.id) + 1}.`,
          onDragOver: ({ over }) =>
            over ? `Over position ${indexOf(sections, over.id) + 1} of ${sections.length}.` : "",
          onDragEnd: ({ over }) =>
            over ? `Dropped at position ${indexOf(sections, over.id) + 1}.` : "Dropped.",
          onDragCancel: () => "Moving cancelled.",
        },
      }}
    >
      <SortableContext items={sections.map((s) => s.key)} strategy={verticalListSortingStrategy}>
        <ol className="grid gap-2" aria-label="Page sections">
          {sections.map((s, i) => (
            <SortableSection
              key={s.key}
              section={s}
              index={i}
              count={sections.length}
              problem={problems.get(s.key)}
              readOnly={readOnly}
              duplicateDisabled={duplicateDisabled(s)}
              {...actions}
            />
          ))}
        </ol>
      </SortableContext>
    </DndContext>
  );
}

function indexOf(sections: BuilderSection[], id: string | number) {
  return sections.findIndex((s) => s.key === id);
}

function SortableSection({
  section,
  index,
  count,
  problem,
  readOnly,
  duplicateDisabled,
  onEdit,
  onMove,
  onDuplicate,
  onToggleVisible,
  onDelete,
  onAddBelow,
}: SectionActions & {
  section: BuilderSection;
  index: number;
  count: number;
  problem?: string;
  readOnly?: boolean;
  duplicateDisabled: boolean;
}) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } =
    useSortable({
      id: section.key,
      disabled: readOnly,
    });
  const label = blockLabel(section.type);
  const summary = sectionSummary(section.content);
  const name = `${label}${summary ? ` — ${summary}` : ""}`;

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      data-testid="builder-section"
      data-type={section.type}
      className={cn(
        "group rounded-xl border bg-background shadow-xs transition-shadow",
        isDragging && "z-10 shadow-lg ring-2 ring-primary/40",
        !section.isVisible && "bg-muted/50",
        problem && "border-amber-400",
      )}
    >
      <div className="flex items-center gap-2 p-2 sm:p-3">
        <button
          type="button"
          ref={setActivatorNodeRef}
          {...attributes}
          {...listeners}
          disabled={readOnly}
          aria-label={`Drag to reorder: ${label}, position ${index + 1} of ${count}`}
          className="flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-muted focus-visible:ring-3 focus-visible:ring-ring/50 active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-40"
        >
          <GripVertical className="size-4" aria-hidden />
        </button>
        <span
          className="hidden w-6 shrink-0 text-center text-xs text-muted-foreground tabular-nums sm:block"
          aria-hidden
        >
          {index + 1}
        </span>
        <button
          type="button"
          onClick={() => onEdit(section.key)}
          className="grid min-w-0 flex-1 rounded-md px-1 py-0.5 text-left outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
          aria-label={`Edit section ${index + 1}: ${name}`}
          disabled={section.broken}
        >
          <span className="flex flex-wrap items-center gap-2">
            <span className="font-medium">{label}</span>
            {!section.isVisible ? (
              <Badge variant="secondary" className="gap-1">
                <EyeOff className="size-3" aria-hidden /> Hidden
              </Badge>
            ) : null}
            {problem ? (
              <Badge variant="outline" className="gap-1 border-amber-400 text-amber-800">
                <AlertTriangle className="size-3" aria-hidden /> Needs attention
              </Badge>
            ) : null}
          </span>
          {summary ? <span className="truncate text-sm text-muted-foreground">{summary}</span> : null}
          {problem ? <span className="truncate text-xs text-amber-800">{problem}</span> : null}
        </button>
        <div className="flex shrink-0 items-center gap-0.5">
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            onClick={() => onMove(section.key, index - 1)}
            disabled={readOnly || index === 0}
            aria-label={`Move ${label} up`}
          >
            <ArrowUp aria-hidden />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            onClick={() => onMove(section.key, index + 1)}
            disabled={readOnly || index === count - 1}
            aria-label={`Move ${label} down`}
          >
            <ArrowDown aria-hidden />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            className="hidden sm:inline-flex"
            onClick={() => onToggleVisible(section.key)}
            disabled={readOnly}
            aria-label={section.isVisible ? `Hide ${label}` : `Show ${label}`}
            aria-pressed={!section.isVisible}
          >
            {section.isVisible ? <Eye aria-hidden /> : <EyeOff aria-hidden />}
          </Button>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                aria-label={`More actions for ${label}`}
                disabled={readOnly}
              >
                <MoreHorizontal aria-hidden />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onSelect={() => onEdit(section.key)} disabled={section.broken}>
                <Pencil aria-hidden /> Edit
              </DropdownMenuItem>
              <DropdownMenuItem onSelect={() => onDuplicate(section.key)} disabled={duplicateDisabled}>
                <Copy aria-hidden /> Duplicate
              </DropdownMenuItem>
              <DropdownMenuItem onSelect={() => onToggleVisible(section.key)}>
                {section.isVisible ? <EyeOff aria-hidden /> : <Eye aria-hidden />}
                {section.isVisible ? "Hide on website" : "Show on website"}
              </DropdownMenuItem>
              <DropdownMenuItem onSelect={() => onAddBelow(index)}>
                <Plus aria-hidden /> Add section below
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem variant="destructive" onSelect={() => onDelete(section.key)}>
                <Trash2 aria-hidden /> Delete
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </li>
  );
}
