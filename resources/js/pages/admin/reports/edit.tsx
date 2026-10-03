import { Head } from "@inertiajs/react";
import { ReportForm } from "@/components/admin/content/report-form";
import type { EditorPermsProps } from "@/lib/content/admin-types";
import type { ContentStatusValue } from "@/lib/content/common";
import { emptyReport } from "@/lib/content/defaults";
import type { MediaAssetDTO } from "@/lib/content/media";
import type { ReportFormValues } from "@/lib/content/schemas";

type Props = {
  id: string | null;
  status: ContentStatusValue | null;
  initialValues: ReportFormValues | null;
  assets: Record<string, MediaAssetDTO>;
  perms: EditorPermsProps;
};

/** Port of app/admin/(shell)/reports/{new,[id]}/page.tsx (data from ReportsController::{create,edit}). */
export default function ReportEditPage(props: Props) {
  return (
    <>
      <Head title={props.id ? "Edit report" : "New report"} />
      <ReportForm
        key={props.id ?? "new"}
        id={props.id}
        status={props.status}
        initialValues={props.initialValues ?? emptyReport()}
        assets={props.assets}
        perms={props.perms}
      />
    </>
  );
}
