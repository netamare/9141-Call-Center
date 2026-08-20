import { useEffect, useMemo, useState } from "react";
import { createFileRoute, Link, useParams } from "@tanstack/react-router";
import {
  ArrowLeft,
  Download,
  FileText,
  MessageSquare,
  Paperclip,
  Send,
  Star,
  Trash2,
  TriangleAlert,
} from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { PriorityBadge, StatusBadge } from "@/components/badges";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Textarea } from "@/components/ui/textarea";
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useAuth } from "@/lib/auth";
import {
  addNotification,
  getEvent,
  getSettings,
  logAudit,
  minutesBetween,
  uid,
  updateEvent,
} from "@/lib/store";
import { ALLOWED_TRANSITIONS, type EventRecord, type Status } from "@/lib/types";

export const Route = createFileRoute("/events/$id")({
  head: () => ({
    meta: [
      { title: "Event Details — Adama 9141" },
      { name: "description", content: "Full emergency event record, evidence and timeline." },
      { property: "og:title", content: "Event Details — Adama 9141" },
      {
        property: "og:description",
        content: "Full emergency event record, evidence and timeline.",
      },
    ],
  }),
  component: EventDetailPage,
});

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="text-sm font-medium text-foreground">{value || "—"}</p>
    </div>
  );
}

function EventDetailPage() {
  const { id } = useParams({ from: "/events/$id" });
  const { user } = useAuth();
  const [event, setEvent] = useState<EventRecord | undefined>(undefined);
  const [loading, setLoading] = useState(true);
  const [nextStatus, setNextStatus] = useState("");
  const [feedbackRating, setFeedbackRating] = useState(0);
  const [feedbackComment, setFeedbackComment] = useState("");
  const [smsOpen, setSmsOpen] = useState(false);
  const settings = getSettings();

  useEffect(() => {
    const sync = () => {
      setEvent(getEvent(id));
      setLoading(false);
    };
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, [id]);

  const responseTime = useMemo(
    () => (event ? minutesBetween(event.createdAt, event.assignedAt) : null),
    [event],
  );
  const resolutionTime = useMemo(
    () => (event ? minutesBetween(event.createdAt, event.resolvedAt) : null),
    [event],
  );

  if (loading) {
    return (
      <AppShell title="Event Details">
        <div className="h-40 animate-pulse rounded-xl bg-muted" />
      </AppShell>
    );
  }

  if (!event) {
    return (
      <AppShell title="Event Details">
        <Card>
          <CardContent className="p-8 text-center">
            <p className="font-medium">Event {id} was not found.</p>
            <Link to="/events" className="mt-3 inline-block text-primary hover:underline">
              Back to event list
            </Link>
          </CardContent>
        </Card>
      </AppShell>
    );
  }

  const ev = event;
  const isAdmin = user?.role === "admin";
  const allowed = ALLOWED_TRANSITIONS[ev.status];

  const applyStatus = () => {
    if (!nextStatus) return;
    if (!allowed.includes(nextStatus as Status)) {
      toast.error(`Cannot move from ${ev.status} to ${nextStatus}`);
      return;
    }
    const patch: Partial<EventRecord> = { status: nextStatus as Status };
    if (nextStatus === "Assigned" && !ev.assignedAt) patch.assignedAt = new Date().toISOString();
    if (nextStatus === "Solved" || nextStatus === "Unsolved")
      patch.resolvedAt = new Date().toISOString();
    updateEvent(ev.id, patch);
    addNotification({
      title: `Status updated: ${ev.id}`,
      body: `${ev.status} → ${nextStatus}`,
      eventId: ev.id,
      kind: "status",
    });
    logAudit(user?.fullName ?? "", `Set ${ev.id} to ${nextStatus}`);
    toast.success(`Event moved to ${nextStatus}`);
    setNextStatus("");
  };

  const escalate = () => {
    updateEvent(ev.id, {
      escalations: [
        ...ev.escalations,
        {
          id: uid(),
          at: new Date().toISOString(),
          reason: "Manual escalation by " + (user?.fullName ?? "user"),
          by: user?.fullName ?? "user",
          level: "Supervisor + Department Head",
        },
      ],
    });
    addNotification({
      title: `Escalated: ${ev.id}`,
      body: "Manually escalated to supervisor and department head.",
      eventId: ev.id,
      kind: "escalation",
    });
    toast.success("Event escalated");
  };

  const uploadMore = (files: FileList | null) => {
    if (!files) return;
    const items = Array.from(files)
      .filter((f) => {
        if (f.size > 10 * 1024 * 1024) {
          toast.error(`${f.name} exceeds 10MB`);
          return false;
        }
        return true;
      })
      .map((f) => ({ id: uid(), name: f.name, type: f.type, size: f.size }));
    if (!items.length) return;
    updateEvent(ev.id, { media: [...ev.media, ...items] });
    toast.success("Evidence uploaded");
  };

  const smsText = settings.smsTemplate
    .replace("{caller}", ev.callerName)
    .replace("{eventId}", ev.id)
    .replace("{status}", ev.status)
    .replace("{department}", ev.department);

  return (
    <AppShell title={`Event ${ev.id}`}>
      <div className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <Link to="/events">
            <Button variant="outline" className="min-h-11">
              <ArrowLeft className="size-4" /> Back
            </Button>
          </Link>
          <PriorityBadge priority={ev.priority} />
          <StatusBadge status={ev.status} />
          <span className="text-sm text-muted-foreground">{ev.department}</span>
        </div>

        <div className="grid gap-4 lg:grid-cols-3">
          <Card className="lg:col-span-2">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Event information</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-3">
              <Field label="Caller" value={ev.callerName} />
              <Field label="Phone" value={ev.phone} />
              <Field label="Alt. phone" value={ev.altPhone ?? ""} />
              <Field label="Gender" value={ev.gender} />
              <Field label="Category" value={ev.category} />
              <Field label="Sub-category" value={ev.subCategory ?? ""} />
              <Field label="Location" value={ev.location} />
              <Field label="Address" value={ev.address} />
              <Field label="Operator" value={ev.operator} />
              <Field label="Created" value={new Date(ev.createdAt).toLocaleString()} />
              <Field
                label="Response time"
                value={responseTime !== null ? `${responseTime} min` : "Pending"}
              />
              <Field
                label="Resolution time"
                value={resolutionTime !== null ? `${resolutionTime} min` : "Pending"}
              />
              <div className="sm:col-span-3">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Description</p>
                <p className="text-sm">{ev.description}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Actions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="space-y-2">
                <Select value={nextStatus} onValueChange={setNextStatus}>
                  <SelectTrigger>
                    <SelectValue placeholder="Update status" />
                  </SelectTrigger>
                  <SelectContent>
                    {allowed.map((s) => (
                      <SelectItem key={s} value={s}>
                        {s}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Button className="min-h-11 w-full" onClick={applyStatus}>
                  Apply status change
                </Button>
                <p className="text-xs text-muted-foreground">
                  Allowed from {ev.status}: {allowed.join(", ") || "none"}
                </p>
              </div>
              <Button className="min-h-11 w-full" variant="secondary" onClick={escalate}>
                <TriangleAlert className="size-4" /> Escalate
              </Button>
              <Dialog open={smsOpen} onOpenChange={setSmsOpen}>
                <DialogTrigger asChild>
                  <Button className="min-h-11 w-full" variant="outline">
                    <Send className="size-4" /> Send update to citizen
                  </Button>
                </DialogTrigger>
                <DialogContent>
                  <DialogHeader>
                    <DialogTitle>SMS to {ev.phone}</DialogTitle>
                    <DialogDescription>Message uses the configured SMS template.</DialogDescription>
                  </DialogHeader>
                  <Textarea defaultValue={smsText} rows={4} />
                  <DialogFooter>
                    <Button
                      onClick={() => {
                        updateEvent(ev.id, {
                          messages: [
                            ...ev.messages,
                            { at: new Date().toISOString(), from: "Call center", text: smsText },
                          ],
                        });
                        setSmsOpen(false);
                        toast.success("SMS update sent to citizen");
                      }}
                    >
                      Send SMS
                    </Button>
                  </DialogFooter>
                </DialogContent>
              </Dialog>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Media attachments ({ev.media.length})</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {ev.media.map((m) => (
                <div key={m.id} className="rounded-xl border bg-card p-3">
                  <div className="mb-2 flex h-20 items-center justify-center rounded-lg bg-secondary text-primary">
                    <FileText className="size-7" />
                  </div>
                  <p className="truncate text-xs font-medium">{m.name}</p>
                  <p className="text-[11px] text-muted-foreground">
                    {(m.size / 1024).toFixed(0)} KB
                  </p>
                  <div className="mt-2 flex gap-1">
                    <Button
                      size="sm"
                      variant="outline"
                      className="flex-1"
                      onClick={() => toast.info(`Downloading ${m.name}`)}
                    >
                      <Download className="size-3.5" />
                    </Button>
                    {isAdmin && (
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          updateEvent(ev.id, { media: ev.media.filter((x) => x.id !== m.id) });
                          toast.success("Attachment deleted");
                        }}
                      >
                        <Trash2 className="size-3.5 text-destructive" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}
              {ev.media.length === 0 && (
                <p className="text-sm text-muted-foreground">No attachments yet.</p>
              )}
            </div>
            <label className="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-primary/40 bg-secondary/40 px-4 py-3 text-sm font-medium">
              <Paperclip className="size-4 text-primary" /> Upload more evidence
              <input type="file" multiple className="hidden" onChange={(e) => uploadMore(e.target.files)} />
            </label>
          </CardContent>
        </Card>

        <div className="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Escalation history</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {ev.escalations.length === 0 && (
                <p className="text-sm text-muted-foreground">No escalations recorded.</p>
              )}
              {ev.escalations.map((e) => (
                <div key={e.id} className="rounded-lg border-l-4 border-l-destructive bg-card p-3">
                  <p className="text-sm font-medium">{e.level}</p>
                  <p className="text-xs text-muted-foreground">{e.reason}</p>
                  <p className="text-[11px] text-muted-foreground">
                    {e.by} · {new Date(e.at).toLocaleString()}
                  </p>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">Citizen feedback</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {ev.feedback ? (
                <div className="rounded-lg bg-secondary p-3">
                  <div className="flex gap-0.5">
                    {Array.from({ length: 5 }, (_, i) => (
                      <Star
                        key={i}
                        className={
                          i < (ev.feedback?.rating ?? 0)
                            ? "size-4 fill-yellow-400 text-yellow-400"
                            : "size-4 text-muted-foreground"
                        }
                      />
                    ))}
                  </div>
                  <p className="mt-2 text-sm">{ev.feedback.comment}</p>
                  <p className="text-[11px] text-muted-foreground">
                    {new Date(ev.feedback.at).toLocaleString()}
                  </p>
                </div>
              ) : ev.status === "Solved" ? (
                <div className="space-y-2">
                  <p className="text-sm font-medium">This event is solved — collect feedback:</p>
                  <div className="flex gap-1">
                    {Array.from({ length: 5 }, (_, i) => (
                      <button key={i} type="button" onClick={() => setFeedbackRating(i + 1)}>
                        <Star
                          className={
                            i < feedbackRating
                              ? "size-6 fill-yellow-400 text-yellow-400"
                              : "size-6 text-muted-foreground"
                          }
                        />
                      </button>
                    ))}
                  </div>
                  <Textarea
                    rows={3}
                    placeholder="Citizen comments"
                    value={feedbackComment}
                    onChange={(e) => setFeedbackComment(e.target.value)}
                  />
                  <Button
                    className="min-h-11 w-full"
                    onClick={() => {
                      if (!feedbackRating) {
                        toast.error("Select a rating first");
                        return;
                      }
                      updateEvent(ev.id, {
                        feedback: {
                          rating: feedbackRating,
                          comment: feedbackComment,
                          at: new Date().toISOString(),
                        },
                      });
                      addNotification({
                        title: "Citizen feedback received",
                        body: `${ev.id} rated ${feedbackRating}/5`,
                        eventId: ev.id,
                        kind: "feedback",
                      });
                      toast.success("Feedback saved");
                    }}
                  >
                    Submit feedback
                  </Button>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">
                  Feedback becomes available once the event is solved.
                </p>
              )}

              {ev.messages.length > 0 && (
                <div className="space-y-2 border-t pt-3">
                  <p className="flex items-center gap-1 text-sm font-medium">
                    <MessageSquare className="size-4 text-primary" /> Message history
                  </p>
                  {ev.messages.map((m, i) => (
                    <p key={i} className="text-xs text-muted-foreground">
                      {new Date(m.at).toLocaleString()} — {m.from}: {m.text}
                    </p>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}
