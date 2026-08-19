import {redirect} from "next/navigation";
import {getSession,rolePath} from "@/lib/auth";
import Portal from "@/components/Portal";
export default async function Page({params}:{params:Promise<{portal:string,page:string}>}){const session=await getSession();const p=await params;if(!session)redirect('/');if(rolePath[session.role]!==p.portal)redirect(`/${rolePath[session.role]}/dashboard`);return <Portal role={session.role} name={session.name} page={p.page}/>;}
