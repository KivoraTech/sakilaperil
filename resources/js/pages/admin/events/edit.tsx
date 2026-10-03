import { Head } from "@inertiajs/react";
import { EventForm } from "@/components/admin/content/event-form";
import type { EditorPermsProps } from "@/lib/content/admin-types";
import type { ContentStatusValue } from "@/lib/content/common";
import { emptyEvent } from "@/lib/content/defaults";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { EventFormValues } from "@/lib/content/schemas";

type Props = {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: EventFormValues | null;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPermsProps;
  publicSlug: string | null;
};

/** Port of app/admin/(shell)/events/{new,[id]}/page.tsx (data from EventsController::{create,edit}). */
export default function EventEditPage(props: Props) {
  return (
    <>
      <Head title={props.id ? "Edit event" : "New event"} />
      <EventForm
        key={props.id ?? "new"}
        id={props.id}
        status={props.status}
        initialValues={props.initialValues ?? emptyEvent()}
        coverAsset={props.coverAsset}
        perms={props.perms}
        publicSlug={props.publicSlug}
      />
    </>
  );
}
