import {NextRequest,NextResponse} from "next/server";
import {connectDB} from "@/lib/db";
import User from "@/models/User";
import {getSession,ROLES} from "@/lib/auth";
import {hashPassword} from "@/lib/password";
export async function GET(){const s=await getSession();if(!s||s.role!=="ADMIN")return NextResponse.json({error:"Forbidden"},{status:403});try{await connectDB();return NextResponse.json(await User.find().select("-passwordHash").lean());}catch{return NextResponse.json({error:"Database unavailable"},{status:503});}}
export async function POST(r:NextRequest){const s=await getSession();if(!s||s.role!=="ADMIN")return NextResponse.json({error:"Forbidden"},{status:403});try{const b=await r.json();if(!b.name||!b.email||!b.password||!ROLES.includes(b.role))return NextResponse.json({error:"Invalid user data"},{status:400});await connectDB();return NextResponse.json(await User.create({...b,passwordHash:hashPassword(b.password)}),{status:201});}catch{return NextResponse.json({error:"Unable to create user"},{status:400});}}
