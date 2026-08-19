import { NextRequest, NextResponse } from "next/server";
import { connectDB } from "@/lib/db";
import Setting from "@/models/Setting";
import { requireUser, invalid } from "@/lib/api";

export async function GET() {
  const auth = await requireUser(["ADMIN"]);
  if (auth.error) return auth.error;

  try {
    await connectDB();
    return NextResponse.json(await Setting.find().lean());
  } catch {
    return invalid("Unable to load settings");
  }
}

export async function PATCH(request: NextRequest) {
  const auth = await requireUser(["ADMIN"]);
  if (auth.error) return auth.error;

  try {
    const body = await request.json();
    if (!body.key) return invalid("Setting key is required");

    await connectDB();
    return NextResponse.json(
      await Setting.findOneAndUpdate(
        { key: body.key },
        { $set: { value: body.value, updatedBy: auth.user!.id } },
        { upsert: true, new: true }
      )
    );
  } catch {
    return invalid("Unable to save setting");
  }
}
