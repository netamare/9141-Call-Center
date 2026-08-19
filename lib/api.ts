import {NextResponse} from "next/server";
import {getSession,Role} from "@/lib/auth";
export async function requireUser(roles?:Role[]){const user=await getSession();if(!user)return {error:NextResponse.json({error:"Authentication required"},{status:401})};if(roles&&!roles.includes(user.role))return {error:NextResponse.json({error:"You do not have permission for this action"},{status:403})};return {user};}
export function invalid(message:string){return NextResponse.json({error:message},{status:400});}
