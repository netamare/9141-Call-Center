import { useState } from "react";
import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { Crosshair, Paperclip, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
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
import { useAuth } from "@/lib/auth";
import { addEvent, getQueue, logAudit, nextEventId, setQueue, uid } from "@/lib/store";
import {
  CATEGORIES,
  DEPARTMENT_BY_CATEGORY,
  PRIORITIES,
  PRIORITY_LABEL,
  type Category,
  type EventRecord,
  type MediaItem,
  type Priority,
} from "@/lib/types";
import {
  LIMITS,
  sanitizeNameInput,
  sanitizePhoneInput,
  validateName,
  validatePhone,
  validateText,
} from "@/lib/validation";


export const Route = createFileRoute("/events/new")({
  head: () => ({
    meta: [
      { title: "Register New Event — Adama 9141" },
      { name: "description", content: "Register a new emergency call for Adama City 9141." },
      { property: "og:title", content: "Register New Event — Adama 9141" },
      { property: "og:description", content: "Register a new emergency call for Adama City 9141." },
    ],
  }),
  component: NewEventPage,
});

const MAX = 10 * 1024 * 1024;

function NewEventPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    callerName: "",
    phone: "",
    altPhone: "",
    gender: "Male",
    address: "",
    location: "",
    lat: 8.5414,
    lng: 39.2689,
    category: "Traffic Accident" as Category,
    subCategory: "",
    priority: "P2" as Priority,
    description: "",
  });
  const [media, setMedia] = useState<MediaItem[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [locating, setLocating] = useState(false);

  const set = (k: string, v: string | number) => setForm((f) => ({ ...f, [k]: v }));

  const getLocation = () => {
    if (!navigator.geolocation) {
      toast.error("Geolocation is not supported on this device");
      return;
    }
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setForm((f) => ({
          ...f,
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          location: f.location || `${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)}`,
        }));
        setLocating(false);
        toast.success("Current location captured");
      },
      () => {
        setLocating(false);
        toast.error("Could not read your location");
      },
    );
  };

  const onFiles = (files: FileList | null) => {
    if (!files) return;
    const accepted: MediaItem[] = [];
    Array.from(files).forEach((f) => {
      if (f.size > MAX) {
        toast.error(`${f.name} exceeds the 10MB limit`);
        return;
      }
      accepted.push({ id: uid(), name: f.name, type: f.type || "application/octet-stream", size: f.size });
    });
    if (accepted.length) {
      setMedia((m) => [...m, ...accepted]);
      toast.success(`${accepted.length} file(s) attached`);
    }
  };

  const validate = () => {
    const e: Record<string, string> = {};
    const nameErr = validateName(form.callerName, "Caller name");
    if (nameErr) e["callerName"] = nameErr;
    const phoneErr = validatePhone(form.phone);
    if (phoneErr) e["phone"] = phoneErr;
    const altErr = validatePhone(form.altPhone, false);
    if (altErr) e["altPhone"] = altErr;
    const locErr = validateText(form.location, "Location", { min: 3, max: LIMITS.location.max });
    if (locErr) e["location"] = locErr;
    const addrErr = validateText(form.address, "Address", {
      max: LIMITS.address.max,
      required: false,
    });
    if (addrErr) e["address"] = addrErr;
    const descErr = validateText(form.description, "Description", {
      min: LIMITS.description.min,
      max: LIMITS.description.max,
    });
    if (descErr) e["description"] = descErr;

    setErrors(e);
    return Object.keys(e).length === 0;
  };


  const submit = (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!validate()) {
      toast.error("Please fix the highlighted fields");
      return;
    }
    setSaving(true);
    const record: EventRecord = {
      id: nextEventId(form.category),
      callerName: form.callerName.trim(),
      phone: form.phone.trim(),
      altPhone: form.altPhone.trim() || undefined,
      gender: form.gender as EventRecord["gender"],
      address: form.address.trim(),
      location: form.location.trim(),
      lat: form.lat,
      lng: form.lng,
      category: form.category,
      subCategory: form.subCategory.trim(),
      priority: form.priority,
      description: form.description.trim(),
      department: DEPARTMENT_BY_CATEGORY[form.category],
      status: "New",
      createdAt: new Date().toISOString(),
      operator: user?.fullName ?? "System",
      media,
      escalations: [],
      messages: [],
    };

    window.setTimeout(() => {
      if (typeof navigator !== "undefined" && !navigator.onLine) {
        setQueue([...getQueue(), record]);
        toast.warning("You are offline — event queued for sync");
      } else {
        addEvent(record);
        logAudit(user?.fullName ?? "System", `Registered event ${record.id}`);
        toast.success(`Event ${record.id} registered · ${record.department}`);
      }
      setSaving(false);
      navigate({ to: "/events" });
    }, 400);
  };

  return (
    <AppShell title="Register New Event">
      <form onSubmit={submit} className="mx-auto max-w-4xl space-y-4">
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Caller information</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>Caller name *</Label>
              <Input
                value={form.callerName}
                onChange={(e) => set("callerName", sanitizeNameInput(e.target.value))}
                maxLength={LIMITS.name.max}
                placeholder="Full name (letters only)"
              />
              {errors['callerName'] && (
                <p className="text-xs text-destructive">{errors['callerName']}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>Phone number *</Label>
              <Input
                value={form.phone}
                inputMode="tel"
                onChange={(e) => set("phone", sanitizePhoneInput(e.target.value))}
                placeholder="0912345678"
              />
              {errors['phone'] && <p className="text-xs text-destructive">{errors['phone']}</p>}
            </div>

            <div className="space-y-2">
              <Label>Alternative phone</Label>
              <Input
                value={form.altPhone}
                inputMode="tel"
                onChange={(e) => set("altPhone", sanitizePhoneInput(e.target.value))}
              />
              {errors['altPhone'] && (
                <p className="text-xs text-destructive">{errors['altPhone']}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Gender</Label>
              <Select value={form.gender} onValueChange={(v) => set("gender", v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="Male">Male</SelectItem>
                  <SelectItem value="Female">Female</SelectItem>
                  <SelectItem value="Other">Other</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Incident location</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>Address</Label>
              <Input
                value={form.address}
                maxLength={LIMITS.address.max}
                onChange={(e) => set("address", e.target.value)}
              />
              {errors['address'] && <p className="text-xs text-destructive">{errors['address']}</p>}
            </div>
            <div className="space-y-2">
              <Label>Location *</Label>
              <div className="flex gap-2">
                <Input
                  value={form.location}
                  maxLength={LIMITS.location.max}
                  onChange={(e) => set("location", e.target.value)}
                  placeholder="Bole, Adama"
                />

                <Button type="button" onClick={getLocation} disabled={locating} className="min-h-11 shrink-0">
                  <Crosshair className="size-4" />
                  <span className="hidden sm:inline">{locating ? "…" : "Get location"}</span>
                </Button>
              </div>
              {errors['location'] && <p className="text-xs text-destructive">{errors['location']}</p>}
              <p className="text-xs text-muted-foreground">
                Coordinates: {form.lat.toFixed(4)}, {form.lng.toFixed(4)}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Emergency details</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label>Category</Label>
              <Select value={form.category} onValueChange={(v) => set("category", v)}>
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
              <p className="text-xs text-primary">
                Auto-assigned to {DEPARTMENT_BY_CATEGORY[form.category]}
              </p>
            </div>
            <div className="space-y-2">
              <Label>Sub-category</Label>
              <Input
                value={form.subCategory}
                maxLength={LIMITS.subCategory.max}
                onChange={(e) => set("subCategory", e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label>Priority</Label>
              <Select value={form.priority} onValueChange={(v) => set("priority", v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {PRIORITIES.map((p) => (
                    <SelectItem key={p} value={p}>
                      {PRIORITY_LABEL[p]}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2 sm:col-span-2">
              <Label>Description *</Label>
              <Textarea
                rows={4}
                value={form.description}
                maxLength={LIMITS.description.max}
                onChange={(e) => set("description", e.target.value)}
                placeholder="What is happening at the scene? (min 20 characters)"
              />
              <div className="flex items-center justify-between text-xs">
                <span className="text-destructive">{errors['description'] ?? ""}</span>
                <span
                  className={
                    form.description.trim().length < LIMITS.description.min
                      ? "text-muted-foreground"
                      : "text-primary"
                  }
                >
                  {form.description.length}/{LIMITS.description.max}
                </span>
              </div>
            </div>

          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Media evidence</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <label className="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-primary/40 bg-secondary/40 p-4 text-center">
              <Paperclip className="size-5 text-primary" />
              <span className="text-sm font-medium">Upload photos, videos, audio or PDF</span>
              <span className="text-xs text-muted-foreground">Multiple files, max 10MB each</span>
              <input
                type="file"
                multiple
                accept="image/jpeg,image/png,video/mp4,audio/mpeg,application/pdf"
                className="hidden"
                onChange={(e) => onFiles(e.target.files)}
              />
            </label>
            {media.map((m) => (
              <div
                key={m.id}
                className="flex items-center justify-between rounded-lg border bg-card px-3 py-2 text-sm"
              >
                <span className="truncate">
                  {m.name} · {(m.size / 1024).toFixed(0)} KB
                </span>
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  onClick={() => setMedia((x) => x.filter((f) => f.id !== m.id))}
                >
                  <Trash2 className="size-4 text-destructive" />
                </Button>
              </div>
            ))}
          </CardContent>
        </Card>

        <div className="flex gap-3">
          <Button type="submit" className="min-h-11 flex-1" disabled={saving}>
            {saving ? "Registering…" : "Register Emergency Event"}
          </Button>
          <Button
            type="button"
            variant="outline"
            className="min-h-11"
            onClick={() => navigate({ to: "/events" })}
          >
            Cancel
          </Button>
        </div>
      </form>
    </AppShell>
  );
}
