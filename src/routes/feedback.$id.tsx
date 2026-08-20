import { useEffect, useState } from "react";
import { createFileRoute, useParams } from "@tanstack/react-router";
import { CheckCircle2, Paperclip, Star } from "lucide-react";
import { toast } from "sonner";
import { PublicShell } from "@/components/PublicShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { addFeedback, ensureSeed, getEvent, uid } from "@/lib/store";
import type { EventRecord, MediaItem } from "@/lib/types";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/feedback/$id")({
  head: () => ({
    meta: [
      { title: "Rate Your 9141 Experience — Adama City" },
      {
        name: "description",
        content: "Rate how the Adama City 9141 emergency call center handled your report.",
      },
      { property: "og:title", content: "Rate Your 9141 Experience" },
      {
        property: "og:description",
        content: "Share your feedback on the handling of your Adama City 9141 report.",
      },
    ],
  }),
  component: FeedbackPage,
});

const EMOJI = ["😠", "🙁", "😐", "🙂", "😃"];

function FeedbackPage() {
  const { id } = useParams({ from: "/feedback/$id" });
  const [event, setEvent] = useState<EventRecord | null | undefined>(undefined);
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState("");
  const [media, setMedia] = useState<MediaItem[]>([]);
  const [done, setDone] = useState(false);

  useEffect(() => {
    ensureSeed();
    setEvent(getEvent(id) ?? null);
  }, [id]);

  if (event === undefined) {
    return (
      <PublicShell>
        <div className="h-40 animate-pulse rounded-xl bg-muted" />
      </PublicShell>
    );
  }

  if (!event) {
    return (
      <PublicShell>
        <Card className="mx-auto max-w-lg">
          <CardContent className="p-8 text-center text-muted-foreground">
            We could not find report <strong>{id}</strong>. Please check the link in your SMS.
          </CardContent>
        </Card>
      </PublicShell>
    );
  }

  if (done || event.feedback) {
    return (
      <PublicShell>
        <Card className="mx-auto max-w-lg animate-in fade-in zoom-in-95">
          <CardContent className="space-y-3 p-8 text-center">
            <CheckCircle2 className="mx-auto size-12 text-primary" />
            <h1 className="font-display text-2xl font-bold">Thank you for your feedback</h1>
            <p className="text-sm text-muted-foreground">
              Your rating for {event.id} has been recorded and shared with the 9141 supervisors.
            </p>
            <p className="text-2xl">
              {"⭐".repeat(event.feedback?.rating ?? rating)}
            </p>
          </CardContent>
        </Card>
      </PublicShell>
    );
  }

  return (
    <PublicShell>
      <div className="mx-auto max-w-lg">
        <h1 className="font-display text-3xl font-bold">Rate your experience</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Report {event.id} · {event.category} · {event.department}
        </p>

        <Card className="mt-6">
          <CardHeader className="pb-2">
            <CardTitle className="text-base">How did we do?</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex justify-center gap-2">
              {[1, 2, 3, 4, 5].map((n) => (
                <button
                  key={n}
                  type="button"
                  onClick={() => setRating(n)}
                  aria-label={`${n} star`}
                  className={cn(
                    "flex flex-col items-center gap-1 rounded-xl px-2 py-2 transition hover:bg-accent",
                    rating === n && "bg-accent ring-2 ring-ring",
                  )}
                >
                  <span className="text-2xl">{EMOJI[n - 1]}</span>
                  <Star
                    className={cn(
                      "size-5",
                      n <= rating ? "fill-warning text-warning" : "text-muted-foreground",
                    )}
                  />
                </button>
              ))}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="comment">Comments</Label>
              <Textarea
                id="comment"
                rows={4}
                maxLength={300}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Tell us what went well or what we should improve."
              />
              <p className="text-right text-xs text-muted-foreground">{comment.length}/300</p>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="evidence">Attach evidence (optional)</Label>
              <Input
                id="evidence"
                type="file"
                multiple
                accept="image/*,video/*,audio/*"
                onChange={(e) =>
                  setMedia(
                    Array.from(e.target.files ?? []).map((f) => ({
                      id: uid(),
                      name: f.name,
                      type: f.type,
                      size: f.size,
                    })),
                  )
                }
              />
              {media.map((m) => (
                <p key={m.id} className="flex items-center gap-1 text-xs text-muted-foreground">
                  <Paperclip className="size-3" /> {m.name}
                </p>
              ))}
            </div>

            <Button
              className="min-h-11 w-full"
              onClick={() => {
                if (!rating) {
                  toast.error("Please select a star rating");
                  return;
                }
                addFeedback(event.id, rating, comment.trim(), media);
                setDone(true);
                toast.success("Feedback submitted — thank you!");
              }}
            >
              Submit feedback
            </Button>
          </CardContent>
        </Card>
      </div>
    </PublicShell>
  );
}
