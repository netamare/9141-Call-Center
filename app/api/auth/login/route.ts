import { NextRequest, NextResponse } from "next/server";
import { connectDB } from "@/lib/db";
import User from "@/models/User";
import { makeSession, Role, ROLES } from "@/lib/auth";
import {verifyPassword} from "@/lib/password";
export async function POST(request:NextRequest){
 const {email,password,role}=await request.json();
 if(!email||!password||!ROLES.includes(role))return NextResponse.json({error:"Email, password and role are required."},{status:400});
 if(!process.env.AUTH_SECRET&&!process.env.NEXTAUTH_SECRET)return NextResponse.json({error:"Authentication is not configured. Set AUTH_SECRET in .env.local."},{status:503});
 let user:any;
 try {await connectDB();user=await User.findOne({email:email.toLowerCase(),role,active:true}).lean();}catch(error){return NextResponse.json({error:error instanceof Error?error.message:"MongoDB setup is incomplete. Set MONGODB_URI in .env.local."},{status:503});}
 const demo=email.toLowerCase()==="demo@9141.local"&&password==="Demo@9141";
 if((!user||!verifyPassword(password,user.passwordHash))&&!demo)return NextResponse.json({error:"Invalid credentials. Use demo@9141.local / Demo@9141 for the demo."},{status:401});
 const name=user?.name||"Demo User"; const id=user?._id?.toString()||"demo";
 const response=NextResponse.json({ok:true,role}); response.cookies.set("cc9141_session",makeSession({id,name,role:role as Role}),{httpOnly:true,sameSite:"lax",secure:process.env.NODE_ENV==="production",path:"/",maxAge:43200}); return response;
}
