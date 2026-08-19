import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { randomBytes } from "node:crypto";
const file = ".env.local";
let source = existsSync(file) ? readFileSync(file, "utf8") : "";
if (!/^AUTH_SECRET=.+$/m.test(source)) { source = `AUTH_SECRET=${randomBytes(48).toString("base64url")}\n${source}`; writeFileSync(file, source); console.log("Generated AUTH_SECRET in .env.local; replace it before deployment."); }
