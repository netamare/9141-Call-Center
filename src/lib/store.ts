import {
  CATEGORIES,
  CATEGORY_PREFIX,
  DEPARTMENT_BY_CATEGORY,
  type AuditEntry,
  type Category,
  type EventRecord,
  type Notification,
  type Priority,
  type Settings,
  type Status,
  type User,
  type SmsLog,
  type FollowUp,
  type OfficerNote,
  ROLE_CATEGORY,
} from "./types";

const KEY = {
  events: "a9141_events",
  users: "a9141_users",
  notifications: "a9141_notifications",
  audit: "a9141_audit",
  settings: "a9141_settings",
  session: "a9141_session",
  searches: "a9141_searches",
  queue: "a9141_queue",
};

export const uid = () => Math.random().toString(36).slice(2, 10);

const isBrowser = () => typeof window !== "undefined";

function read<T>(key: string, fallback: T): T {
  if (!isBrowser()) return fallback;
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

function write<T>(key: string, value: T) {
  if (!isBrowser()) return;
  localStorage.setItem(key, JSON.stringify(value));
  window.dispatchEvent(new CustomEvent("a9141:change", { detail: key }));
}

export const DEFAULT_SETTINGS: Settings = {
  systemName: "Adama City 9141 Emergency Call Center",
  timezone: "Africa/Addis_Ababa",
  dateFormat: "dd/MM/yyyy HH:mm",
  departments: [
    "Traffic Police Department",
    "Fire Brigade Department",
    "Police Department",
    "Administrative Office",
  ],
  escalation: { P1: 5, P2: 15, P3: 60, P4: 1440 },
  sla: { P1: 10, P2: 30, P3: 120, P4: 1440 },
  smsTemplate:
    "Dear {caller}, your report {eventId} is now {status}. Handled by {department}. Adama 9141.",
  emailTemplate: "Report {eventId} update: {status}.",
};

export const DEFAULT_USERS: User[] = [
  {
    id: "u1",
    fullName: "Alemeshet Ketema",
    email: "admin@adama9141.gov.et",
    phone: "+251911000001",
    username: "admin",
    password: "admin123",
    role: "admin",
    active: true,
  },
  {
    id: "u2",
    fullName: "Netsanet Amare",
    email: "operator@adama9141.gov.et",
    phone: "+251911000002",
    username: "operator",
    password: "op123",
    role: "operator",
    active: true,
  },
  {
    id: "u3",
    fullName: "Mohammedareb Ahmed",
    email: "supervisor@adama9141.gov.et",
    phone: "+251911000003",
    username: "supervisor",
    password: "sup123",
    role: "supervisor",
    active: true,
  },
  {
    id: "u4",
    fullName: "Naol Abdulkadir",
    email: "officehead@adama9141.gov.et",
    phone: "+251911000004",
    username: "officehead",
    password: "off123",
    role: "officer",
    active: true,
  },
  {
    id: "u5",
    fullName: "Traffic Police Department",
    email: "traffic@9141.com",
    phone: "+251911000005",
    username: "traffic",
    password: "traffic123",
    role: "traffic",
    active: true,
  },
  {
    id: "u6",
    fullName: "Fire Brigade Department",
    email: "fire@9141.com",
    phone: "+251911000006",
    username: "fire",
    password: "fire123",
    role: "fire",
    active: true,
  },
  {
    id: "u7",
    fullName: "Police Department",
    email: "police@9141.com",
    phone: "+251911000007",
    username: "police",
    password: "police123",
    role: "police",
    active: true,
  },
  {
    id: "u8",
    fullName: "Administrative Office",
    email: "adminoffice@9141.com",
    phone: "+251911000008",
    username: "adminoffice",
    password: "adminoffice123",
    role: "adminoffice",
    active: true,
  },
];


const LOCATIONS: { name: string; lat: number; lng: number }[] = [
  { name: "Bole, Adama", lat: 8.5595, lng: 39.2705 },
  { name: "Kality, Adama", lat: 8.5401, lng: 39.2482 },
  { name: "Megenagna, Adama", lat: 8.5721, lng: 39.2891 },
  { name: "Bishoftu Road", lat: 8.5488, lng: 39.2312 },
  { name: "Sebeta Sefer", lat: 8.5312, lng: 39.2955 },
  { name: "Dukem Gate", lat: 8.5885, lng: 39.2604 },
  { name: "City Center", lat: 8.5414, lng: 39.2689 },
  { name: "Franco Sefer", lat: 8.5522, lng: 39.2801 },
  { name: "Dembela", lat: 8.5647, lng: 39.2438 },
  { name: "Adama Stadium", lat: 8.5468, lng: 39.2755 },
];

const NAMES = [
  "Alemu Tadesse",
  "Marta Bekele",
  "Yonas Assefa",
  "Tigist Alemayehu",
  "Dawit Haile",
  "Bethlehem Solomon",
  "Kalkidan Mulugeta",
  "Samuel Negash",
  "Fikadu Desta",
  "Meseret Abera",
  "Biruk Tesfaye",
  "Hiwot Girma",
];

const DESCRIPTIONS: Record<Category, string[]> = {
  "Traffic Accident": [
    "Two vehicles collided at the main junction, injuries reported.",
    "Motorbike hit a pedestrian near the market road.",
    "Truck overturned blocking both lanes of the highway.",
  ],
  "Fire & Water": [
    "Kitchen fire spreading to the neighbouring shop.",
    "Burst water pipe flooding the residential street.",
    "Electrical fire reported in a two storey building.",
  ],
  "Peace & Security": [
    "Group fight reported near the bus terminal.",
    "Robbery in progress at a small grocery store.",
    "Loud disturbance and threats between neighbours.",
  ],
  "Office Problem": [
    "Citizen service desk closed during working hours.",
    "Complaint about delayed municipal document processing.",
    "Street light maintenance request not handled for weeks.",
  ],
};

function seedEvents(): EventRecord[] {
  const events: EventRecord[] = [];
  const counters: Record<string, number> = {};
  const now = Date.now();
  for (let i = 0; i < 30; i++) {
    const category = CATEGORIES[i % 4]!;
    const priority = (["P1", "P2", "P3", "P4"] as Priority[])[(i * 3) % 4]!;
    const statusPool: Status[] = ["New", "Assigned", "Ongoing", "Solved", "Solved", "Unsolved"];
    const status = statusPool[i % statusPool.length]!;
    const loc = LOCATIONS[i % LOCATIONS.length]!;
    const daysAgo = Math.floor((i * 29) / 30);
    const hour = [2, 7, 9, 11, 13, 15, 17, 19, 21, 23][i % 10]!;
    const created = new Date(now - daysAgo * 86400000);
    created.setHours(hour, (i * 7) % 60, 0, 0);
    const prefix = CATEGORY_PREFIX[category];
    counters[prefix] = (counters[prefix] ?? 0) + 1;

    const responseMin = 5 + ((i * 13) % 115);
    const assigned =
      status === "New" ? undefined : new Date(created.getTime() + responseMin * 60000);
    const resolved =
      status === "Solved" || status === "Unsolved"
        ? new Date((assigned ?? created).getTime() + (20 + ((i * 17) % 180)) * 60000)
        : undefined;
    events.push({
      id: `${prefix}-2026-${String(counters[prefix]).padStart(3, "0")}`,
      callerName: NAMES[i % NAMES.length]!,
      phone: `+2519${String(10000000 + i * 137).slice(0, 8)}`,
      altPhone: i % 4 === 0 ? `+2519${String(20000000 + i * 91).slice(0, 8)}` : undefined,
      gender: i % 3 === 0 ? "Female" : i % 3 === 1 ? "Male" : "Other",
      address: `${loc.name} Kebele ${(i % 12) + 1}`,
      location: loc.name,
      lat: loc.lat + (i % 5) * 0.002 - 0.004,
      lng: loc.lng + (i % 4) * 0.002 - 0.003,
      category,
      subCategory: "",
      priority,
      description: DESCRIPTIONS[category][i % 3]!,
      department: DEPARTMENT_BY_CATEGORY[category],
      status,
      createdAt: created.toISOString(),
      assignedAt: assigned?.toISOString(),
      resolvedAt: resolved?.toISOString(),
      operator: i % 2 === 0 ? "Netsanet Amare" : "Mohammedareb Ahmed",
      media:
        i < 5
          ? [
              { id: uid(), name: `scene-photo-${i + 1}.jpg`, type: "image/jpeg", size: 244000 },
              { id: uid(), name: `voice-note-${i + 1}.mp3`, type: "audio/mpeg", size: 120000 },
            ]
          : [],
      escalations:
        i % 7 === 0
          ? [
              {
                id: uid(),
                at: new Date(created.getTime() + 6 * 60000).toISOString(),
                reason: "Not assigned within the priority time limit",
                by: "System",
                level: "Supervisor",
              },
            ]
          : [],
      feedback:
        status === "Solved" && i % 3 === 0
          ? {
              rating: 3 + (i % 3),
              comment: "Response team arrived and handled the situation well.",
              at: (resolved ?? created).toISOString(),
            }
          : undefined,
      source: i % 6 === 0 ? "Public Portal" : "Call Center",
      messages: [],
      smsLogs: [
        {
          id: uid(),
          at: created.toISOString(),
          to: `+2519${String(10000000 + i * 137).slice(0, 8)}`,
          by: "System",
          template: "Received",
          text: `Dear ${NAMES[i % NAMES.length]}, your report has been received. Ref: ${prefix}-2026-${String(counters[prefix]).padStart(3, "0")}. - 9141 Team`,
          status: "Delivered",
          auto: true,
        },
      ],
      followUps:
        status === "Solved" && i % 4 === 0
          ? [
              {
                id: uid(),
                dueAt: new Date(
                  (resolved ?? created).getTime() + 3 * 86400000,
                ).toISOString(),
                notes: "Confirm the citizen is satisfied with the resolution.",
                status: i % 8 === 0 ? ("Completed" as const) : ("Pending" as const),
                createdBy: "Netsanet Amare",
                createdAt: (resolved ?? created).toISOString(),
                ...(i % 8 === 0
                  ? {
                      completedAt: new Date(
                        (resolved ?? created).getTime() + 3 * 86400000,
                      ).toISOString(),
                      outcome: "Resolved" as const,
                      satisfaction: 4,
                    }
                  : {}),
              },
            ]
          : [],
      officerNotes: [],
    });
  }
  return events;
}

const SEED_VERSION = "3";

export function ensureSeed() {
  if (!isBrowser()) return;
  if (localStorage.getItem("a9141_seed_version") !== SEED_VERSION) {
    // Staff roster changed — refresh demo accounts and sample events.
    localStorage.setItem("a9141_seed_version", SEED_VERSION);
    write(KEY.users, DEFAULT_USERS);
    write(KEY.events, seedEvents());
  }


  if (!localStorage.getItem(KEY.users)) write(KEY.users, DEFAULT_USERS);
  if (!localStorage.getItem(KEY.settings)) write(KEY.settings, DEFAULT_SETTINGS);
  if (!localStorage.getItem(KEY.events)) write(KEY.events, seedEvents());
  if (!localStorage.getItem(KEY.notifications)) {
    write<Notification[]>(KEY.notifications, [
      {
        id: uid(),
        title: "Welcome to 9141",
        body: "System initialised with sample data for Adama City.",
        at: new Date().toISOString(),
        read: false,
        kind: "event",
      },
    ]);
  }
  if (!localStorage.getItem(KEY.audit)) write<AuditEntry[]>(KEY.audit, []);
}

/* events */
export const getEvents = () => read<EventRecord[]>(KEY.events, []);
export const setEvents = (e: EventRecord[]) => write(KEY.events, e);
export const getEvent = (id: string) => getEvents().find((e) => e.id === id);

export function nextEventId(category: Category) {
  const prefix = CATEGORY_PREFIX[category];
  const year = new Date().getFullYear();
  const n =
    getEvents().filter((e) => e.id.startsWith(`${prefix}-${year}`)).length +
    getEvents().filter((e) => e.id.startsWith(prefix)).length * 0 +
    1;
  return `${prefix}-${year}-${String(n).padStart(3, "0")}`;
}

export function addEvent(e: EventRecord) {
  setEvents([e, ...getEvents()]);
  addNotification({
    title: "New event registered",
    body: `${e.id} — ${e.category} at ${e.location}`,
    eventId: e.id,
    kind: "event",
  });
}

export function updateEvent(id: string, patch: Partial<EventRecord>) {
  setEvents(getEvents().map((e) => (e.id === id ? { ...e, ...patch } : e)));
}

/* users */
export const getUsers = () => read<User[]>(KEY.users, DEFAULT_USERS);
export const setUsers = (u: User[]) => write(KEY.users, u);

/* settings */
export const getSettings = () => read<Settings>(KEY.settings, DEFAULT_SETTINGS);
export const setSettings = (s: Settings) => write(KEY.settings, s);

/* notifications */
export const getNotifications = () => read<Notification[]>(KEY.notifications, []);
export const setNotifications = (n: Notification[]) => write(KEY.notifications, n);
export function addNotification(n: Omit<Notification, "id" | "at" | "read">) {
  setNotifications([
    { ...n, id: uid(), at: new Date().toISOString(), read: false },
    ...getNotifications(),
  ]);
}

/* audit */
export const getAudit = () => read<AuditEntry[]>(KEY.audit, []);
export function logAudit(user: string, action: string) {
  write(KEY.audit, [
    { id: uid(), at: new Date().toISOString(), user, action },
    ...getAudit(),
  ].slice(0, 300));
}

/* saved searches */
export const getSavedSearches = () =>
  read<{ id: string; name: string; query: string }[]>(KEY.searches, []);
export const setSavedSearches = (s: { id: string; name: string; query: string }[]) =>
  write(KEY.searches, s);

/* offline queue */
export const getQueue = () => read<EventRecord[]>(KEY.queue, []);
export const setQueue = (q: EventRecord[]) => write(KEY.queue, q);

/* session */
export const getSession = () =>
  read<{ userId: string; at: number } | null>(KEY.session, null);
export const setSession = (s: { userId: string; at: number } | null) => write(KEY.session, s);

export const SESSION_KEY = KEY.session;

/* helpers */
export const minutesBetween = (a?: string, b?: string) =>
  a && b ? Math.max(0, Math.round((new Date(b).getTime() - new Date(a).getTime()) / 60000)) : null;

export function runEscalationCheck(user: string) {
  const settings = getSettings();
  const now = Date.now();
  let changed = false;
  const events = getEvents().map((e) => {
    if (e.status !== "New") return e;
    const limit = settings.escalation[e.priority];
    const age = (now - new Date(e.createdAt).getTime()) / 60000;
    if (age > limit && e.escalations.length === 0) {
      changed = true;
      addNotification({
        title: `Escalation: ${e.id}`,
        body: `${e.priority} unassigned for ${Math.round(age)} min — supervisor notified.`,
        eventId: e.id,
        kind: "escalation",
      });
      return {
        ...e,
        escalations: [
          ...e.escalations,
          {
            id: uid(),
            at: new Date().toISOString(),
            reason: `Auto escalation: not assigned within ${limit} minutes`,
            by: "System",
            level: e.priority === "P1" ? "Supervisor + Department Head" : "Supervisor",
          },
        ],
      };
    }
    return e;
  });
  if (changed) {
    setEvents(events);
    logAudit(user, "Auto-escalation rules executed");
  }
  return changed;
}

/* ---------- Weekly operations history export (admin) ---------- */

const csvCell = (v: unknown) => `"${String(v ?? "").replace(/"/g, '""')}"`;
const csvRow = (cells: unknown[]) => cells.map(csvCell).join(",");

export function buildWeeklyReport(days = 7) {
  const since = Date.now() - days * 86400000;
  const events = getEvents().filter((e) => new Date(e.createdAt).getTime() >= since);
  const audit = getAudit().filter((a) => new Date(a.at).getTime() >= since);
  const solved = events.filter((e) => e.status === "Solved").length;
  const responses = events
    .map((e) => minutesBetween(e.createdAt, e.assignedAt))
    .filter((v): v is number => v !== null);
  const avgResponse = responses.length
    ? Math.round(responses.reduce((a, b) => a + b, 0) / responses.length)
    : 0;

  const lines: string[] = [];
  lines.push(csvRow(["Adama City 9141 — Weekly Call Operations Report"]));
  lines.push(csvRow(["Generated", new Date().toLocaleString()]));
  lines.push(csvRow(["Period", `${new Date(since).toLocaleDateString()} — ${new Date().toLocaleDateString()}`]));
  lines.push("");
  lines.push(csvRow(["SUMMARY"]));
  lines.push(csvRow(["Total calls", events.length]));
  lines.push(csvRow(["Solved", solved]));
  lines.push(csvRow(["Unsolved", events.filter((e) => e.status === "Unsolved").length]));
  lines.push(csvRow(["Open", events.filter((e) => e.status !== "Solved" && e.status !== "Unsolved").length]));
  lines.push(csvRow(["Escalated", events.filter((e) => e.escalations.length > 0).length]));
  lines.push(csvRow(["Avg response (min)", avgResponse]));
  lines.push("");
  lines.push(csvRow(["BY CATEGORY"]));
  Array.from(new Set(events.map((e) => e.category))).forEach((c) =>
    lines.push(csvRow([c, events.filter((e) => e.category === c).length])),
  );
  lines.push("");
  lines.push(csvRow(["BY OPERATOR"]));
  Array.from(new Set(events.map((e) => e.operator))).forEach((o) =>
    lines.push(csvRow([o, events.filter((e) => e.operator === o).length])),
  );
  lines.push("");
  lines.push(csvRow(["CALL RECORDS"]));
  lines.push(
    csvRow([
      "Event ID",
      "Created",
      "Caller",
      "Phone",
      "Category",
      "Priority",
      "Status",
      "Department",
      "Operator",
      "Location",
      "Assigned at",
      "Resolved at",
      "Response (min)",
      "Escalations",
      "Description",
    ]),
  );
  events.forEach((e) =>
    lines.push(
      csvRow([
        e.id,
        new Date(e.createdAt).toLocaleString(),
        e.callerName,
        e.phone,
        e.category,
        e.priority,
        e.status,
        e.department,
        e.operator,
        e.location,
        e.assignedAt ? new Date(e.assignedAt).toLocaleString() : "",
        e.resolvedAt ? new Date(e.resolvedAt).toLocaleString() : "",
        minutesBetween(e.createdAt, e.assignedAt) ?? "",
        e.escalations.length,
        e.description,
      ]),
    ),
  );
  lines.push("");
  lines.push(csvRow(["SYSTEM ACTIVITY LOG"]));
  lines.push(csvRow(["Time", "User", "Action"]));
  audit.forEach((a) => lines.push(csvRow([new Date(a.at).toLocaleString(), a.user, a.action])));

  return lines.join("\n");
}

export function downloadWeeklyReport(user: string, days = 7) {
  if (!isBrowser()) return;
  const csv = buildWeeklyReport(days);
  const blob = new Blob(["\uFEFF" + csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `adama-9141-history-${days}d-${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  logAudit(user, `Downloaded ${days}-day call operations history`);
}

/* ---------------- Role-based data isolation ---------------- */

export function scopeEvents(events: EventRecord[], user?: { role: string } | null) {
  if (!user) return events;
  const cat = (ROLE_CATEGORY as Record<string, Category>)[user.role];
  return cat ? events.filter((e) => e.category === cat) : events;
}

/* ---------------- SMS ---------------- */

export const SMS_TEMPLATES: { id: string; label: string; body: string }[] = [
  {
    id: "received",
    label: "Report received",
    body: "Dear {name}, your report {id} has been received. We are reviewing it. - 9141 Team",
  },
  {
    id: "assigned",
    label: "Assigned to department",
    body: "Dear {name}, your report {id} is now assigned to {department}. We'll update you shortly. - 9141 Team",
  },
  {
    id: "investigating",
    label: "Under investigation",
    body: "Dear {name}, your report {id} is being investigated. Status: {status}. - 9141 Team",
  },
  {
    id: "resolved",
    label: "Resolved",
    body: "Dear {name}, your report {id} has been resolved. Thank you for contacting 9141. - 9141 Team",
  },
  {
    id: "moreinfo",
    label: "Need more information",
    body: "Dear {name}, we need more information about your report {id}. Please call 9141. - 9141 Team",
  },
];

export function fillTemplate(body: string, e: EventRecord) {
  return body
    .replace(/{name}/g, e.callerName)
    .replace(/{id}/g, e.id)
    .replace(/{department}/g, e.department)
    .replace(/{status}/g, e.status);
}

export function sendSms(
  eventId: string,
  text: string,
  by: string,
  template = "Custom",
  auto = false,
) {
  const e = getEvent(eventId);
  if (!e) return null;
  const log: SmsLog = {
    id: uid(),
    at: new Date().toISOString(),
    to: e.phone,
    by,
    template,
    text: text.slice(0, 160),
    status: "Sent",
    auto,
  };
  updateEvent(eventId, { smsLogs: [log, ...(e.smsLogs ?? [])] });
  // Mock delivery receipt.
  if (isBrowser()) {
    window.setTimeout(() => {
      const cur = getEvent(eventId);
      if (!cur) return;
      updateEvent(eventId, {
        smsLogs: (cur.smsLogs ?? []).map((s) =>
          s.id === log.id ? { ...s, status: "Delivered" as const } : s,
        ),
      });
    }, 1500);
  }
  logAudit(by, `SMS sent to ${e.phone} for ${eventId}`);
  return log;
}

/* ---------------- Follow-ups ---------------- */

export function scheduleFollowUp(
  eventId: string,
  dueAt: string,
  notes: string,
  by: string,
  replaceId?: string,
) {
  const e = getEvent(eventId);
  if (!e) return;
  const list = (e.followUps ?? []).map((f) =>
    f.id === replaceId ? { ...f, status: "Rescheduled" as const } : f,
  );
  const fu: FollowUp = {
    id: uid(),
    dueAt,
    notes,
    status: "Pending",
    createdBy: by,
    createdAt: new Date().toISOString(),
  };
  updateEvent(eventId, { followUps: [fu, ...list] });
  logAudit(by, `Scheduled follow-up for ${eventId} on ${new Date(dueAt).toLocaleString()}`);
  addNotification({
    title: `Follow-up scheduled: ${eventId}`,
    body: `Due ${new Date(dueAt).toLocaleString()}`,
    eventId,
    kind: "status",
  });
}

export function completeFollowUp(
  eventId: string,
  followUpId: string,
  outcome: "Resolved" | "Problem persists",
  satisfaction: number,
  notes: string,
  by: string,
) {
  const e = getEvent(eventId);
  if (!e) return;
  const followUps = (e.followUps ?? []).map((f) =>
    f.id === followUpId
      ? {
          ...f,
          status: "Completed" as const,
          completedAt: new Date().toISOString(),
          outcome,
          satisfaction,
          notes: notes || f.notes,
        }
      : f,
  );
  const patch: Partial<EventRecord> = { followUps };
  if (outcome === "Problem persists") patch.status = "Unsolved";
  updateEvent(eventId, patch);
  logAudit(by, `Completed follow-up for ${eventId} — ${outcome}`);
  addNotification({
    title: `Follow-up completed: ${eventId}`,
    body: `${outcome} · satisfaction ${satisfaction}/5`,
    eventId,
    kind: "feedback",
  });
}

export interface FollowUpRow {
  event: EventRecord;
  followUp: FollowUp;
  overdue: boolean;
}

export function getFollowUps(): FollowUpRow[] {
  const now = Date.now();
  return getEvents()
    .flatMap((event) =>
      (event.followUps ?? []).map((followUp) => ({
        event,
        followUp,
        overdue: followUp.status === "Pending" && new Date(followUp.dueAt).getTime() < now,
      })),
    )
    .sort((a, b) => new Date(a.followUp.dueAt).getTime() - new Date(b.followUp.dueAt).getTime());
}

/* ---------------- Public citizen submissions ---------------- */

export function createPublicReport(input: {
  callerName: string;
  phone: string;
  altPhone?: string;
  gender?: "Male" | "Female" | "Other";
  location: string;
  address?: string;
  lat?: number;
  lng?: number;
  category: Category;
  priority: Priority;
  description: string;
  media?: EventRecord["media"];
}) {
  ensureSeed();
  const id = nextEventId(input.category);
  const now = new Date().toISOString();
  const event: EventRecord = {
    id,
    callerName: input.callerName,
    phone: input.phone,
    altPhone: input.altPhone,
    gender: input.gender ?? "Other",
    address: input.address ?? input.location,
    location: input.location,
    lat: input.lat ?? 8.5414,
    lng: input.lng ?? 39.2689,
    category: input.category,
    priority: input.priority,
    description: input.description,
    department: DEPARTMENT_BY_CATEGORY[input.category],
    status: "New",
    createdAt: now,
    operator: "Public Portal",
    source: "Public Portal",
    media: input.media ?? [],
    escalations: [],
    messages: [],
    smsLogs: [],
    followUps: [],
    officerNotes: [],
  };
  addEvent(event);
  sendSms(
    id,
    `Dear ${input.callerName}, your report ${id} has been received and assigned to ${event.department}. - 9141 Team`,
    "System",
    "Received",
    true,
  );
  return event;
}

export function addFeedback(
  eventId: string,
  rating: number,
  comment: string,
  media?: EventRecord["media"],
) {
  const e = getEvent(eventId);
  if (!e) return false;
  updateEvent(eventId, {
    feedback: { rating, comment, at: new Date().toISOString(), media: media ?? [] },
  });
  addNotification({
    title: `Citizen feedback: ${eventId}`,
    body: `${rating}/5 — ${comment.slice(0, 60)}`,
    eventId,
    kind: "feedback",
  });
  return true;
}

export function averageRating() {
  const rated = getEvents().filter((e) => e.feedback);
  if (!rated.length) return 0;
  return rated.reduce((a, e) => a + (e.feedback?.rating ?? 0), 0) / rated.length;
}

export function addOfficerNote(eventId: string, note: Omit<OfficerNote, "id" | "at">) {
  const e = getEvent(eventId);
  if (!e) return;
  updateEvent(eventId, {
    officerNotes: [
      { ...note, id: uid(), at: new Date().toISOString() },
      ...(e.officerNotes ?? []),
    ],
  });
}
