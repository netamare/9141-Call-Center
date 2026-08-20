import { useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { CheckCircle2, MapPin, Paperclip, Siren } from "lucide-react";
import { toast } from "sonner";
import { PublicShell } from "@/components/PublicShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { createPublicReport, uid } from "@/lib/store";
import { CATEGORIES, PRIORITIES, type Category, type MediaItem, type Priority } from "@/lib/types";
import {
  LIMITS,
  sanitizeNameInput,
  sanitizePhoneInput,
  validateName,
  validatePhone,
  validateText,
} from "@/lib/validation";

export const Route = createFileRoute("/report")({
  head: () => ({
    meta: [
      { title: "Report an Emergency Online — Adama City 9141" },
      {
        name: "description",
        content:
          "Submit an emergency report to Adama City 9141 without signing in. Attach photos, share your location and receive an event ID instantly.",
      },
      { property: "og:title", content: "Report an Emergency — Adama City 9141" },
      {
        property: "og:description",
        content: "Report traffic, fire, security or office incidents to Adama City 9141 online.",
      },
    ],
  }),
  component: ReportPage,
});

const DEFAULT_PRIORITY: Record<Category, Priority> = {
  "Traffic Accident": "P1",
  "Fire & Water": "P1",
  "Peace & Security": "P2",
  "Office Problem": "P3",
};

const MAX_BYTES = 10 * 1024 * 1024;

function ReportPage() {
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [altPhone, setAltPhone] = useState("");
  const [location, setLocation] = useState("");
  const [coords, setCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [category, setCategory] = useState<Category>("Traffic Accident");
  const [priority, setPriority] = useState<Priority>("P1");
  const [description, setDescription] = useState("");
  const [media, setMedia] = useState<MediaItem[]>([]);
  const [human, setHuman] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [created, setCreated] = useState<string | null>(null);

  const errors = {
    name: validateName(name, "Full name"),
    phone: validatePhone(phone),
    altPhone: altPhone ? validatePhone(altPhone, false) : null,
    location: validateText(location, "Location", { min: 3, max: LIMITS.location.max }),
    description: validateText(description, "Description", {
      min: LIMITS.description.min,
      max: LIMITS.description.max,
    }),
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const first = Object.values(errors).find(Boolean);
    if (first) {
      toast.error(first);
      return;
    }
    if (human.trim() !== "12") {
      toast.error("Please answer the anti-bot question correctly (7 + 5)");
      return;
    }
    setSubmitting(true);
    window.setTimeout(() => {
      const event = createPublicReport({
        callerName: name.trim(),
        phone: phone.trim(),
        ...(altPhone.trim() ? { altPhone: altPhone.trim() } : {}),
        location: location.trim(),
        ...(coords ?? {}),
        category,
        priority,
        description: description.trim(),
        media,
      });
      setSubmitting(false);
      setCreated(event.id);
      toast.success(`Report received — SMS confirmation sent. ID ${event.id}`);
    }, 600);
  };

  if (created) {
    return (
      <PublicShell>
        <Card className="mx-auto max-w-xl animate-in fade-in zoom-in-95">
          <CardContent className="space-y-4 p-8 text-center">
            <CheckCircle2 className="mx-auto size-14 text-primary" />
            <h1 className="font-display text-2xl font-bold">Your report was received</h1>
            <p className="text-sm text-muted-foreground">
              Keep this reference number safe — you can use it to track progress at any time.
            </p>
            <p className="font-display text-3xl font-bold text-primary">{created}</p>
            <p className="text-sm text-muted-foreground">
              A confirmation SMS was sent to {phone}.
            </p>
            <div className="flex flex-wrap justify-center gap-2">
              <Button onClick={() => navigate({ to: "/portal", search: { id: created } })}>
                Track this report
              </Button>
              <Button variant="outline" onClick={() => window.location.reload()}>
                Report another emergency
              </Button>
            </div>
          </CardContent>
        </Card>
      </PublicShell>
    );
  }

  return (
    <PublicShell>
      <div className="mx-auto max-w-2xl">
        <h1 className="font-display text-3xl font-bold">Report an Emergency</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          No account needed. For life-threatening emergencies always call{" "}
          <strong className="text-primary">9141</strong>.
        </p>

        <Card className="mt-6">
          <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-base">
              <Siren className="size-4 text-primary" /> Incident details
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="name">Full name *</Label>
                  <Input
                    id="name"
                    value={name}
                    onChange={(e) => setName(sanitizeNameInput(e.target.value))}
                    placeholder="Abebe Kebede"
                  />
                  {name && errors.name && (
                    <p className="text-xs text-destructive">{errors.name}</p>
                  )}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="phone">Phone number *</Label>
                  <Input
                    id="phone"
                    inputMode="numeric"
                    value={phone}
                    onChange={(e) => setPhone(sanitizePhoneInput(e.target.value))}
                    placeholder="0912345678"
                  />
                  {phone && errors.phone && (
                    <p className="text-xs text-destructive">{errors.phone}</p>
                  )}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="alt">Alternative phone</Label>
                  <Input
                    id="alt"
                    inputMode="numeric"
                    value={altPhone}
                    onChange={(e) => setAltPhone(sanitizePhoneInput(e.target.value))}
                    placeholder="Optional"
                  />
                  {altPhone && errors.altPhone && (
                    <p className="text-xs text-destructive">{errors.altPhone}</p>
                  )}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="loc">Location *</Label>
                  <div className="flex gap-2">
                    <Input
                      id="loc"
                      value={location}
                      maxLength={LIMITS.location.max}
                      onChange={(e) => setLocation(e.target.value)}
                      placeholder="Bole Road, Adama"
                    />
                    <Button
                      type="button"
                      variant="outline"
                      className="shrink-0"
                      onClick={() =>
                        navigator.geolocation?.getCurrentPosition(
                          (p) => {
                            setCoords({ lat: p.coords.latitude, lng: p.coords.longitude });
                            if (!location)
                              setLocation(
                                `${p.coords.latitude.toFixed(4)}, ${p.coords.longitude.toFixed(4)}`,
                              );
                            toast.success("Location shared");
                          },
                          () => toast.error("Location unavailable"),
                        )
                      }
                    >
                      <MapPin className="size-4" />
                    </Button>
                  </div>
                  {coords && (
                    <p className="text-xs text-muted-foreground">
                      GPS: {coords.lat.toFixed(4)}, {coords.lng.toFixed(4)}
                    </p>
                  )}
                </div>
                <div className="space-y-1.5">
                  <Label>Emergency category *</Label>
                  <Select
                    value={category}
                    onValueChange={(v) => {
                      setCategory(v as Category);
                      setPriority(DEFAULT_PRIORITY[v as Category]);
                    }}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {CATEGORIES.map((c) => (
                        <SelectItem key={c} value={c}>
                          {c}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label>Priority</Label>
                  <Select value={priority} onValueChange={(v) => setPriority(v as Priority)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {PRIORITIES.map((p) => (
                        <SelectItem key={p} value={p}>
                          {p}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="desc">Description of the incident *</Label>
                <Textarea
                  id="desc"
                  rows={5}
                  value={description}
                  maxLength={LIMITS.description.max}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Describe what happened, who is involved and whether anyone is injured."
                />
                <div className="flex justify-between text-xs">
                  <span className="text-destructive">
                    {description && errors.description ? errors.description : ""}
                  </span>
                  <span className="text-muted-foreground">
                    {description.length}/{LIMITS.description.max}
                  </span>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="media">Photos, video or audio (max 10MB each)</Label>
                <Input
                  id="media"
                  type="file"
                  multiple
                  accept="image/*,video/*,audio/*"
                  onChange={(e) => {
                    const files = Array.from(e.target.files ?? []);
                    const ok = files.filter((f) => f.size <= MAX_BYTES);
                    if (ok.length !== files.length) toast.error("Some files exceed the 10MB limit");
                    setMedia((m) => [
                      ...m,
                      ...ok.map((f) => ({
                        id: uid(),
                        name: f.name,
                        type: f.type,
                        size: f.size,
                      })),
                    ]);
                  }}
                />
                {media.length > 0 && (
                  <ul className="space-y-1 text-xs text-muted-foreground">
                    {media.map((m) => (
                      <li key={m.id} className="flex items-center gap-1">
                        <Paperclip className="size-3" /> {m.name} ({Math.round(m.size / 1024)} KB)
                      </li>
                    ))}
                  </ul>
                )}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="human">Anti-bot check: what is 7 + 5? *</Label>
                <Input
                  id="human"
                  inputMode="numeric"
                  value={human}
                  maxLength={3}
                  onChange={(e) => setHuman(e.target.value.replace(/\D/g, ""))}
                  className="max-w-28"
                />
              </div>

              <Button type="submit" className="min-h-12 w-full text-base" disabled={submitting}>
                {submitting ? "Submitting…" : "Submit emergency report"}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </PublicShell>
  );
}
