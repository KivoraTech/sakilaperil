/** Settings actions (reference: lib/actions/settings.ts). */
import { callAction } from "./client";

export const updateSettingsAction = (input: unknown) => callAction<null>("settings/update", input);
