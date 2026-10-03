import type { z } from "zod";
import { blockRegistry, type BlockType } from "@/lib/blocks/registry";
import { cleanBlock, describeBlock } from "./introspect";

export type BlockValue = { content: Record<string, unknown>; settings: Record<string, unknown> };

const describedCache = new Map<BlockType, ReturnType<typeof describeBlock>>();
export function describedBlock(type: BlockType) {
  let d = describedCache.get(type);
  if (!d) {
    d = describeBlock(blockRegistry[type]);
    describedCache.set(type, d);
  }
  return d;
}

/** Friendly editor-facing message for a Zod issue. */
export function issueMessage(issue: z.core.$ZodIssue): string {
  const last = issue.path[issue.path.length - 1];
  if (last === "assetId") return "Choose an image";
  switch (issue.code) {
    case "invalid_type":
      return issue.input === undefined || issue.input === "" ? "Required" : "Invalid value";
    case "too_small":
      if (issue.origin === "string")
        return Number(issue.minimum) <= 1 ? "Required" : `At least ${issue.minimum} characters`;
      if (issue.origin === "array") return `Add at least ${issue.minimum}`;
      return `Must be at least ${issue.minimum}`;
    case "too_big":
      if (issue.origin === "string") return `At most ${issue.maximum} characters`;
      if (issue.origin === "array") return `At most ${issue.maximum} items`;
      return `Must be at most ${issue.maximum}`;
    case "invalid_value":
      return "Choose one of the options";
    default:
      return issue.message;
  }
}

/**
 * Clean + validate a block's form value with the SAME Zod schemas the server uses.
 * Returns flat dot-path errors ("content.items.0.name") for React Hook Form.
 */
export function validateBlockValue(type: BlockType, value: BlockValue) {
  const def = blockRegistry[type];
  const cleaned = cleanBlock(describedBlock(type), value);
  const errors: Record<string, string> = {};
  const c = def.content.safeParse(cleaned.content);
  const s = def.settings.safeParse(cleaned.settings);
  for (const [prefix, result] of [
    ["content", c],
    ["settings", s],
  ] as const) {
    if (result.success) continue;
    for (const issue of result.error.issues) {
      const path = [prefix, ...issue.path.map(String)].join(".");
      if (!errors[path]) errors[path] = issueMessage(issue);
    }
  }
  return { ok: Object.keys(errors).length === 0, cleaned, errors };
}
