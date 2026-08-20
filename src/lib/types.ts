export type Role =
  | "admin"
  | "operator"
  | "supervisor"
  | "officer"
  | "traffic"
  | "fire"
  | "police"
  | "adminoffice";

/** Department roles only see events in their own category. */
export const DEPARTMENT_ROLES = ["traffic", "fire", "police", "adminoffice"] as const;
export type DepartmentRole = (typeof DEPARTMENT_ROLES)[number];

export type Category = "Traffic Accident" | "Fire & Water" | "Peace & Security" | "Office Problem";
export type Priority = "P1" | "P2" | "P3" | "P4";
export type Status = "New" | "Assigned" | "Ongoing" | "Solved" | "Unsolved";

export interface MediaItem {
  id: string;
  name: string;
  type: string;
  size: number;
  dataUrl?: string | undefined;
}

export interface EscalationEntry {
  id: string;
  at: string;
  reason: string;
  by: string;
  level: string;
}

export interface Feedback {
  rating: number;
  comment: string;
  at: string;
  media?: MediaItem[] | undefined;
}

export interface SmsLog {
  id: string;
  at: string;
  to: string;
  by: string;
  template: string;
  text: string;
  status: "Sent" | "Delivered" | "Failed";
  auto: boolean;
}

export type FollowUpStatus = "Pending" | "Completed" | "Rescheduled" | "Overdue";

export interface FollowUp {
  id: string;
  dueAt: string;
  notes: string;
  status: FollowUpStatus;
  createdBy: string;
  createdAt: string;
  completedAt?: string | undefined;
  outcome?: "Resolved" | "Problem persists" | undefined;
  satisfaction?: number | undefined;
}

export interface OfficerNote {
  id: string;
  at: string;
  by: string;
  text: string;
  lat?: number | undefined;
  lng?: number | undefined;
}

export interface EventRecord {
  id: string;
  callerName: string;
  phone: string;
  altPhone?: string | undefined;
  gender: "Male" | "Female" | "Other";
  address: string;
  location: string;
  lat: number;
  lng: number;
  category: Category;
  subCategory?: string | undefined;
  priority: Priority;
  description: string;
  department: string;
  status: Status;
  createdAt: string;
  assignedAt?: string | undefined;
  resolvedAt?: string | undefined;
  arrivedAt?: string | undefined;
  operator: string;
  source?: "Call Center" | "Public Portal" | undefined;
  stage?: string | undefined;
  media: MediaItem[];
  escalations: EscalationEntry[];
  feedback?: Feedback | undefined;
  messages: { at: string; from: string; text: string }[];
  smsLogs?: SmsLog[] | undefined;
  followUps?: FollowUp[] | undefined;
  officerNotes?: OfficerNote[] | undefined;
}

export interface User {
  id: string;
  fullName: string;
  email: string;
  phone: string;
  username: string;
  password: string;
  role: Role;
  active: boolean;
}

export interface Notification {
  id: string;
  title: string;
  body: string;
  eventId?: string | undefined;
  at: string;
  read: boolean;
  kind: "event" | "escalation" | "sla" | "feedback" | "status";
}

export interface AuditEntry {
  id: string;
  at: string;
  user: string;
  action: string;
}

export interface Settings {
  systemName: string;
  timezone: string;
  dateFormat: string;
  departments: string[];
  escalation: Record<Priority, number>; // minutes
  sla: Record<Priority, number>; // minutes
  smsTemplate: string;
  emailTemplate: string;
}

export const CATEGORIES: Category[] = [
  "Traffic Accident",
  "Fire & Water",
  "Peace & Security",
  "Office Problem",
];

export const PRIORITIES: Priority[] = ["P1", "P2", "P3", "P4"];
export const STATUSES: Status[] = ["New", "Assigned", "Ongoing", "Solved", "Unsolved"];

export const PRIORITY_LABEL: Record<Priority, string> = {
  P1: "Critical P1",
  P2: "High P2",
  P3: "Medium P3",
  P4: "Low P4",
};

export const DEPARTMENT_BY_CATEGORY: Record<Category, string> = {
  "Traffic Accident": "Traffic Police Department",
  "Fire & Water": "Fire Brigade Department",
  "Peace & Security": "Police Department",
  "Office Problem": "Administrative Office",
};

export const CATEGORY_PREFIX: Record<Category, string> = {
  "Traffic Accident": "TRA",
  "Fire & Water": "FIR",
  "Peace & Security": "SEC",
  "Office Problem": "OFF",
};

export const ALLOWED_TRANSITIONS: Record<Status, Status[]> = {
  New: ["Assigned"],
  Assigned: ["Ongoing", "Solved", "Unsolved"],
  Ongoing: ["Solved", "Unsolved"],
  Solved: ["Ongoing"],
  Unsolved: ["Ongoing", "Assigned"],
};

export const ROLE_CATEGORY: Record<DepartmentRole, Category> = {
  traffic: "Traffic Accident",
  fire: "Fire & Water",
  police: "Peace & Security",
  adminoffice: "Office Problem",
};

export const ROLE_LABEL: Record<Role, string> = {
  admin: "Administrator",
  operator: "Call Operator",
  supervisor: "Supervisor",
  officer: "Department Office Head",
  traffic: "Traffic Police Department",
  fire: "Fire Brigade Department",
  police: "Police Department",
  adminoffice: "Administrative Office",
};

/** Department-specific field status labels (mapped onto the core status flow). */
export const DEPARTMENT_STAGES: Record<DepartmentRole, { label: string; status: Status }[]> = {
  traffic: [
    { label: "En Route", status: "Assigned" },
    { label: "On Scene", status: "Ongoing" },
    { label: "Investigating", status: "Ongoing" },
    { label: "Clearance in Progress", status: "Ongoing" },
    { label: "Resolved", status: "Solved" },
    { label: "Unsolved", status: "Unsolved" },
  ],
  fire: [
    { label: "Dispatched", status: "Assigned" },
    { label: "Arrived", status: "Ongoing" },
    { label: "Containing", status: "Ongoing" },
    { label: "Extinguished", status: "Ongoing" },
    { label: "Resolved", status: "Solved" },
    { label: "Unsolved", status: "Unsolved" },
  ],
  police: [
    { label: "Investigating", status: "Ongoing" },
    { label: "Suspects Identified", status: "Ongoing" },
    { label: "Arrests Made", status: "Ongoing" },
    { label: "Resolved", status: "Solved" },
    { label: "Unsolved", status: "Unsolved" },
  ],
  adminoffice: [
    { label: "Reviewing", status: "Assigned" },
    { label: "Investigating", status: "Ongoing" },
    { label: "Action Taken", status: "Ongoing" },
    { label: "Resolved", status: "Solved" },
    { label: "Unsolved", status: "Unsolved" },
  ],
};
