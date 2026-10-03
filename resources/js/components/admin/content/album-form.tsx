"use client";

import { useState } from "react";
import { useFieldArray, useForm, useFormContext, useWatch } from "react-hook-form";
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
  rectSortingStrategy,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical, ImagePlus, Star, Trash2, Video } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MediaPicker } from "@/components/admin/media/MediaPicker";
import { MediaUploader } from "@/components/admin/media/MediaUploader";
import { deleteAlbumAction, saveAlbumAction, setAlbumStatusAction } from "@/lib/actions/gallery";
import type { ContentStatusValue } from "@/lib/content/common";
import type { MediaAssetDTO } from "@/lib/content/media";
import { albumFormSchema, type AlbumFormValues, type GalleryItemValues } from "@/lib/content/schemas";
import { parseVideoUrl, videoThumbnailUrl } from "@/lib/richtext/video";
import { ContentEditor, EditorSection, type EditorPerms } from "./content-editor";
import { DateTimeField, MediaFormField, SelectField, SlugField, TextField } from "./fields";
import { resolverFor } from "./resolver";
import { TranslationTabs } from "./translation-tabs";

const newKey = () => `new-${Math.random().toString(36).slice(2, 10)}`;

const photoItem = (a: MediaAssetDTO): GalleryItemValues => ({
  key: newKey(),
  type: "PHOTO",
  mediaAssetId: a.id,
  videoProvider: null,
  videoId: null,
  captionIt: a.caption.it,
  captionEn: a.caption.en,
  previewUrl: a.url,
});

export function AlbumForm({
  id,
  status,
  initialValues,
  coverAsset,
  perms,
  publicSlug,
  projects,
}: {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: AlbumFormValues;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPerms;
  publicSlug: string | null;
  projects: { value: string; label: string }[];
}) {
  const form = useForm<AlbumFormValues>({
    resolver: resolverFor(albumFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });
  const title = useWatch({ control: form.control, name: "translations.it.title" });

  return (
    <ContentEditor
      form={form}
      id={id}
      kindLabel="album"
      title={id ? title || "Untitled album" : "New album"}
      basePath="/admin/gallery"
      status={status}
      publicUrl={publicSlug ? `/it/gallery/${publicSlug}` : null}
      perms={perms}
      onSave={(values, intent) => saveAlbumAction(id, values, intent)}
      onStatus={id ? (s) => setAlbumStatusAction(id, s) : undefined}
      onDelete={id ? () => deleteAlbumAction(id) : undefined}
      main={
        <>
          <TranslationTabs>
            {(l) => (
              <EditorSection title="Album">
                <TextField name={`translations.${l}.title`} label="Title" maxLength={200} required />
                <SlugField
                  name={`translations.${l}.slug`}
                  sourceName={`translations.${l}.title`}
                  prefix={`/${l}/gallery/`}
                  autoFollow={!initialValues.translations[l].slug}
                />
                <TextField
                  name={`translations.${l}.description`}
                  label="Description"
                  multiline
                  rows={3}
                  maxLength={1000}
                />
              </EditorSection>
            )}
          </TranslationTabs>
          <GalleryItemsEditor />
        </>
      }
      sidebar={
        <>
          <EditorSection title="Cover image" description="Defaults to the first photo when empty.">
            <MediaFormField name="coverImageId" label="Image" initialAsset={coverAsset} />
          </EditorSection>
          <EditorSection title="Details">
            <SelectField
              name="projectId"
              label="Project"
              options={projects}
              emptyLabel="Not linked"
              nullable
            />
            <DateTimeField name="takenAt" label="Photos taken on" dateOnly />
            <TextField
              name="position"
              label="Order"
              type="number"
              description="Lower numbers are listed first."
            />
          </EditorSection>
          <EditorSection title="Publishing">
            <DateTimeField name="publishedAt" label="Publication date" />
          </EditorSection>
        </>
      }
    />
  );
}

function GalleryItemsEditor() {
  const { control, setValue, formState } = useFormContext<AlbumFormValues>();
  const { fields, append, remove, move } = useFieldArray({ control, name: "items", keyName: "_rhfKey" });
  const [pickerOpen, setPickerOpen] = useState(false);
  const [videoOpen, setVideoOpen] = useState(false);
  const [showUploader, setShowUploader] = useState(false);
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );
  const onDragEnd = ({ active, over }: DragEndEvent) => {
    if (!over || active.id === over.id) return;
    const from = fields.findIndex((f) => f.key === active.id);
    const to = fields.findIndex((f) => f.key === over.id);
    if (from >= 0 && to >= 0) move(from, to);
  };
  const itemsError = formState.errors.items;

  return (
    <EditorSection
      title={`Photos & videos (${fields.length})`}
      description="Drag to reorder (or focus the handle and use the arrow keys). Photos need alt text before publishing."
    >
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={() => setShowUploader((v) => !v)}>
          <ImagePlus aria-hidden /> Upload photos
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={() => setPickerOpen(true)}>
          <ImagePlus aria-hidden /> Add from library
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={() => setVideoOpen(true)}>
          <Video aria-hidden /> Add YouTube/Vimeo video
        </Button>
      </div>
      {showUploader ? (
        <MediaUploader
          accept="IMAGE"
          onUploaded={(a) => append(photoItem(a))}
          onAllDone={(u) =>
            u.length && toast.success(`${u.length} photo(s) added. Remember to add alt text.`)
          }
        />
      ) : null}
      {itemsError && !Array.isArray(itemsError) ? (
        <p role="alert" className="text-sm text-destructive">
          {String(itemsError.message ?? "")}
        </p>
      ) : null}
      {fields.length ? (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
          <SortableContext items={fields.map((f) => f.key)} strategy={rectSortingStrategy}>
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Album items">
              {fields.map((f, index) => (
                <SortableItem
                  key={f.key}
                  item={f}
                  index={index}
                  onRemove={() => remove(index)}
                  onCover={() => {
                    if (f.mediaAssetId) {
                      setValue("coverImageId", f.mediaAssetId, { shouldDirty: true });
                      toast.success("Cover image set");
                    }
                  }}
                />
              ))}
            </ul>
          </SortableContext>
        </DndContext>
      ) : (
        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
          No photos or videos yet.
        </p>
      )}
      <MediaPicker
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        accept="IMAGE"
        title="Add a photo"
        onSelect={(a) => append(photoItem(a))}
      />
      <AddVideoDialog
        open={videoOpen}
        onOpenChange={setVideoOpen}
        onAdd={(provider, videoId) =>
          append({
            key: newKey(),
            type: "VIDEO",
            mediaAssetId: null,
            videoProvider: provider,
            videoId,
            captionIt: "",
            captionEn: "",
            previewUrl: videoThumbnailUrl(provider, videoId),
          })
        }
      />
    </EditorSection>
  );
}

function SortableItem({
  item,
  index,
  onRemove,
  onCover,
}: {
  item: GalleryItemValues;
  index: number;
  onRemove: () => void;
  onCover: () => void;
}) {
  const { register, formState } = useFormContext<AlbumFormValues>();
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } =
    useSortable({
      id: item.key,
    });
  const err = formState.errors.items?.[index];
  const label =
    item.type === "VIDEO" ? `${item.videoProvider === "vimeo" ? "Vimeo" : "YouTube"} video` : "Photo";
  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={`grid gap-2 rounded-lg border bg-background p-2 ${isDragging ? "z-10 shadow-lg ring-2 ring-primary" : ""}`}
    >
      <div className="relative aspect-video overflow-hidden rounded-md bg-muted">
        {item.previewUrl ? (
          <img src={item.previewUrl} alt="" className="h-full w-full object-cover" loading="lazy" />
        ) : (
          <div className="grid h-full place-items-center text-xs text-muted-foreground">{label}</div>
        )}
        <span className="absolute top-1 left-1 rounded bg-black/60 px-1.5 text-[11px] font-medium text-white">
          {index + 1} · {label}
        </span>
      </div>
      <div className="flex items-center gap-1">
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          ref={setActivatorNodeRef}
          aria-label={`Reorder item ${index + 1}`}
          className="cursor-grab touch-none"
          {...attributes}
          {...listeners}
        >
          <GripVertical />
        </Button>
        {item.type === "PHOTO" ? (
          <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            aria-label={`Use item ${index + 1} as cover`}
            title="Use as cover"
            onClick={onCover}
          >
            <Star />
          </Button>
        ) : null}
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={`Remove item ${index + 1}`}
          className="ml-auto text-destructive"
          onClick={onRemove}
        >
          <Trash2 />
        </Button>
      </div>
      <label className="grid gap-1 text-xs">
        <span className="text-muted-foreground">Caption (IT)</span>
        <Input {...register(`items.${index}.captionIt`)} maxLength={300} className="h-7 text-sm" />
      </label>
      <label className="grid gap-1 text-xs">
        <span className="text-muted-foreground">Caption (EN)</span>
        <Input {...register(`items.${index}.captionEn`)} maxLength={300} className="h-7 text-sm" />
      </label>
      {err ? (
        <p role="alert" className="text-xs text-destructive">
          {err.mediaAssetId?.message ?? err.videoId?.message ?? "Invalid item"}
        </p>
      ) : null}
    </li>
  );
}

function AddVideoDialog({
  open,
  onOpenChange,
  onAdd,
}: {
  open: boolean;
  onOpenChange: (o: boolean) => void;
  onAdd: (provider: "youtube" | "vimeo", id: string) => void;
}) {
  const [url, setUrl] = useState("");
  const [error, setError] = useState<string | null>(null);
  const submit = () => {
    const parsed = parseVideoUrl(url);
    if (!parsed) return setError("Paste a YouTube or Vimeo link.");
    onAdd(parsed.provider, parsed.id);
    setUrl("");
    setError(null);
    onOpenChange(false);
  };
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Add a video</DialogTitle>
          <DialogDescription>
            Videos stay on YouTube or Vimeo and are embedded in the album.
          </DialogDescription>
        </DialogHeader>
        <div className="grid gap-2">
          <Label htmlFor="album-video-url">Video link</Label>
          <Input
            id="album-video-url"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                submit();
              }
            }}
            placeholder="https://www.youtube.com/watch?v=…"
            aria-invalid={!!error}
          />
          {error ? (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          ) : null}
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={submit}>
            Add video
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
