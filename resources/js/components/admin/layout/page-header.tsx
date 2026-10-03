/** Consistent page heading for admin sections. */
export function AdminPageHeader({
  title,
  description,
  actions,
}: {
  title: string;
  description?: string;
  actions?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
      </div>
      {actions ? <div className="flex gap-2">{actions}</div> : null}
    </div>
  );
}

/** Placeholder body for a section that is not implemented yet. */
export function ComingSoon({ owner }: { owner: string }) {
  return (
    <div className="rounded-xl border border-dashed bg-background p-10 text-center text-sm text-muted-foreground">
      This section is being built ({owner}).
    </div>
  );
}
