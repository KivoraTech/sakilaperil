import { blockRegistry, getBlockDefinition, isBlockType, parseSection } from "@/lib/blocks";
import { buildPageSnapshot, type PageSnapshot } from "./snapshot";

/**
 * Pure publish/validation logic for the page builder (no DB, unit-tested).
 * The server action loads the draft rows, calls `preparePublish()` and, if it succeeds, stores
 * `snapshot` as an immutable PageRevision (ADR-003).
 */

export type DraftRow = {
  id: string;
  type: string;
  schemaVersion: number;
  isVisible: boolean;
  position: number;
  content: unknown;
  settings: unknown;
};

export type SectionIssue = {
  sectionId: string;
  type: string;
  /** 1-based position shown to editors ("Section 3 · Hero"). */
  position: number;
  messages: string[];
};

const reasonText = {
  UNKNOWN_TYPE: "This block type no longer exists",
  FUTURE_VERSION: "This block was saved by a newer version of the CMS",
  MIGRATION_FAILED: "This block could not be upgraded to the current version",
  INVALID: "Some fields are missing or invalid",
} as const;

export function blockLabel(type: string): string {
  return isBlockType(type) ? blockRegistry[type].label : type;
}

/** Validate every section of a draft. Hidden sections are checked too only for type existence. */
export function validateDraftSections(rows: DraftRow[]): SectionIssue[] {
  const sorted = [...rows].sort((a, b) => a.position - b.position);
  const issues: SectionIssue[] = [];
  const singletonSeen = new Set<string>();
  sorted.forEach((row, index) => {
    const position = index + 1;
    if (!isBlockType(row.type)) {
      issues.push({ sectionId: row.id, type: row.type, position, messages: [reasonText.UNKNOWN_TYPE] });
      return;
    }
    if (!row.isVisible) return;
    const def = getBlockDefinition(row.type);
    if (def.singleton) {
      if (singletonSeen.has(row.type)) {
        issues.push({
          sectionId: row.id,
          type: row.type,
          position,
          messages: [`Only one “${def.label}” block is allowed per page`],
        });
        return;
      }
      singletonSeen.add(row.type);
    }
    const parsed = parseSection(row);
    if (!parsed.ok) {
      const details =
        parsed.issues?.slice(0, 5).map((i) => `${i.path.map(String).join(".") || "block"}: ${i.message}`) ??
        [];
      issues.push({
        sectionId: row.id,
        type: row.type,
        position,
        messages: [reasonText[parsed.reason], ...details],
      });
    }
  });
  return issues;
}

export type PreparedPublish =
  { ok: true; snapshot: PageSnapshot; sectionCount: number } | { ok: false; issues: SectionIssue[] };

/**
 * Validate the draft and build the snapshot. Visible sections are stored migrated to the
 * CURRENT block version with Zod-parsed (normalised, defaults applied) content, so the public
 * renderer never has to migrate published data.
 */
export function preparePublish(input: {
  title: string;
  seoTitle?: string | null;
  seoDescription?: string | null;
  ogImageId?: string | null;
  noIndex?: boolean;
  sections: DraftRow[];
}): PreparedPublish {
  const issues = validateDraftSections(input.sections);
  if (issues.length > 0) return { ok: false, issues };

  const normalised: DraftRow[] = [];
  for (const row of input.sections) {
    if (!row.isVisible || !isBlockType(row.type)) continue;
    const parsed = parseSection(row);
    if (!parsed.ok) return { ok: false, issues: validateDraftSections(input.sections) };
    normalised.push({
      ...row,
      schemaVersion: blockRegistry[row.type].version,
      content: parsed.block.content,
      settings: parsed.block.settings,
    });
  }

  const snapshot = buildPageSnapshot({ ...input, sections: normalised });
  return { ok: true, snapshot, sectionCount: snapshot.sections.length };
}

/** Human summary for toasts / action errors. */
export function summariseIssues(issues: SectionIssue[]): string {
  if (issues.length === 0) return "";
  const first = issues[0]!;
  const more = issues.length > 1 ? ` (+${issues.length - 1} more)` : "";
  return `Section ${first.position} · ${blockLabel(first.type)}: ${first.messages.join(" — ")}${more}`;
}
