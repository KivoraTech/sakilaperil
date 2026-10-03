import { Head } from "@inertiajs/react";
import { NewsForm } from "@/components/admin/content/news-form";
import type { EditorPermsProps, SelectOption } from "@/lib/content/admin-types";
import type { ContentStatusValue } from "@/lib/content/common";
import { emptyNews } from "@/lib/content/defaults";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { NewsFormValues } from "@/lib/content/schemas";

type Props = {
  id: string | null;
  status: ContentStatusValue | null;
  /** null for "new": the empty form is built here (emptyNews, like the reference's new/page.tsx). */
  initialValues: NewsFormValues | null;
  /** Signed-in user's id, preset as the author of a new post. */
  authorId: string | null;
  coverAsset: MediaAssetDTO | null;
  options: { categories: SelectOption[]; projects: SelectOption[]; authors: SelectOption[] };
  perms: EditorPermsProps;
  canManageCategories: boolean;
  publicSlug: string | null;
};

/** Port of app/admin/(shell)/news/{new,[id]}/page.tsx (data from NewsController::{create,edit}). */
export default function NewsEditPage(props: Props) {
  const initialValues = props.initialValues ?? { ...emptyNews(), authorId: props.authorId };
  return (
    <>
      <Head title={props.id ? "Edit post" : "New post"} />
      <NewsForm
        key={props.id ?? "new"}
        id={props.id}
        status={props.status}
        initialValues={initialValues}
        coverAsset={props.coverAsset}
        options={props.options}
        perms={props.perms}
        canManageCategories={props.canManageCategories}
        publicSlug={props.publicSlug}
      />
    </>
  );
}
