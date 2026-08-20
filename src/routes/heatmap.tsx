import { useEffect, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { AppShell } from "@/components/AppShell";
import { Card, CardContent } from "@/components/ui/card";
import { getEvents } from "@/lib/store";
import { CATEGORIES, type EventRecord } from "@/lib/types";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export const Route = createFileRoute("/heatmap")({
  head: () => ({
    meta: [
      { title: "Incident Heat Map — Adama 9141" },
      { name: "description", content: "Geographic distribution of incidents across Adama City." },
      { property: "og:title", content: "Incident Heat Map — Adama 9141" },
      { property: "og:description", content: "Geographic distribution of incidents." },
    ],
  }),
  component: HeatmapPage,
});

const ALL = "all";

function HeatmapPage() {
  const [events, setEvents] = useState<EventRecord[]>([]);
  const [cat, setCat] = useState(ALL);
  const [Map, setMap] = useState<null | React.ComponentType<{ events: EventRecord[] }>>(null);

  useEffect(() => {
    const sync = () => setEvents(getEvents());
    sync();
    window.addEventListener("a9141:change", sync);
    import("@/components/HeatMapView").then((m) => setMap(() => m.HeatMapView));
    return () => window.removeEventListener("a9141:change", sync);
  }, []);

  const filtered = events.filter((e) => cat === ALL || e.category === cat);

  return (
    <AppShell title="Incident Heat Map">
      <div className="space-y-4">
        <Card>
          <CardContent className="flex flex-wrap items-center gap-3 p-4">
            <Select value={cat} onValueChange={setCat}>
              <SelectTrigger className="w-64">
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
            <p className="text-sm text-muted-foreground">{filtered.length} incidents plotted</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="h-[70vh] p-0">
            {Map ? (
              <Map events={filtered} />
            ) : (
              <div className="flex h-full items-center justify-center text-muted-foreground">
                Loading map…
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
