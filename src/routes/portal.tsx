import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Search } from "lucide-react";
import { toast } from "sonner";
import { PriorityBadge, StatusBadge } from "@/components/badges";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { getEvents } from "@/lib/store";
import type { EventRecord } from "@/lib/types";

export const Route = createFileRoute("/portal")({
  head: () => ({
    meta: [
      { title: "Track Your Complaint — Adama 9141" },
      { name: "description", content: "Citizens can track the status of an Adama City 9141 report." },
      { property: "og:title", content: "Track Your Complaint — Adama 9141" },
      { property: "og:description", content: "Track the status of your emergency report." },
    ],
  }),
  component: PortalPage,
});

function PortalPage() {
  const [id, setId] = useState("");
  const [result, setResult] = useState<EventRecord | null | undefined>(undefined);

  const search = () => {
    const found = getEvents().find((e) => e.id.toLowerCase() === id.trim().toLowerCase());
    setResult(found ?? null);
    if (!found) toast.error("No report found with that ID");
  };

  return (
    <div className="min-h-screen bg-background">
      <header className="bg-brand-gradient px-4 py-8 text-primary-foreground">
        <div className="mx-auto max-w-2xl">
          <h1 className="text-2xl font-bold">Adama City 9141 — Citizen Portal</h1>
          <p className="text-sm opacity-90">Track the status of your emergency report</p>
        </div>
      </header>
      <main className="mx-auto max-w-2xl space-y-4 p-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Enter your report ID</CardTitle>
          </CardHeader>
          <CardContent className="flex gap-2">
            <Input
              placeholder="e.g. TRA-2026-001"
              value={id}
              onChange={(e) => setId(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && search()}
            />
            <Button className="min-h-11" onClick={search}>
              <Search className="size-4" /> Track
            </Button>
          </CardContent>
        </Card>

        {result === null && (
          <Card>
            <CardContent className="p-6 text-center text-muted-foreground">
              No report matches that ID. Please check and try again.
            </CardContent>
          </Card>
        )}

        {result && (
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                {result.id} <StatusBadge status={result.status} />
                <PriorityBadge priority={result.priority} />
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <p>
                <span className="text-muted-foreground">Category:</span> {result.category}
              </p>
              <p>
                <span className="text-muted-foreground">Reported:</span>{" "}
                {new Date(result.createdAt).toLocaleString()}
              </p>
              <p>
                <span className="text-muted-foreground">Assigned to:</span> {result.department}
              </p>
              <p>
                <span className="text-muted-foreground">Location:</span> {result.location}
              </p>
              <p className="rounded-lg bg-secondary p-3 text-muted-foreground">
                For your privacy, operator notes and personal contact details are not shown here.
              </p>
            </CardContent>
          </Card>
        )}

        <Card>
          <CardContent className="p-4 text-sm text-muted-foreground">
            In an emergency always call <strong className="text-primary">9141</strong>.
          </CardContent>
        </Card>
      </main>
    </div>
  );
}
