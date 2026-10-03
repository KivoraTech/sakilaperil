/** Types of the audit log data (reference: lib/queries/admin/audit.ts; built by App\Queries\Admin\AuditQuery). */
export const AUDIT_PER_PAGE = 50;

export type AuditParams = {
  user?: string;
  action?: string;
  entity?: string;
  q?: string;
  page: number;
};

export type AuditData = {
  total: number;
  page: number;
  pageCount: number;
  rows: {
    id: string;
    createdAt: string;
    action: string;
    entityType: string | null;
    entityId: string | null;
    metadata: unknown;
    ipAddress: string | null;
    userAgent: string | null;
    userId: string | null;
    user: string | null;
    userEmail: string | null;
  }[];
  users: { id: string; name: string }[];
  entityTypes: string[];
};
