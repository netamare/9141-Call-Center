import { cn } from "@/lib/utils";
import type { Priority, Status } from "@/lib/types";

const STATUS_STYLE: Record<Status, string> = {
  New: "bg-secondary text-secondary-foreground",
  Assigned: "bg-[oklch(0.9_0.09_240)] text-[oklch(0.3_0.1_240)]",
  Ongoing: "bg-[oklch(0.92_0.1_75)] text-[oklch(0.4_0.12_60)]",
  Solved: "bg-[oklch(0.9_0.1_150)] text-[oklch(0.35_0.12_152)]",
  Unsolved: "bg-[oklch(0.9_0.08_25)] text-[oklch(0.4_0.18_27)]",
};

const PRIORITY_STYLE: Record<Priority, string> = {
  P1: "bg-[oklch(0.9_0.09_25)] text-[oklch(0.42_0.2_27)]",
  P2: "bg-[oklch(0.92_0.1_60)] text-[oklch(0.42_0.15_55)]",
  P3: "bg-[oklch(0.94_0.11_95)] text-[oklch(0.42_0.12_90)]",
  P4: "bg-[oklch(0.92_0.09_150)] text-[oklch(0.36_0.12_152)]",
};

export const PRIORITY_COLOR: Record<Priority, string> = {
  P1: "#dc2626",
  P2: "#ea580c",
  P3: "#ca8a04",
  P4: "#16a34a",
};

export function StatusBadge({ status }: { status: Status }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold",
        STATUS_STYLE[status],
      )}
    >
      {status}
    </span>
  );
}

export function PriorityBadge({ priority }: { priority: Priority }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold",
        PRIORITY_STYLE[priority],
      )}
    >
      {priority}
    </span>
  );
}
