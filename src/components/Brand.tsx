import { PhoneCall, ShieldAlert } from "lucide-react";
import logo from "@/assets/adama-city-logo.jpg.asset.json";
import { cn } from "@/lib/utils";

type Size = "sm" | "md" | "lg";

const LOGO: Record<Size, string> = {
  sm: "size-10",
  md: "size-14",
  lg: "size-20 sm:size-24",
};
const NUM: Record<Size, string> = {
  sm: "text-xl",
  md: "text-3xl",
  lg: "text-5xl sm:text-6xl",
};

/** Prominent 9141 brand lockup: city logo + emergency 9141 + tagline. */
export function Brand({
  size = "md",
  onDark = false,
  tagline = "Adama City Emergency Call Center",
  className,
}: {
  size?: Size;
  onDark?: boolean;
  tagline?: string | false;
  className?: string;
}) {
  return (
    <div className={cn("flex items-center gap-3", className)}>
      <span className="relative shrink-0">
        <img
          src={logo.url}
          alt="Adama City Administration logo"
          className={cn(
            LOGO[size],
            "rounded-full bg-white object-contain p-0.5 shadow-brand ring-2 ring-primary/40 transition-transform duration-300 hover:scale-105",
          )}
        />
        <ShieldAlert
          className={cn(
            "absolute -bottom-1 -right-1 rounded-full bg-primary p-0.5 text-primary-foreground",
            size === "lg" ? "size-7" : "size-5",
          )}
        />
      </span>
      <div className="leading-tight">
        <p
          className={cn(
            "font-display font-extrabold tracking-tight drop-shadow-sm",
            NUM[size],
            onDark ? "text-white" : "text-foreground",
          )}
        >
          <PhoneCall
            className={cn("mr-1 inline-block", size === "lg" ? "size-8" : "size-5")}
            strokeWidth={2.5}
          />
          9141
        </p>
        {tagline !== false && (
          <p
            className={cn(
              "font-medium",
              size === "lg" ? "text-sm sm:text-base" : "text-xs",
              onDark ? "text-white/85" : "text-muted-foreground",
            )}
          >
            {tagline}
          </p>
        )}
      </div>
    </div>
  );
}
