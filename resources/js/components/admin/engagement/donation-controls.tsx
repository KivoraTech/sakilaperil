"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";
import { toast } from "sonner";
import { Plus } from "lucide-react";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { recordBankTransfer, setManualDonationStatus } from "@/lib/actions/engagement";

export function RecordBankTransferDialog({ projects }: { projects: { id: string; title: string }[] }) {
  const [open, setOpen] = useState(false);
  const [pending, start] = useTransition();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [currency, setCurrency] = useState("EUR");
  const [projectId, setProjectId] = useState("none");
  const router = useRouter();
  const today = new Date().toISOString().slice(0, 10);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm">
          <Plus aria-hidden="true" /> Record bank transfer
        </Button>
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record a bank transfer</DialogTitle>
          <DialogDescription>
            For gifts received offline (bank transfer, eSewa/Khalti QR). They count in the totals as
            succeeded.
          </DialogDescription>
        </DialogHeader>
        <form
          id="record-transfer"
          className="grid gap-4"
          onSubmit={(e) => {
            e.preventDefault();
            const fd = new FormData(e.currentTarget);
            start(async () => {
              const res = await recordBankTransfer({
                amount: fd.get("amount"),
                currency,
                receivedOn: fd.get("receivedOn"),
                donorName: fd.get("donorName") || undefined,
                donorEmail: fd.get("donorEmail") || undefined,
                note: fd.get("note") || undefined,
                projectId: projectId === "none" ? undefined : projectId,
                isAnonymous: fd.get("isAnonymous") === "on",
              });
              if (res.ok) {
                toast.success(res.message);
                setOpen(false);
                setErrors({});
                router.refresh();
              } else {
                setErrors(res.fieldErrors ?? {});
                toast.error(res.message);
              }
            });
          }}
        >
          <div className="grid grid-cols-[1fr_7rem] gap-3">
            <div className="grid gap-1.5">
              <Label htmlFor="rt-amount">Amount</Label>
              <Input
                id="rt-amount"
                name="amount"
                inputMode="decimal"
                required
                aria-invalid={!!errors.amount}
              />
              {errors.amount ? <p className="text-xs text-destructive">{errors.amount[0]}</p> : null}
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="rt-currency">Currency</Label>
              <Select value={currency} onValueChange={setCurrency}>
                <SelectTrigger id="rt-currency">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="EUR">EUR</SelectItem>
                  <SelectItem value="NPR">NPR</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="rt-date">Received on</Label>
            <Input id="rt-date" name="receivedOn" type="date" max={today} defaultValue={today} required />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="rt-name">Donor name (optional)</Label>
            <Input id="rt-name" name="donorName" maxLength={120} />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="rt-email">Donor email (optional)</Label>
            <Input
              id="rt-email"
              name="donorEmail"
              type="email"
              maxLength={254}
              aria-invalid={!!errors.donorEmail}
            />
            {errors.donorEmail ? <p className="text-xs text-destructive">{errors.donorEmail[0]}</p> : null}
          </div>
          {projects.length ? (
            <div className="grid gap-1.5">
              <Label htmlFor="rt-project">Project (optional)</Label>
              <Select value={projectId} onValueChange={setProjectId}>
                <SelectTrigger id="rt-project">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Where most needed</SelectItem>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={p.id}>
                      {p.title}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          ) : null}
          <div className="grid gap-1.5">
            <Label htmlFor="rt-note">Note / bank reference (optional)</Label>
            <Input id="rt-note" name="note" maxLength={500} />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" name="isAnonymous" className="size-4" /> Anonymous donor
          </label>
        </form>
        <DialogFooter>
          <Button type="submit" form="record-transfer" disabled={pending}>
            {pending ? "Saving…" : "Save"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function ManualStatusSelect({ id, status }: { id: string; status: string }) {
  const [value, setValue] = useState(status);
  const [pending, start] = useTransition();
  const router = useRouter();
  return (
    <div className="grid gap-1.5">
      <Label htmlFor={`ds-${id}`}>Status (manual record)</Label>
      <Select
        value={value}
        disabled={pending}
        onValueChange={(next) => {
          const prev = value;
          setValue(next);
          start(async () => {
            const res = await setManualDonationStatus({ id, status: next });
            if (res.ok) {
              toast.success(res.message);
              router.refresh();
            } else {
              toast.error(res.message);
              setValue(prev);
            }
          });
        }}
      >
        <SelectTrigger id={`ds-${id}`} className="w-48">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="SUCCEEDED">Succeeded</SelectItem>
          <SelectItem value="CANCELED">Cancelled</SelectItem>
          <SelectItem value="REFUNDED">Refunded</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );
}
