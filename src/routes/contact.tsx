import { useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { Clock, Facebook, Mail, MapPin, Phone, Send, Twitter } from "lucide-react";
import { toast } from "sonner";
import { PublicShell } from "@/components/PublicShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { logAudit } from "@/lib/store";
import { sanitizeNameInput, sanitizePhoneInput, validateName, validatePhone, validateText } from "@/lib/validation";

export const Route = createFileRoute("/contact")({
  head: () => ({
    meta: [
      { title: "Contact Adama City 9141 Emergency Call Center" },
      {
        name: "description",
        content:
          "Phone numbers, email, office hours and location of the Adama City 9141 emergency call center headquarters.",
      },
      { property: "og:title", content: "Contact Adama City 9141" },
      {
        property: "og:description",
        content: "Reach the Adama City 9141 emergency call center by phone, email or in person.",
      },
    ],
  }),
  component: ContactPage,
});

function ContactPage() {
  const [form, setForm] = useState({ name: "", email: "", phone: "", subject: "", message: "" });
  const [sending, setSending] = useState(false);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const err =
      validateName(form.name, "Name") ??
      (/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(form.email) ? null : "Enter a valid email address") ??
      validatePhone(form.phone) ??
      validateText(form.subject, "Subject", { min: 3, max: 80 }) ??
      validateText(form.message, "Message", { min: 20, max: 500 });
    if (err) {
      toast.error(err);
      return;
    }
    setSending(true);
    window.setTimeout(() => {
      setSending(false);
      logAudit("Public visitor", `Contact message: ${form.subject}`);
      setForm({ name: "", email: "", phone: "", subject: "", message: "" });
      toast.success("Message sent — the 9141 office will reply shortly.");
    }, 600);
  };

  return (
    <PublicShell>
      <h1 className="font-display text-3xl font-bold">Contact Us</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        For emergencies dial <strong className="text-primary">9141</strong>. For all other
        enquiries use the form below.
      </p>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1.2fr_1fr]">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Send us a message</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="cname">Name</Label>
                  <Input
                    id="cname"
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: sanitizeNameInput(e.target.value) })}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="cemail">Email</Label>
                  <Input
                    id="cemail"
                    type="email"
                    maxLength={120}
                    value={form.email}
                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="cphone">Phone</Label>
                  <Input
                    id="cphone"
                    inputMode="numeric"
                    value={form.phone}
                    onChange={(e) => setForm({ ...form, phone: sanitizePhoneInput(e.target.value) })}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="csubject">Subject</Label>
                  <Input
                    id="csubject"
                    maxLength={80}
                    value={form.subject}
                    onChange={(e) => setForm({ ...form, subject: e.target.value })}
                  />
                </div>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="cmessage">Message</Label>
                <Textarea
                  id="cmessage"
                  rows={5}
                  maxLength={500}
                  value={form.message}
                  onChange={(e) => setForm({ ...form, message: e.target.value })}
                />
                <p className="text-right text-xs text-muted-foreground">
                  {form.message.length}/500
                </p>
              </div>
              <Button type="submit" className="min-h-11 w-full" disabled={sending}>
                {sending ? "Sending…" : "Send message"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardContent className="space-y-3 p-6 text-sm">
              <p className="font-display text-2xl font-bold text-primary">9141</p>
              <p className="flex items-center gap-2">
                <Phone className="size-4 text-primary" /> +251-221-112-233
              </p>
              <p className="flex items-center gap-2">
                <Mail className="size-4 text-primary" /> info@adama9141.gov.et
              </p>
              <p className="flex items-center gap-2">
                <MapPin className="size-4 text-primary" /> 9141 HQ, City Center, Adama, Oromia
              </p>
              <p className="flex items-start gap-2">
                <Clock className="mt-0.5 size-4 text-primary" />
                <span>
                  Emergency line: 24/7
                  <br />
                  Office hours: Mon–Fri, 8:00 AM – 5:00 PM
                </span>
              </p>
              <div className="flex gap-3 pt-2 text-primary">
                <a href="https://facebook.com" target="_blank" rel="noreferrer" aria-label="Facebook">
                  <Facebook className="size-5" />
                </a>
                <a href="https://twitter.com" target="_blank" rel="noreferrer" aria-label="Twitter">
                  <Twitter className="size-5" />
                </a>
                <a href="https://telegram.org" target="_blank" rel="noreferrer" aria-label="Telegram">
                  <Send className="size-5" />
                </a>
              </div>
            </CardContent>
          </Card>

          <Card className="overflow-hidden">
            <iframe
              title="Adama City 9141 headquarters location"
              src="https://www.google.com/maps?q=Adama%20City%20Administration%2C%20Adama%2C%20Ethiopia&output=embed"
              className="h-64 w-full border-0"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          </Card>
        </div>
      </div>
    </PublicShell>
  );
}
