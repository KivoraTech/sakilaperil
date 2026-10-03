import { usePage } from "@inertiajs/react";
import { useEffect } from "react";
import { toast } from "sonner";
import type { SharedProps } from "@/types/inertia";

/** Shows `session()->flash('success'|'error', …)` from Laravel controllers as toasts. */
export function FlashToasts() {
  const { flash } = usePage<SharedProps>().props;
  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash?.success, flash?.error, flash?.id]);
  return null;
}
