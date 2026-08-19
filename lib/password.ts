import crypto from "crypto";
export function hashPassword(password:string){const salt=crypto.randomBytes(16).toString("hex");const hash=crypto.scryptSync(password,salt,64).toString("hex");return `scrypt:${salt}:${hash}`;}
export function verifyPassword(password:string,stored:string){if(!stored)return false;if(!stored.startsWith("scrypt:"))return stored===password;const [,salt,hash]=stored.split(":");try{return crypto.timingSafeEqual(Buffer.from(hash,"hex"),crypto.scryptSync(password,salt,64));}catch{return false;}}
