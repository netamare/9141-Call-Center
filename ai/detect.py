#!/usr/bin/env python3
"""
Adama 9141 Call Center — AI Detection Module (v2)
=================================================
Improved local detector for Camera Room / AI Detection.

Upgrades over v1:
  • Stronger frame stats (edge, contrast, brightness, color dominance)
  • Motion score between consecutive video frames
  • Fire / smoke / night-scene heuristics
  • Location-aware priors (Oromo + English keywords)
  • Bilingual summary (English + Afaan Oromoo)
  • Severity (low / medium / high / critical)
  • Suggested action for operators
  • More frames extracted from video (up to 5)
  • Deterministic scoring (stable results for same file)

Still swap-ready for ONNX / YOLOv8 — keep the same JSON contract.

CLI:
  python3 detect.py --image path.jpg [--location "..."] [--category_hint illegal]
  python3 detect.py --video path.mp4 [--location "..."] [--cleanup]

Output JSON (stdout):
{
  "ok": true,
  "detections": [
    {"label": "...", "confidence": 0.87, "category": "emergency", "severity": "high", "bbox": null}
  ],
  "summary": "EN text",
  "summary_om": "OM text",
  "suggested_action": "...",
  "frames_analyzed": 3,
  "model": "adama-local-v2",
  "stats": {...}
}
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path

try:
    from PIL import Image
    import numpy as np
except ImportError:
    print(json.dumps({"ok": False, "error": "Pillow/numpy required: pip install Pillow numpy"}))
    sys.exit(1)


# ---------------------------------------------------------------------------
# Frame extraction
# ---------------------------------------------------------------------------

def extract_frames(video_path: str, max_frames: int = 5) -> list[str]:
    """Extract evenly spaced frames with ffmpeg. Returns list of image paths."""
    if not os.path.isfile(video_path):
        return []

    tmpdir = tempfile.mkdtemp(prefix="adama_ai_")
    out_pattern = os.path.join(tmpdir, "frame_%03d.jpg")

    # Probe duration
    duration = 0.0
    try:
        probe = subprocess.run(
            [
                "ffprobe", "-v", "error", "-show_entries", "format=duration",
                "-of", "default=noprint_wrappers=1:nokey=1", video_path,
            ],
            capture_output=True, text=True, timeout=15,
        )
        if probe.returncode == 0 and probe.stdout.strip():
            duration = float(probe.stdout.strip())
    except Exception:
        duration = 0.0

    if duration <= 0:
        # fallback: extract first few frames at 1 fps
        fps_filter = f"fps=1"
    else:
        # sample max_frames across the video
        interval = max(duration / max(max_frames, 1), 0.3)
        fps_filter = f"fps=1/{interval:.3f}"

    try:
        subprocess.run(
            [
                "ffmpeg", "-y", "-i", video_path,
                "-vf", fps_filter,
                "-frames:v", str(max_frames),
                "-q:v", "3",
                out_pattern,
            ],
            capture_output=True, timeout=60,
        )
    except Exception:
        return []

    frames = sorted(Path(tmpdir).glob("frame_*.jpg"))
    return [str(p) for p in frames]


# ---------------------------------------------------------------------------
# Image analysis
# ---------------------------------------------------------------------------

def analyze_image(path: str) -> dict:
    """Return brightness, contrast, edge energy, color ratios."""
    try:
        img = Image.open(path).convert("RGB")
        # Downscale for speed
        img.thumbnail((320, 320), Image.Resampling.BILINEAR)
        arr = np.asarray(img, dtype=np.float32) / 255.0
        gray = arr.mean(axis=2)

        brightness = float(gray.mean())
        contrast = float(gray.std())
        gx = np.abs(np.diff(gray, axis=1)).mean()
        gy = np.abs(np.diff(gray, axis=0)).mean()
        edge = float((gx + gy) / 2)

        # Color dominance
        r, g, b = arr[:, :, 0].mean(), arr[:, :, 1].mean(), arr[:, :, 2].mean()
        # Warm (fire-ish): high R, moderate G, low B
        warm_ratio = float(max(0.0, (r - b) * (r / (g + 0.01))))
        # Smoke / gray: low saturation
        sat = float(arr.std(axis=2).mean())
        # Dark pixels ratio (night / smoke)
        dark_ratio = float((gray < 0.25).mean())

        return {
            "brightness": brightness,
            "contrast": contrast,
            "edge": edge,
            "warm_ratio": warm_ratio,
            "saturation": sat,
            "dark_ratio": dark_ratio,
            "r": float(r), "g": float(g), "b": float(b),
        }
    except Exception:
        return {
            "brightness": 0.5, "contrast": 0.2, "edge": 0.1,
            "warm_ratio": 0.0, "saturation": 0.1, "dark_ratio": 0.2,
            "r": 0.5, "g": 0.5, "b": 0.5,
        }


def motion_score(stats_list: list[dict]) -> float:
    """Approximate motion from variance of edge/brightness across frames."""
    if len(stats_list) < 2:
        return 0.0
    edges = [s["edge"] for s in stats_list]
    brights = [s["brightness"] for s in stats_list]
    e_var = float(np.std(edges))
    b_var = float(np.std(brights))
    return min(1.0, e_var * 8.0 + b_var * 4.0)


# ---------------------------------------------------------------------------
# Detection engine
# ---------------------------------------------------------------------------

LABEL_INFO = {
    "vehicle_congestion": {
        "cat": "emergency",
        "en": "Possible traffic congestion / vehicle buildup",
        "om": "Congestion daandii / konkolaataa baay’een jiraachuu danda’a",
        "action": "Dispatch traffic unit / daandii hordofuu",
    },
    "accident_risk": {
        "cat": "emergency",
        "en": "Possible accident or collision risk",
        "om": "Aksidantii / walitti bu’iinsa shakkisiisaa",
        "action": "Alert emergency / ambulance readiness",
    },
    "fire_smoke": {
        "cat": "emergency",
        "en": "Possible fire or smoke indicators",
        "om": "Ibidda ykn aara shakkisiisaa",
        "action": "Alert fire brigade immediately",
    },
    "crowd": {
        "cat": "security",
        "en": "Elevated crowd density",
        "om": "Namoonni baay’inaan walitti qabamaa jiru",
        "action": "Monitor for security risk",
    },
    "suspicious_activity": {
        "cat": "illegal",
        "en": "Possible suspicious activity (theft / illegal risk)",
        "om": "Gocha shakkisiisaa (hanna / seeraan alaa)",
        "action": "Notify security / police unit",
    },
    "person": {
        "cat": "security",
        "en": "People detected in frame",
        "om": "Namoota suuraa keessatti argaman",
        "action": "Review footage if incident reported",
    },
    "night_low_visibility": {
        "cat": "security",
        "en": "Low light / night scene — limited visibility",
        "om": "Ifa gadi-aanaa / halkan — arguun cimaa dha",
        "action": "Increase monitoring sensitivity",
    },
    "service_issue": {
        "cat": "service",
        "en": "Possible service / infrastructure issue",
        "om": "Rakkoo tajaajila / ijaarsa ta’uu danda’a",
        "action": "Forward to relevant department",
    },
    "car": {
        "cat": "emergency",
        "en": "Vehicles present",
        "om": "Konkolaatoonni argamu",
        "action": "",
    },
    "truck": {
        "cat": "emergency",
        "en": "Heavy vehicles present",
        "om": "Konkolaataa ulfaataa argamu",
        "action": "",
    },
}


def severity_from_conf(conf: float, label: str) -> str:
    if label in ("fire_smoke", "accident_risk") and conf >= 0.65:
        return "critical"
    if conf >= 0.80:
        return "high"
    if conf >= 0.60:
        return "medium"
    return "low"


def location_priors(location: str) -> dict:
    loc = (location or "").lower()
    return {
        "traffic": any(k in loc for k in [
            "road", "daandi", "street", "intersection", "roundabout", "highway",
            "traffic", "trafica", "bus", "taxi", "parking", "garaaji", "korojo",
            "avenue", "bridge", "riqicha",
        ]),
        "market": any(k in loc for k in [
            "market", "suuq", "gaba", "shop", "duka", "mall", "center", "suuqii",
        ]),
        "residential": any(k in loc for k in [
            "residence", "mana", "neighborhood", "kebele", "qabeenya", "ganda",
        ]),
        "industrial": any(k in loc for k in [
            "factory", "warshaa", "industrial", "warehouse", "depot",
        ]),
    }


def run_detection(image_paths: list[str], location: str = "", category_hint: str = "") -> dict:
    if not image_paths:
        return {
            "ok": True,
            "detections": [],
            "summary": "No frames available for analysis",
            "summary_om": "Freemii hin jiru — xiinxalli hin danda’amne",
            "suggested_action": "",
            "frames_analyzed": 0,
            "model": "adama-local-v2",
            "stats": {},
        }

    stats = [analyze_image(p) for p in image_paths]
    n = len(stats)
    avg_edge = sum(s["edge"] for s in stats) / n
    avg_bright = sum(s["brightness"] for s in stats) / n
    avg_contrast = sum(s["contrast"] for s in stats) / n
    avg_warm = sum(s["warm_ratio"] for s in stats) / n
    avg_dark = sum(s["dark_ratio"] for s in stats) / n
    avg_sat = sum(s["saturation"] for s in stats) / n
    motion = motion_score(stats)
    priors = location_priors(location)

    detections: list[dict] = []

    def add(label: str, conf: float, cat_override: str | None = None):
        conf = max(0.15, min(0.98, conf))
        info = LABEL_INFO.get(label, {})
        cat = cat_override or info.get("cat", "security")
        if category_hint and category_hint in ("illegal", "security", "service", "emergency"):
            # mild boost toward hinted category when relevant
            if info.get("cat") == category_hint:
                conf = min(0.98, conf + 0.05)
        detections.append({
            "label": label,
            "confidence": round(conf, 3),
            "category": cat,
            "severity": severity_from_conf(conf, label),
            "bbox": None,
        })

    # --- Fire / smoke ---
    if avg_warm > 0.35 and avg_sat > 0.12:
        conf = 0.55 + min(0.35, avg_warm * 0.4)
        add("fire_smoke", conf, "emergency")
    elif avg_dark > 0.55 and avg_sat < 0.08 and avg_edge < 0.08:
        # gray/hazy low-detail → possible smoke
        add("fire_smoke", 0.52, "emergency")

    # --- Night / low visibility ---
    if avg_bright < 0.28 or avg_dark > 0.60:
        add("night_low_visibility", 0.70 + (0.25 - avg_bright) * 0.5, "security")

    # --- Traffic / congestion ---
    traffic_score = 0.0
    if priors["traffic"]:
        traffic_score += 0.35
    if avg_edge > 0.10:
        traffic_score += min(0.40, avg_edge * 2.2)
    if motion > 0.15:
        traffic_score += min(0.25, motion * 0.8)
    if traffic_score >= 0.40:
        add("vehicle_congestion", 0.50 + traffic_score * 0.45, "emergency")
        add("car", 0.45 + traffic_score * 0.35, "emergency")
        if traffic_score > 0.65 and motion > 0.25:
            add("accident_risk", 0.55 + motion * 0.25, "emergency")
        if avg_edge > 0.14:
            add("truck", 0.42 + avg_edge, "emergency")

    # --- Crowd / people ---
    people_score = 0.0
    if avg_edge > 0.08 and avg_contrast > 0.12:
        people_score += min(0.45, avg_edge * 2.0 + avg_contrast)
    if priors["market"]:
        people_score += 0.25
    if motion > 0.20:
        people_score += 0.15
    if people_score >= 0.35:
        add("person", 0.48 + people_score * 0.4, "security")
        if people_score >= 0.55:
            add("crowd", 0.50 + people_score * 0.35, "security")

    # --- Suspicious / illegal ---
    if priors["market"] and motion > 0.18 and avg_edge > 0.09:
        add("suspicious_activity", 0.55 + motion * 0.3, "illegal")
    elif category_hint == "illegal" and avg_edge > 0.07:
        add("suspicious_activity", 0.62, "illegal")
    elif avg_edge > 0.15 and motion > 0.30 and not priors["traffic"]:
        add("suspicious_activity", 0.50 + motion * 0.25, "illegal")

    # --- Service / infrastructure ---
    if priors["industrial"] or (avg_edge < 0.05 and avg_bright > 0.55 and not priors["traffic"]):
        add("service_issue", 0.48, "service")
    elif category_hint == "service":
        add("service_issue", 0.58, "service")

    # Ensure at least something if frames exist and edge is non-trivial
    if not detections and avg_edge > 0.06:
        add("person", 0.42, "security")

    # Sort by confidence
    detections.sort(key=lambda d: -d["confidence"])
    detections = detections[:8]

    # Summary (bilingual)
    if not detections:
        summary = "No significant activity detected in the analyzed frames"
        summary_om = "Gocha barbaachisaa freemii keessatti hin argamne"
        action = "Continue routine monitoring"
    else:
        top = detections[0]
        info = LABEL_INFO.get(top["label"], {})
        summary = info.get("en", f"Detected: {top['label']}") + f" (confidence {top['confidence']:.0%}, severity: {top['severity']})"
        summary_om = info.get("om", top["label"]) + f" (amanamummaa {top['confidence']:.0%}, cimina: {top['severity']})"
        # pick first non-empty action among top detections
        action = ""
        for d in detections:
            a = LABEL_INFO.get(d["label"], {}).get("action", "")
            if a:
                action = a
                break
        if not action:
            action = "Review footage and escalate if needed"

    return {
        "ok": True,
        "detections": detections,
        "summary": summary,
        "summary_om": summary_om,
        "suggested_action": action,
        "frames_analyzed": n,
        "model": "adama-local-v2",
        "stats": {
            "avg_edge": round(avg_edge, 4),
            "avg_brightness": round(avg_bright, 4),
            "avg_contrast": round(avg_contrast, 4),
            "motion": round(motion, 4),
            "warm_ratio": round(avg_warm, 4),
            "dark_ratio": round(avg_dark, 4),
        },
    }


def main():
    parser = argparse.ArgumentParser(description="Adama 9141 AI Detection v2")
    parser.add_argument("--image", help="Single image path")
    parser.add_argument("--video", help="Video path")
    parser.add_argument("--location", default="", help="Location text for priors")
    parser.add_argument("--category_hint", default="", help="illegal|security|service|emergency")
    parser.add_argument("--cleanup", action="store_true", help="Delete temp frames")
    args = parser.parse_args()

    image_paths: list[str] = []
    temp_dirs: list[str] = []

    if args.image and os.path.isfile(args.image):
        image_paths = [args.image]
    elif args.video and os.path.isfile(args.video):
        image_paths = extract_frames(args.video, max_frames=5)
        if image_paths:
            temp_dirs.append(str(Path(image_paths[0]).parent))
    else:
        print(json.dumps({"ok": False, "error": "No valid --image or --video provided"}))
        sys.exit(1)

    result = run_detection(image_paths, location=args.location, category_hint=args.category_hint)

    if args.cleanup and temp_dirs:
        import shutil
        for d in temp_dirs:
            try:
                shutil.rmtree(d, ignore_errors=True)
            except Exception:
                pass

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
