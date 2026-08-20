import { createFileRoute, Link } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import {
  Ambulance,
  ArrowRight,
  Building2,
  Clock,
  Flame,
  ShieldCheck,
  Siren,
  Star,
  Search,
} from "lucide-react";
import { Brand } from "@/components/Brand";
import { PublicShell } from "@/components/PublicShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { averageRating, ensureSeed, getEvents, minutesBetween } from "@/lib/store";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Adama City 9141 Emergency Call Center — Report & Track Emergencies" },
      {
        name: "description",
        content:
          "Report an emergency, track your complaint and reach the Adama City 9141 emergency call center, available 24 hours a day.",
      },
      { property: "og:title", content: "Adama City 9141 Emergency Call Center" },
      {
        property: "og:description",
        content: "Report an emergency or track your complaint with Adama City 9141.",
      },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary_large_image" },
    ],
  }),
  component: HomePage,
});

const SERVICES = [
  { icon: Ambulance, title: "Traffic Accident", desc: "Collisions, hit-and-run and road blockages." },
  { icon: Flame, title: "Fire & Water", desc: "Fires, gas leaks, flooding and burst pipes." },
  { icon: ShieldCheck, title: "Peace & Security", desc: "Theft, assault and public disturbances." },
  { icon: Building2, title: "Office Problem", desc: "Service delays, misconduct and corruption." },
];

function HomePage() {
  const [stats, setStats] = useState({ total: 0, solved: 0, avg: 0, rating: 0 });

  useEffect(() => {
    ensureSeed();
    const events = getEvents();
    const times = events
      .map((e) => minutesBetween(e.createdAt, e.assignedAt))
      .filter((v): v is number => v !== null);
    setStats({
      total: events.length,
      solved: events.filter((e) => e.status === "Solved").length,
      avg: times.length ? Math.round(times.reduce((a, b) => a + b, 0) / times.length) : 0,
      rating: Number(averageRating().toFixed(1)),
    });
  }, []);

  return (
    <PublicShell>
      <section className="animate-in fade-in slide-in-from-bottom-4 rounded-3xl bg-brand-gradient p-6 text-primary-foreground shadow-brand duration-500 sm:p-10">
        <Brand size="lg" onDark />
        <h1 className="mt-6 max-w-2xl font-display text-3xl font-bold leading-tight drop-shadow sm:text-4xl">
          Every emergency answered. Every citizen protected.
        </h1>
        <p className="mt-3 max-w-2xl text-sm text-white/85 sm:text-base">
          Call <strong>9141</strong> or report online. Your report reaches the right department in
          seconds and you can follow its progress at any time.
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Button asChild size="lg" variant="secondary" className="min-h-12 font-semibold">
            <Link to="/report">
              <Siren className="size-5" /> Report an Emergency
            </Link>
          </Button>
          <Button
            asChild
            size="lg"
            className="min-h-12 bg-white/15 font-semibold text-white hover:bg-white/25"
          >
            <Link to="/portal">
              <Search className="size-5" /> Track My Complaint
            </Link>
          </Button>
        </div>
      </section>

      <section className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {[
          { label: "Calls handled", value: stats.total, icon: Siren },
          { label: "Resolved cases", value: stats.solved, icon: ShieldCheck },
          { label: "Avg. response (min)", value: stats.avg, icon: Clock },
          { label: "Citizen rating", value: stats.rating || "—", icon: Star },
        ].map((s) => (
          <Card key={s.label} className="transition hover:-translate-y-0.5 hover:shadow-brand">
            <CardContent className="flex items-center gap-3 p-5">
              <s.icon className="size-8 text-primary" />
              <div>
                <p className="font-display text-2xl font-bold">{s.value}</p>
                <p className="text-xs text-muted-foreground">{s.label}</p>
              </div>
            </CardContent>
          </Card>
        ))}
      </section>

      <section className="mt-10">
        <h2 className="font-display text-2xl font-bold">What we respond to</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {SERVICES.map((s) => (
            <Card key={s.title} className="group transition hover:-translate-y-1 hover:shadow-brand">
              <CardContent className="p-5">
                <s.icon className="size-8 text-primary transition group-hover:scale-110" />
                <p className="mt-3 font-display font-semibold">{s.title}</p>
                <p className="mt-1 text-sm text-muted-foreground">{s.desc}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      <section className="mt-10 rounded-2xl border border-border bg-card p-6 shadow-sm">
        <h2 className="font-display text-xl font-bold">Life-threatening emergency?</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Always call <strong className="text-primary">9141</strong> first — the online form is for
          non-immediate reports and follow-ups.
        </p>
        <Link
          to="/about"
          className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
        >
          Learn about the 9141 call center <ArrowRight className="size-4" />
        </Link>
      </section>
    </PublicShell>
  );
}
