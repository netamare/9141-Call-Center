import { useEffect, useMemo, useState } from "react";
import { createFileRoute, Link } from "@tanstack/react-router";
import { Camera, MapPin, Mic, Navigation } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { PriorityBadge, StatusBadge } from "@/components/badges";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getEvents, logAudit, updateEvent } from "@/lib/store";
import { useAuth } from "@/lib/auth";
import type { EventRecord, Status } from "@/lib/types";

export const Route = createFileRoute("/field")({
  head: () => ({
    meta: [
      { title: "Field View — Adama 9141" },
      { name: "description", content: "Field officer view with arrival, GPS distance and ETA." },
      { property: "og:title", content: "Field View — Adama 9141" },
      { property: "og:description", content: "Field officer tools for on-scene response." },
    ],
  }),
  component: FieldPage,
});

function distanceKm(a: [number, number], b: [number, number]) {
  const R = 6371;
  const dLat = ((b[0] - a[0]) * Math.PI) / 180;
  const dLng = ((b[1] - a[1]) * Math.PI) / 180;
  const x =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((a[0] * Math.PI) / 180) * Math.cos((b[0] * Math.PI) / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}

function FieldPage() {
  const { user } = useAuth();
  const [events, setEvents] = useState<EventRecord[]>([]);
  const [me, setMe] = useState<[number, number]>([8.5414, 39.2689]);
  const [recording, setRecording] = useState(false);

  useEffect(() => {
    const sync = () => setEvents(getEvents());
    sync();
    window.addEventListener("a9141:change", sync);
    return () => window.removeEventListener("a9141:change", sync);
  }, []);

  const active = useMemo(
    () =>
      events
        .filter((e) => ["Assigned", "Ongoing", "New"].includes(e.status))
        .sort((a, b) => a.priority.localeCompare(b.priority))
        .slice(0, 6),
    [events],
  );

  const locate = () => {
    navigator.geolocation?.getCurrentPosition(
      (p) => {
        setMe([p.coords.latitude, p.coords.longitude]);
        toast.success("Position updated");
      },
      () => toast.error("Location unavailable"),
    );
  };

  const quick = (e: EventRecord, status: Status, label: string) => {
    updateEvent(e.id, {
      status,
      ...(status === "Assigned" && !e.assignedAt ? { assignedAt: new Date().toISOString() } : {}),
      ...(status === "Solved" ? { resolvedAt: new Date().toISOString() } : {}),
    });
    logAudit(user?.fullName ?? "Officer", `${label} on ${e.id}`);
    toast.success(`${label}: ${e.id}`);
  };

  return (
    <AppShell title="Field View">
      <div className="space-y-4">
        <Card>
          <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
            <div className="text-sm">
              <p className="font-semibold">My position</p>
              <p className="text-muted-foreground">
                {me[0].toFixed(4)}, {me[1].toFixed(4)}
              </p>
            </div>
            <Button className="min-h-11" onClick={locate}>
              <Navigation className="size-4" /> Update GPS
            </Button>
          </CardContent>
        </Card>

        {active.map((e) => {
          const d = distanceKm(me, [e.lat, e.lng]);
          return (
            <Card key={e.id}>
              <CardHeader className="pb-2">
                <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                  <Link to="/events/$id" params={{ id: e.id }} className="text-primary">
                    {e.id}
                  </Link>
                  <PriorityBadge priority={e.priority} />
                  <StatusBadge status={e.status} />
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm">{e.description}</p>
                <p className="flex items-center gap-1 text-sm text-muted-foreground">
                  <MapPin className="size-4 text-primary" /> {e.location} · {d.toFixed(1)} km away ·
                  ETA {Math.max(2, Math.round((d / 35) * 60))} min
                </p>
                <Button
                  className="min-h-12 w-full text-base"
                  onClick={() => {
                    updateEvent(e.id, { arrivedAt: new Date().toISOString(), status: "Ongoing" });
                    toast.success(`Arrival recorded for ${e.id}`);
                  }}
                >
                  Arrived at Scene
                </Button>
                <div className="grid grid-cols-2 gap-2">
                  <Button variant="secondary" className="min-h-11" onClick={() => quick(e, "Ongoing", "On-Site")}>
                    On-Site
                  </Button>
                  <Button
                    variant="secondary"
                    className="min-h-11"
                    onClick={() => quick(e, "Ongoing", "Investigating")}
                  >
                    Investigating
                  </Button>
                  <Button variant="secondary" className="min-h-11" onClick={() => quick(e, "Solved", "Resolved")}>
                    Resolved
                  </Button>
                  <Button
                    variant="destructive"
                    className="min-h-11"
                    onClick={() => {
                      toast.warning(`Backup requested for ${e.id}`);
                      logAudit(user?.fullName ?? "Officer", `Requested backup for ${e.id}`);
                    }}
                  >
                    Needs Backup
                  </Button>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <label className="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-primary/40 text-sm font-medium">
                    <Camera className="size-4 text-primary" /> Take photo
                    <input
                      type="file"
                      accept="image/*"
                      capture="environment"
                      className="hidden"
                      onChange={(ev) => {
                        const f = ev.target.files?.[0];
                        if (!f) return;
                        updateEvent(e.id, {
                          media: [
                            ...e.media,
                            { id: `${Date.now()}`, name: f.name, type: f.type, size: f.size },
                          ],
                        });
                        toast.success("Scene photo attached");
                      }}
                    />
                  </label>
                  <Button
                    variant="outline"
                    className="min-h-11"
                    onClick={() => {
                      setRecording((r) => !r);
                      toast.success(recording ? "Voice note saved" : "Recording voice note…");
                    }}
                  >
                    <Mic className="size-4" /> {recording ? "Stop" : "Voice note"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          );
        })}
        {active.length === 0 && (
          <Card>
            <CardContent className="p-8 text-center text-muted-foreground">
              No active assignments right now.
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  );
}
