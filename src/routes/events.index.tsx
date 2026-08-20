import { useEffect, useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { ArrowUpDown, Bookmark, Download, Search, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { PriorityBadge, StatusBadge } from "@/components/badges";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import { useAuth } from "@/lib/auth";
import {
  getEvents,
  getSavedSearches,
  getSettings,
  logAudit,
  setEvents,
  setSavedSearches,
  uid,
} from "@/lib/store";
import {
  CATEGORIES,
  PRIORITIES,
  STATUSES,
  type EventRecord,
  type Priority,
  type Status,
} from "@/lib/types";

export const Route = createFileRoute("/events/")({
  head: () => ({
    meta: [
      { title: "Event List — Adama 9141" },
      { name: "description", content: "Search, filter and manage all emergency events." },
      { property: "og:title", content: "Event List — Adama 9141" },
      { property: "og:description", content: "Search, filter and manage all emergency events." },
    ],
  }),
  component: EventListPage,
});

const ALL = "all";

export function toCsv(rows: EventRecord[]) {
  const head = [
    "ID",
    "Caller",
    "Phone",
    "Category",
    "Priority",
    "Status",
    "Department",
    "Location",
    "Created",
  ];
  const body = rows.map((e) =>
    [
      e.id,
      e.callerName,
      e.phone,
      e.category,
      e.priority,
      e.status,
      e.department,
      e.location,
      new Date(e.createdAt).toLocaleString(),
    ]
      .map((v) => `"${String(v).replace(/"/g, '""')}"`)
      .join(","),
  );
  return [head.join(","), ...body].join("\n");
}

export function download(name: string, content: string, type = "text/csv") {
  const blob = new Blob([content], { type });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = name;
  a.click();
  URL.revokeObjectURL(url);
}

function EventListPage() {
  const { user } = useAuth();
  const [all, setAll] = useState<EventRecord[]>([]);
  const [q, setQ] = useState("");
  const [category, setCategory] = useState(ALL);
  const [priority, setPriority] = useState(ALL);
  const [status, setStatus] = useState(ALL);
  const [department, setDepartment] = useState(ALL);
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [sortBy, setSortBy] = useState<"createdAt" | "priority" | "status">("createdAt");
  const [dir, setDir] = useState<"asc" | "desc">("desc");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [selected, setSelected] = useState<string[]>([]);
  const [saved, setSaved] = useState(getSavedSearches());
  const [bulkValue, setBulkValue] = useState("");
  const departments = getSettings().departments;

  useEffect(() => {
    const sync = () => setAll(getEvents());
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, []);

  const filtered = useMemo(() => {
    const terms = q
      .toLowerCase()
      .split(/\s+(?:and)\s+|\s+/)
      .filter(Boolean);
    const orMode = q.toLowerCase().includes(" or ");
    const orTerms = q
      .toLowerCase()
      .split(/\s+or\s+/)
      .map((t) => t.trim())
      .filter(Boolean);

    let rows = all.filter((e) => {
      const hay =
        `${e.id} ${e.callerName} ${e.location} ${e.address} ${e.description} ${e.phone} ${e.department} ${e.category}`.toLowerCase();
      const matchQ = !q
        ? true
        : orMode
          ? orTerms.some((t) => hay.includes(t))
          : terms.every((t) => t === "and" || hay.includes(t));
      const matchCat = category === ALL || e.category === category;
      const matchPri = priority === ALL || e.priority === priority;
      const matchSta = status === ALL || e.status === status;
      const matchDep = department === ALL || e.department === department;
      const t = +new Date(e.createdAt);
      const matchFrom = !from || t >= +new Date(from);
      const matchTo = !to || t <= +new Date(to) + 86400000;
      return matchQ && matchCat && matchPri && matchSta && matchDep && matchFrom && matchTo;
    });

    const order = dir === "asc" ? 1 : -1;
    rows = [...rows].sort((a, b) => {
      if (sortBy === "createdAt") return (+new Date(a.createdAt) - +new Date(b.createdAt)) * order;
      if (sortBy === "priority") return a.priority.localeCompare(b.priority) * order;
      return a.status.localeCompare(b.status) * order;
    });
    return rows;
  }, [all, q, category, priority, status, department, from, to, sortBy, dir]);

  const pages = Math.max(1, Math.ceil(filtered.length / perPage));
  const current = filtered.slice((page - 1) * perPage, page * perPage);
  const isAdmin = user?.role === "admin";

  const applyBulk = (kind: "status" | "priority" | "department") => {
    if (!bulkValue || selected.length === 0) {
      toast.error("Select events and a value first");
      return;
    }
    setEvents(
      getEvents().map((e) =>
        selected.includes(e.id)
          ? {
              ...e,
              ...(kind === "status" ? { status: bulkValue as Status } : {}),
              ...(kind === "priority" ? { priority: bulkValue as Priority } : {}),
              ...(kind === "department" ? { department: bulkValue } : {}),
              ...(kind === "status" && bulkValue === "Assigned" && !e.assignedAt
                ? { assignedAt: new Date().toISOString() }
                : {}),
            }
          : e,
      ),
    );
    logAudit(user?.fullName ?? "", `Bulk ${kind} update on ${selected.length} events`);
    toast.success(`Updated ${selected.length} events`);
    setSelected([]);
  };

  return (
    <AppShell title="Event List">
      <div className="space-y-4">
        <Card>
          <CardContent className="space-y-4 p-4">
            <div className="flex flex-col gap-2 sm:flex-row">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-primary" />
                <Input
                  className="pl-9"
                  placeholder="Search ID, caller, location, description… (supports AND / OR)"
                  value={q}
                  onChange={(e) => {
                    setQ(e.target.value);
                    setPage(1);
                  }}
                />
              </div>
              <Button
                variant="outline"
                className="min-h-11"
                onClick={() => {
                  const name = window.prompt("Name this search");
                  if (!name) return;
                  const next = [...saved, { id: uid(), name, query: q }];
                  setSaved(next);
                  setSavedSearches(next);
                  toast.success("Search saved");
                }}
              >
                <Bookmark className="size-4" /> Save
              </Button>
              <Button
                className="min-h-11"
                onClick={() => {
                  download(`events-${Date.now()}.csv`, toCsv(filtered));
                  toast.success("CSV exported");
                }}
              >
                <Download className="size-4" /> Export CSV
              </Button>
            </div>

            {saved.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {saved.map((s) => (
                  <button
                    key={s.id}
                    onClick={() => setQ(s.query)}
                    className="rounded-full bg-secondary px-3 py-1 text-xs text-secondary-foreground"
                  >
                    {s.name}
                  </button>
                ))}
              </div>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div className="space-y-1">
                <Label className="text-xs">Category</Label>
                <Select value={category} onValueChange={setCategory}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL}>All categories</SelectItem>
                    {CATEGORIES.map((c) => (
                      <SelectItem key={c} value={c}>
                        {c}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Priority</Label>
                <Select value={priority} onValueChange={setPriority}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL}>All priorities</SelectItem>
                    {PRIORITIES.map((p) => (
                      <SelectItem key={p} value={p}>
                        {p}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Status</Label>
                <Select value={status} onValueChange={setStatus}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL}>All statuses</SelectItem>
                    {STATUSES.map((s) => (
                      <SelectItem key={s} value={s}>
                        {s}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Department</Label>
                <Select value={department} onValueChange={setDepartment}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL}>All departments</SelectItem>
                    {departments.map((d) => (
                      <SelectItem key={d} value={d}>
                        {d}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">From</Label>
                <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">To</Label>
                <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Sort by</Label>
                <Select value={sortBy} onValueChange={(v) => setSortBy(v as typeof sortBy)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="createdAt">Date</SelectItem>
                    <SelectItem value="priority">Priority</SelectItem>
                    <SelectItem value="status">Status</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Per page</Label>
                <Select
                  value={String(perPage)}
                  onValueChange={(v) => {
                    setPerPage(Number(v));
                    setPage(1);
                  }}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10">10</SelectItem>
                    <SelectItem value="25">25</SelectItem>
                    <SelectItem value="50">50</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {selected.length > 0 && (
          <Card className="border-primary">
            <CardContent className="flex flex-wrap items-center gap-2 p-4">
              <span className="text-sm font-medium">{selected.length} selected</span>
              <Select value={bulkValue} onValueChange={setBulkValue}>
                <SelectTrigger className="w-48">
                  <SelectValue placeholder="Choose value" />
                </SelectTrigger>
                <SelectContent>
                  {[...STATUSES, ...PRIORITIES, ...departments].map((v) => (
                    <SelectItem key={v} value={v}>
                      {v}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Button size="sm" onClick={() => applyBulk("status")}>
                Set status
              </Button>
              <Button size="sm" onClick={() => applyBulk("priority")}>
                Set priority
              </Button>
              <Button size="sm" onClick={() => applyBulk("department")}>
                Assign dept
              </Button>
              <Button
                size="sm"
                variant="outline"
                onClick={() =>
                  download(
                    `selected-${Date.now()}.csv`,
                    toCsv(all.filter((e) => selected.includes(e.id))),
                  )
                }
              >
                <Download className="size-4" /> Export
              </Button>
              {isAdmin && (
                <AlertDialog>
                  <AlertDialogTrigger asChild>
                    <Button size="sm" variant="destructive">
                      <Trash2 className="size-4" /> Delete
                    </Button>
                  </AlertDialogTrigger>
                  <AlertDialogContent>
                    <AlertDialogHeader>
                      <AlertDialogTitle>Delete {selected.length} events?</AlertDialogTitle>
                      <AlertDialogDescription>
                        This permanently removes the selected events and their evidence.
                      </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                      <AlertDialogCancel>Cancel</AlertDialogCancel>
                      <AlertDialogAction
                        onClick={() => {
                          setEvents(getEvents().filter((e) => !selected.includes(e.id)));
                          logAudit(user?.fullName ?? "", `Deleted ${selected.length} events`);
                          setSelected([]);
                          toast.success("Events deleted");
                        }}
                      >
                        Delete
                      </AlertDialogAction>
                    </AlertDialogFooter>
                  </AlertDialogContent>
                </AlertDialog>
              )}
            </CardContent>
          </Card>
        )}

        <Card>
          <CardContent className="overflow-x-auto p-0">
            <table className="w-full min-w-[720px] text-sm">
              <thead className="bg-brand-gradient text-primary-foreground">
                <tr>
                  <th className="px-3 py-3">
                    <Checkbox
                      checked={current.length > 0 && current.every((e) => selected.includes(e.id))}
                      onCheckedChange={(v) =>
                        setSelected(v ? current.map((e) => e.id) : [])
                      }
                    />
                  </th>
                  <th className="px-3 py-3 text-left">ID</th>
                  <th className="px-3 py-3 text-left">Caller</th>
                  <th className="px-3 py-3 text-left">Category</th>
                  <th className="px-3 py-3 text-left">Priority</th>
                  <th className="px-3 py-3 text-left">Status</th>
                  <th className="px-3 py-3 text-left">Department</th>
                  <th
                    className="cursor-pointer px-3 py-3 text-left"
                    onClick={() => setDir(dir === "asc" ? "desc" : "asc")}
                  >
                    <span className="inline-flex items-center gap-1">
                      Date <ArrowUpDown className="size-3.5" />
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {current.map((e) => (
                  <tr key={e.id} className="border-t hover:bg-accent/40">
                    <td className="px-3 py-2">
                      <Checkbox
                        checked={selected.includes(e.id)}
                        onCheckedChange={(v) =>
                          setSelected((s) =>
                            v ? [...s, e.id] : s.filter((id) => id !== e.id),
                          )
                        }
                      />
                    </td>
                    <td className="px-3 py-2 font-medium">
                      <Link
                        to="/events/$id"
                        params={{ id: e.id }}
                        className="text-primary hover:underline"
                      >
                        {e.id}
                      </Link>
                    </td>
                    <td className="px-3 py-2">{e.callerName}</td>
                    <td className="px-3 py-2">{e.category}</td>
                    <td className="px-3 py-2">
                      <PriorityBadge priority={e.priority} />
                    </td>
                    <td className="px-3 py-2">
                      <StatusBadge status={e.status} />
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">{e.department}</td>
                    <td className="whitespace-nowrap px-3 py-2 text-muted-foreground">
                      {new Date(e.createdAt).toLocaleString()}
                    </td>
                  </tr>
                ))}
                {current.length === 0 && (
                  <tr>
                    <td colSpan={8} className="px-3 py-8 text-center text-muted-foreground">
                      No events match your filters.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </CardContent>
        </Card>

        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">
            {filtered.length} events · page {page} of {pages}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              className="min-h-11"
              disabled={page === 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              className="min-h-11"
              disabled={page >= pages}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      </div>
    </AppShell>
  );
}
