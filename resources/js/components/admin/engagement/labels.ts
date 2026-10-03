/** Admin labels for submission/donation statuses (admin UI is English). */
const LABELS: Record<string, string> = {
  NEW: "New",
  READ: "Read",
  REPLIED: "Replied",
  SPAM: "Spam",
  ARCHIVED: "Archived",
  IN_REVIEW: "In review",
  ACCEPTED: "Accepted",
  REJECTED: "Rejected",
  PENDING: "Pending",
  SUBSCRIBED: "Subscribed",
  UNSUBSCRIBED: "Unsubscribed",
  SUCCEEDED: "Succeeded",
  FAILED: "Failed",
  CANCELED: "Cancelled",
  REFUNDED: "Refunded",
};

export function statusLabel(status: string): string {
  return LABELS[status] ?? status;
}

export function statusTone(status: string): "default" | "secondary" | "destructive" | "outline" {
  switch (status) {
    case "NEW":
    case "PENDING":
      return "default";
    case "SUCCEEDED":
    case "SUBSCRIBED":
    case "ACCEPTED":
    case "REPLIED":
      return "secondary";
    case "FAILED":
    case "SPAM":
    case "REJECTED":
      return "destructive";
    default:
      return "outline";
  }
}

export const contactStatusOptions = ["NEW", "READ", "REPLIED", "SPAM", "ARCHIVED"].map((v) => ({
  value: v,
  label: statusLabel(v),
}));
export const applicationStatusOptions = ["NEW", "IN_REVIEW", "ACCEPTED", "REJECTED", "ARCHIVED"].map((v) => ({
  value: v,
  label: statusLabel(v),
}));
