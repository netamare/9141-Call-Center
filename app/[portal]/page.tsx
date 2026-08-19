import {redirect} from "next/navigation";
export default async function Page({params}:{params:Promise<{portal:string}>}){redirect(`/${(await params).portal}/dashboard`);}
