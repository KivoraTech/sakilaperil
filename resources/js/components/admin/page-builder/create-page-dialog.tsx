"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { Plus } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { createPageAction } from "@/lib/actions/pages";
import { slugify } from "@/lib/i18n/routes";
import { createPageSchema, type CreatePageInput } from "@/lib/pages/schemas";

/** `defaultOpen` lets links such as the dashboard's "Create a new page" (?new=1) open it directly. */
export function CreatePageDialog({ defaultOpen = false }: { defaultOpen?: boolean } = {}) {
  const router = useRouter();
  const [open, setOpen] = useState(defaultOpen);
  const [pending, startTransition] = useTransition();
  const [slugTouched, setSlugTouched] = useState({ it: false, en: false });
  const form = useForm<CreatePageInput>({
    resolver: zodResolver(createPageSchema),
    defaultValues: { title: "", slug: "", enTitle: "", enSlug: "" },
  });

  const onSubmit = form.handleSubmit((values) =>
    startTransition(async () => {
      const r = await createPageAction({
        ...values,
        enTitle: values.enTitle || undefined,
        enSlug: values.enSlug || undefined,
      });
      if (!r.ok) {
        for (const [k, v] of Object.entries(r.fieldErrors ?? {}))
          form.setError(k as keyof CreatePageInput, { message: v[0] });
        toast.error(r.message);
        return;
      }
      toast.success("Page created — add your first block");
      setOpen(false);
      form.reset();
      router.push(`/admin/pages/${r.data.id}`);
    }),
  );

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button>
          <Plus aria-hidden /> New page
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>New page</DialogTitle>
          <DialogDescription>
            Italian is required. You can add the English version now or later from the editor.
          </DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={onSubmit} className="grid gap-4" noValidate>
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Title (Italian)</FormLabel>
                  <FormControl>
                    <Input
                      {...field}
                      onChange={(e) => {
                        field.onChange(e);
                        if (!slugTouched.it) form.setValue("slug", slugify(e.target.value));
                      }}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="slug"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Address (Italian)</FormLabel>
                  <FormControl>
                    <Input
                      {...field}
                      onChange={(e) => {
                        setSlugTouched((s) => ({ ...s, it: true }));
                        field.onChange(e.target.value.toLowerCase());
                      }}
                    />
                  </FormControl>
                  <FormDescription>/it/{field.value || "…"}</FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />
            <fieldset className="grid min-w-0 gap-4 rounded-lg border p-3">
              <legend className="px-1 text-sm font-medium">
                English version <span className="font-normal text-muted-foreground">(optional)</span>
              </legend>
              <FormField
                control={form.control}
                name="enTitle"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Title (English)</FormLabel>
                    <FormControl>
                      <Input
                        {...field}
                        onChange={(e) => {
                          field.onChange(e);
                          if (!slugTouched.en) form.setValue("enSlug", slugify(e.target.value));
                        }}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="enSlug"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Address (English)</FormLabel>
                    <FormControl>
                      <Input
                        {...field}
                        onChange={(e) => {
                          setSlugTouched((s) => ({ ...s, en: true }));
                          field.onChange(e.target.value.toLowerCase());
                        }}
                      />
                    </FormControl>
                    <FormDescription>/en/{field.value || "…"}</FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </fieldset>
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={pending}>
                {pending ? "Creating…" : "Create page"}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
