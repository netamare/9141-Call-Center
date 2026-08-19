import {NextResponse} from "next/server";
import {connectDB} from "@/lib/db";
export async function GET(){try{await connectDB();return NextResponse.json({ok:true,database:"mongodb",message:"MongoDB Atlas connection is healthy"});}catch(error){return NextResponse.json({ok:false,database:"mongodb",error:error instanceof Error?error.message:"MongoDB connection failed"},{status:503});}}
