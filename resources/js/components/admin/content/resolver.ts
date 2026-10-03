import { zodResolver } from "@hookform/resolvers/zod";
import type { FieldValues, Resolver } from "react-hook-form";
import type { z } from "zod";

/**
 * zodResolver typed on the schema OUTPUT. Our form values are already in output shape
 * (defaults are filled in by lib/content/defaults.ts), so input/output differences such as
 * `.default([])` in the rich-text schema do not matter to the form.
 */
export function resolverFor<S extends z.ZodType<FieldValues>>(schema: S): Resolver<z.output<S>> {
  return zodResolver(schema as never) as unknown as Resolver<z.output<S>>;
}
