/** Navigation actions (reference: lib/actions/navigation.ts). */
import { callAction } from "./client";

export const saveNavigationAction = (input: unknown) =>
  callAction<{ count: number }>("navigation/save", input);
