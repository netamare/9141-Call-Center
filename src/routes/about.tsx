import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { Building2, Flame, Gauge, HeartHandshake, ShieldCheck, Siren, Zap } from "lucide-react";
import { Brand } from "@/components/Brand";
import { PublicShell } from "@/components/PublicShell";
import { Card, CardContent } from "@/components/ui/card";
import { ensureSeed, getEvents, minutesBetween } from "@/lib/store";

export const Route = createFileRoute("/about")({
  head: () => ({
    meta: [
      { title: "About the Adama City 9141 Emergency Call Center" },
      {
        name: "description",
        content:
          "Learn about the mission, vision, values and response performance of the Adama City 9141 emergency call center.",
      },
      { property: "og:title", content: "About Adama City 9141" },
      {
        property: "og:description",
        content: "Mission, vision, values and performance of the Adama City 9141 call center.",
      },
    ],
  }),
  component: AboutPage,
});

const VALUES = [
  { icon: Zap, title: "Speed", desc: "Every second counts — calls are triaged in under a minute." },
  { icon: ShieldCheck, title: "Integrity", desc: "Every report is recorded, audited and traceable." },
  { icon: Gauge, title: "Professionalism", desc: "Trained operators and dispatch discipline." },
  { icon: HeartHandshake, title: "Citizen-Centric", desc: "We follow up until the citizen is satisfied." },
];

const DEPARTMENTS = [
  { icon: Siren, name: "Traffic Police Department" },
  { icon: Flame, name: "Fire Brigade Department" },
  { icon: ShieldCheck, name: "Police Department" },
  { icon: Building2, name: "Administrative Office" },
];

function AboutPage() {
  const [stats, setStats] = useState({ total: 0, avg: 0, solved: 0 });
  useEffect(() => {
    ensureSeed();
    const events = getEvents();
    const times = events
      .map((e) => minutesBetween(e.createdAt, e.assignedAt))
      .filter((v): v is number => v !== null);
    setStats({
      total: events.length,
      avg: times.length ? Math.round(times.reduce((a, b) => a + b, 0) / times.length) : 0,
      solved: events.filter((e) => e.status === "Solved").length,
    });
  }, []);

  return (
    <PublicShell>
      <div className="max-w-3xl">
        <Brand size="lg" />
        <h1 className="mt-6 font-display text-3xl font-bold">About Adama City 9141</h1>
        <p className="mt-3 text-muted-foreground">
          The 9141 Emergency Call Center is the single emergency number of the Adama City
          Administration. Established to unify traffic, fire, security and municipal service
          reporting, it connects citizens with the right department from one phone call — day or
          night, in Afaan Oromoo, Amharic, Tigrigna and English.
        </p>
      </div>

      <div className="mt-8 grid gap-4 sm:grid-cols-2">
        <Card>
          <CardContent className="p-6">
            <p className="font-display text-lg font-bold text-primary">Our Mission</p>
            <p className="mt-2 text-sm text-muted-foreground">
              To provide rapid, efficient, and professional emergency response.
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <p className="font-display text-lg font-bold text-primary">Our Vision</p>
            <p className="mt-2 text-sm text-muted-foreground">
              A safer Adama where every emergency is responded to within minutes.
            </p>
          </CardContent>
        </Card>
      </div>

      <h2 className="mt-10 font-display text-2xl font-bold">Core values</h2>
      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {VALUES.map((v) => (
          <Card key={v.title} className="transition hover:-translate-y-1 hover:shadow-brand">
            <CardContent className="p-5">
              <v.icon className="size-8 text-primary" />
              <p className="mt-3 font-display font-semibold">{v.title}</p>
              <p className="mt-1 text-sm text-muted-foreground">{v.desc}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <h2 className="mt-10 font-display text-2xl font-bold">Response statistics</h2>
      <div className="mt-4 grid gap-4 sm:grid-cols-3">
        {[
          { label: "Total calls recorded", value: stats.total },
          { label: "Cases resolved", value: stats.solved },
          { label: "Average response (minutes)", value: stats.avg },
        ].map((s) => (
          <Card key={s.label}>
            <CardContent className="p-6">
              <p className="font-display text-3xl font-bold text-primary">{s.value}</p>
              <p className="text-xs text-muted-foreground">{s.label}</p>
            </CardContent>
          </Card>
        ))}
      </div>

      <h2 className="mt-10 font-display text-2xl font-bold">Responding departments</h2>
      <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {DEPARTMENTS.map((d) => (
          <Card key={d.name}>
            <CardContent className="flex items-center gap-3 p-5">
              <d.icon className="size-7 text-primary" />
              <p className="text-sm font-medium">{d.name}</p>
            </CardContent>
          </Card>
        ))}
      </div>
    </PublicShell>
  );
}
