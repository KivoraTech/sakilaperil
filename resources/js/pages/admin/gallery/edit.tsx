import { Head } from "@inertiajs/react";
import { AlbumForm } from "@/components/admin/content/album-form";
import type { EditorPermsProps, SelectOption } from "@/lib/content/admin-types";
import type { ContentStatusValue } from "@/lib/content/common";
import { emptyAlbum } from "@/lib/content/defaults";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { AlbumFormValues } from "@/lib/content/schemas";

type Props = {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: AlbumFormValues | null;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPermsProps;
  publicSlug: string | null;
  projects: SelectOption[];
};

/** Port of app/admin/(shell)/gallery/{new,[id]}/page.tsx (data from GalleryController::{create,edit}). */
export default function AlbumEditPage(props: Props) {
  return (
    <>
      <Head title={props.id ? "Edit album" : "New album"} />
      <AlbumForm
        key={props.id ?? "new"}
        id={props.id}
        status={props.status}
        initialValues={props.initialValues ?? emptyAlbum()}
        coverAsset={props.coverAsset}
        perms={props.perms}
        publicSlug={props.publicSlug}
        projects={props.projects}
      />
    </>
  );
}
