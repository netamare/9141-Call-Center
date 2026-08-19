import mongoose from "mongoose";
import dns from "node:dns";
const uri=process.env.MONGODB_URI;
// Validate lazily inside connectDB so API routes can return a clear setup message.
let cached=(global as typeof globalThis & {mongoose?:{conn:typeof mongoose|null;promise:Promise<typeof mongoose>|null}}).mongoose;
if(!cached) cached=(global as typeof globalThis & {mongoose?:{conn:typeof mongoose|null;promise:Promise<typeof mongoose>|null}}).mongoose={conn:null,promise:null};
export async function connectDB(){if(cached!.conn)return cached!.conn;const connectionUri=process.env.MONGODB_URI?.trim();if(!connectionUri)throw new Error("MongoDB is not configured. Set MONGODB_URI in .env.local (copy .env.example first).");const dnsServer=process.env.MONGODB_DNS_SERVER?.trim();if(dnsServer)dns.setServers([dnsServer]);if(!cached!.promise)cached!.promise=mongoose.connect(connectionUri,{bufferCommands:false,serverSelectionTimeoutMS:8000});try{cached!.conn=await cached!.promise;return cached!.conn}catch(error){cached!.promise=null;throw new Error(`MongoDB connection failed. Check MONGODB_URI and Atlas network access. ${error instanceof Error?error.message:""}`)}}
