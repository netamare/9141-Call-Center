import {NextRequest,NextResponse} from "next/server";
import {connectDB} from "@/lib/db";
import Department from "@/models/Department";
import {getSession} from "@/lib/auth";
export async function GET(){const s=await getSession();if(!s)return NextResponse.json({error:"Unauthorized"},{status:401});try{await connectDB();return NextResponse.json(await Department.find().sort({name:1}).lean());}catch{return NextResponse.json({error:"Database unavailable"},{status:503});}}
export async function POST(r:NextRequest){const s=await getSession();if(!s||s.role!=="ADMIN")return NextResponse.json({error:"Forbidden"},{status:403});try{const body=await r.json();if(!body.name||!body.code)return NextResponse.json({error:"Name and code are required"},{status:400});await connectDB();return NextResponse.json(await Department.create(body),{status:201});}catch{return NextResponse.json({error:"Unable to create department"},{status:400});}}
