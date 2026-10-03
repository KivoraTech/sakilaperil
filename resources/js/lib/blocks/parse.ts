import { z } from "zod";
import { blockRegistry, getBlockDefinition, isBlockType, type BlockType, type ParsedBlock } from "./registry";

/** Shape of a stored section (PageSection row or an entry of PageRevision.snapshot.sections). */
export type StoredSection = {
  id: string;
  type: string;
  schemaVersion: number;
  isVisible?: boolean;
  content: unknown;
  settings?: unknown;
};

export type SectionParseResult =
  | { ok: true; block: ParsedBlock }
  | {
      ok: false;
      id: string;
      type: string;
      reason: "UNKNOWN_TYPE" | "FUTURE_VERSION" | "MIGRATION_FAILED" | "INVALID";
      issues?: z.core.$ZodIssue[];
    };

/** Upgrade stored data to the block's current version. Throws if a migration is missing. */
export function migrateSection(
  type: BlockType,
  fromVersion: number,
  raw: { content: unknown; settings: unknown },
) {
  const def = getBlockDefinition(type);
  let data = raw;
  for (let v = fromVersion; v < def.version; v++) {
    if (!def.migrate) throw new Error(`Block "${type}" has no migration from v${v}`);
    data = def.migrate(v, data);
  }
  return data;
}

/** Validate + migrate one stored section. Never throws. */
export function parseSection(section: StoredSection): SectionParseResult {
  const { id, type } = section;
  if (!isBlockType(type)) return { ok: false, id, type, reason: "UNKNOWN_TYPE" };
  const def = getBlockDefinition(type);
  if (section.schemaVersion > def.version) return { ok: false, id, type, reason: "FUTURE_VERSION" };

  let raw: { content: unknown; settings: unknown };
  try {
    raw = migrateSection(type, section.schemaVersion, {
      content: section.content,
      settings: section.settings ?? {},
    });
  } catch {
    return { ok: false, id, type, reason: "MIGRATION_FAILED" };
  }

  const content = def.content.safeParse(raw.content);
  const settings = def.settings.safeParse(raw.settings ?? {});
  if (!content.success || !settings.success) {
    return {
      ok: false,
      id,
      type,
      reason: "INVALID",
      issues: [...(content.error?.issues ?? []), ...(settings.error?.issues ?? [])],
    };
  }
  return { ok: true, block: { id, type, content: content.data, settings: settings.data } as ParsedBlock };
}

/**
 * Validate a list of sections for rendering: hidden sections are dropped, invalid ones
 * are reported in `errors` (and skipped by the public renderer — fail safely).
 */
export function parseSections(sections: StoredSection[]) {
  const blocks: ParsedBlock[] = [];
  const errors: Extract<SectionParseResult, { ok: false }>[] = [];
  for (const section of sections) {
    if (section.isVisible === false) continue;
    const result = parseSection(section);
    if (result.ok) blocks.push(result.block);
    else errors.push(result);
  }
  return { blocks, errors };
}

/**
 * Validate content+settings for a SAVE/PUBLISH (server actions). Returns Zod issues so the
 * builder can highlight fields.
 */
export function validateBlockInput(type: string, content: unknown, settings: unknown) {
  if (!isBlockType(type))
    return { success: false as const, issues: [{ path: ["type"], message: "Unknown block type" }] };
  const def = blockRegistry[type];
  const c = def.content.safeParse(content);
  const s = def.settings.safeParse(settings ?? {});
  if (c.success && s.success)
    return { success: true as const, content: c.data, settings: s.data, version: def.version };
  return {
    success: false as const,
    issues: [
      ...(c.error?.issues.map((i) => ({ path: ["content", ...i.path.map(String)], message: i.message })) ??
        []),
      ...(s.error?.issues.map((i) => ({ path: ["settings", ...i.path.map(String)], message: i.message })) ??
        []),
    ],
  };
}

/** Walk any JSON value and collect every `assetId` string (for batched media resolution). */
export function collectAssetIds(value: unknown, into: Set<string> = new Set()): Set<string> {
  if (Array.isArray(value)) {
    for (const v of value) collectAssetIds(v, into);
  } else if (value && typeof value === "object") {
    for (const [k, v] of Object.entries(value)) {
      if (k === "assetId" && typeof v === "string" && v) into.add(v);
      else collectAssetIds(v, into);
    }
  }
  return into;
}
