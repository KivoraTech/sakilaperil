/** Types of the dashboard data (reference: lib/queries/admin/dashboard.ts; built by App\Queries\Admin\DashboardQuery). */
import type { Role } from "@/lib/permissions";

export type ChecklistItem = { id: string; label: string; hint: string; href: string; done: boolean };

export type DraftItem = {
  key: string;
  kind: "Page" | "News story" | "Project" | "Event";
  title: string;
  href: string;
  updatedAt: string;
  isLive: boolean;
};

export type DashboardData = {
  user: { id: string; name: string; email: string; role: Role };
  pages: { published: number; draft: number; withChanges: number };
  news: {
    published: number;
    drafts: number;
    recent: { id: string; title: string; status: string; updatedAt: string }[];
  };
  events: { id: string; title: string; status: string; startsAt: string }[];
  mediaCount: number;
  projectsActive: number;
  submissions: null | {
    contact: number;
    volunteer: number;
    membership: number;
    partner: number;
    newsletter: number;
    total: number;
  };
  donations: null | {
    recent: {
      id: string;
      reference: string;
      provider: string;
      amount: number;
      currency: string;
      donorName: string | null;
      isAnonymous: boolean;
      isTestMode: boolean;
      completedAt: string | null;
      createdAt: string;
    }[];
    month: { currency: string; amount: number; count: number }[];
    pendingBank: number;
  };
  activity:
    | null
    | {
        id: string;
        action: string;
        entityType: string | null;
        entityId: string | null;
        description: string;
        user: string | null;
        createdAt: string;
      }[];
  todoCount: number | null;
  drafts: DraftItem[];
  checklist: ChecklistItem[];
  homePageId: string | null;
  /** Keys of required system pages that do not exist yet (only for users who may create pages). */
  missingSystemPages: string[];
};
