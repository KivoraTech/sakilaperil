import { Head } from "@inertiajs/react";
import { ProjectForm } from "@/components/admin/content/project-form";
import type { EditorPermsProps } from "@/lib/content/admin-types";
import type { ContentStatusValue } from "@/lib/content/common";
import { emptyProject } from "@/lib/content/defaults";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { ProjectFormValues } from "@/lib/content/schemas";

type Props = {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: ProjectFormValues | null;
  coverAsset: MediaAssetDTO | null;
  perms: EditorPermsProps;
  publicSlug: string | null;
  albums: { id: string; title: string; status: string }[];
};

/** Port of app/admin/(shell)/projects/{new,[id]}/page.tsx (data from ProjectsController::{create,edit}). */
export default function ProjectEditPage(props: Props) {
  return (
    <>
      <Head title={props.id ? "Edit project" : "New project"} />
      <ProjectForm
        key={props.id ?? "new"}
        id={props.id}
        status={props.status}
        initialValues={props.initialValues ?? emptyProject()}
        coverAsset={props.coverAsset}
        perms={props.perms}
        publicSlug={props.publicSlug}
        albums={props.id ? props.albums : undefined}
      />
    </>
  );
}
