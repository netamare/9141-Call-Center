import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getSettings, setSettings } from "@/lib/store";
import { PRIORITIES } from "@/lib/types";
import type { Settings } from "@/lib/types";

export const Route = createFileRoute("/settings")({
  head: () => ({
    meta: [
      { title: "System Settings — Adama 9141" },
      { name: "description", content: "Configure SLA targets, departments and SMS templates." },
      { property: "og:title", content: "System Settings — Adama 9141" },
      { property: "og:description", content: "Configure SLA targets and departments." },
    ],
  }),
  component: SettingsPage,
});

function SettingsPage() {
  const [s, setS] = useState<Settings>(getSettings());

  const save = () => {
    setSettings(s);
    toast.success("Settings saved");
  };

  return (
    <AppShell title="System Settings">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">SLA targets (minutes)</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-2">
            {PRIORITIES.map((p) => (
              <div key={p} className="space-y-1">
                <Label className="text-xs">{p} response target</Label>
                <Input
                  type="number"
                  value={s.sla[p]}
                  onChange={(e) =>
                    setS({ ...s, sla: { ...s.sla, [p]: Number(e.target.value) || 0 } })
                  }
                />
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Departments</CardTitle>
          </CardHeader>
          <CardContent>
            <Label className="text-xs">One department per line</Label>
            <textarea
              className="mt-1 min-h-40 w-full rounded-lg border border-input bg-background p-2 text-sm"
              value={s.departments.join("\n")}
              onChange={(e) =>
                setS({ ...s, departments: e.target.value.split("\n").filter(Boolean) })
              }
            />
          </CardContent>
        </Card>

        <Card className="lg:col-span-2">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">SMS template</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">Citizen update template</Label>
              <Input
                value={s.smsTemplate}
                onChange={(e) => setS({ ...s, smsTemplate: e.target.value })}
              />
            </div>
          </CardContent>
        </Card>

        <Button className="min-h-11 lg:col-span-2" onClick={save}>
          Save settings
        </Button>
      </div>
    </AppShell>
  );
}
