import { useEffect, useState } from "react";
import { createFileRoute } from "@tanstack/react-router";
import { toast } from "sonner";
import { AppShell } from "@/components/AppShell";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { getAudit, getUsers, setUsers, uid, logAudit } from "@/lib/store";
import { useAuth } from "@/lib/auth";
import type { Role, User } from "@/lib/types";

export const Route = createFileRoute("/users")({
  head: () => ({
    meta: [
      { title: "User Management — Adama 9141" },
      { name: "description", content: "Manage operators, officers and supervisors." },
      { property: "og:title", content: "User Management — Adama 9141" },
      { property: "og:description", content: "Manage call center staff accounts." },
    ],
  }),
  component: UsersPage,
});

const ROLES: Role[] = ["admin", "operator", "officer", "supervisor"];

function UsersPage() {
  const { user } = useAuth();
  const [rows, setRows] = useState<User[]>([]);
  const [name, setName] = useState("");
  const [username, setUsername] = useState("");
  const [role, setRole] = useState<Role>("operator");

  useEffect(() => setRows(getUsers()), []);

  const persist = (next: User[]) => {
    setUsers(next);
    setRows(next);
  };

  const add = () => {
    if (!name.trim() || !username.trim()) {
      toast.error("Name and username are required");
      return;
    }
    const next: User[] = [
      ...rows,
      {
        id: uid(),
        username: username.trim(),
        password: "demo123",
        fullName: name.trim(),
        email: `${username.trim()}@adama9141.gov.et`,
        phone: "+251900000000",
        role,
        active: true,
      },
    ];
    persist(next);
    logAudit(user?.fullName ?? "Admin", `Created user ${username}`);
    toast.success("User created (default password: demo123)");
    setName("");
    setUsername("");
  };

  return (
    <AppShell title="User Management">
      <div className="space-y-4">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Add user</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-4">
            <div className="space-y-1">
              <Label className="text-xs">Full name</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Username</Label>
              <Input value={username} onChange={(e) => setUsername(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Role</Label>
              <Select value={role} onValueChange={(v) => setRole(v as Role)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ROLES.map((r) => (
                    <SelectItem key={r} value={r}>
                      {r}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button className="min-h-11 self-end" onClick={add}>
              Add user
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Staff ({rows.length})</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto p-0">
            <table className="w-full min-w-[640px] text-sm">
              <thead className="bg-brand-gradient text-primary-foreground">
                <tr>
                  <th className="px-3 py-2 text-left">Name</th>
                  <th className="px-3 py-2 text-left">Username</th>
                  <th className="px-3 py-2 text-left">Role</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((u) => (
                  <tr key={u.id} className="border-t">
                    <td className="px-3 py-2 font-medium">{u.fullName}</td>
                    <td className="px-3 py-2">{u.username}</td>
                    <td className="px-3 py-2 capitalize">{u.role}</td>
                    <td className="px-3 py-2">{u.active ? "Active" : "Deactivated"}</td>
                    <td className="flex flex-wrap gap-2 px-3 py-2">
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() =>
                          persist(rows.map((r) => (r.id === u.id ? { ...r, active: !r.active } : r)))
                        }
                      >
                        {u.active ? "Deactivate" : "Activate"}
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => toast.success(`Password reset for ${u.username}: demo123`)}
                      >
                        Reset password
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-base">Recent activity log</CardTitle>
          </CardHeader>
          <CardContent className="space-y-1 text-sm">
            {getAudit()
              .slice(0, 15)
              .map((a) => (
                <p key={a.id} className="text-muted-foreground">
                  <span className="text-foreground">{a.user}</span> — {a.action} ·{" "}
                  {new Date(a.at).toLocaleString()}
                </p>
              ))}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
