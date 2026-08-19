import crypto from "crypto";
import { cookies } from "next/headers";
export const ROLES = ["ADMIN", "OPERATOR", "SUPERVISOR", "DEPARTMENT_OFFICER"] as const;
export type Role = (typeof ROLES)[number];
const secret = process.env.AUTH_SECRET || process.env.NEXTAUTH_SECRET;
if (!secret && process.env.NODE_ENV === "production") throw new Error("Authentication is not configured. Set AUTH_SECRET in .env.local.");
const signingSecret = secret || crypto.randomBytes(32).toString("hex");
export const rolePath: Record<Role,string> = {ADMIN:"admin",OPERATOR:"operator",SUPERVISOR:"supervisor",DEPARTMENT_OFFICER:"department"};
export function makeSession(user:{id:string;name:string;role:Role}) { const payload=Buffer.from(JSON.stringify({...user,exp:Date.now()+43200000})).toString("base64url"); return payload+"."+crypto.createHmac("sha256",signingSecret).update(payload).digest("base64url"); }
export function readSession(value?:string): {id:string;name:string;role:Role;exp:number}|null { if(!value)return null; const [payload,signature]=value.split("."); const expected=crypto.createHmac("sha256",signingSecret).update(payload||"").digest("base64url"); if(!payload||!signature||signature.length!==expected.length||!crypto.timingSafeEqual(Buffer.from(signature),Buffer.from(expected)))return null; try {const s=JSON.parse(Buffer.from(payload,"base64url").toString());return s.exp>Date.now()&&ROLES.includes(s.role)?s:null;}catch{return null;} }
export async function getSession(){return readSession((await cookies()).get("cc9141_session")?.value);}
