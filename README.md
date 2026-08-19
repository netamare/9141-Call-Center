# Call Center 9141

A responsive Adama Administration event escalation dashboard built with Next.js App Router, TypeScript, Tailwind CSS, Recharts and Lucide.

## Run locally
1. Copy the template: `Copy-Item .env.example .env.local` (PowerShell) or `cp .env.example .env.local` (macOS/Linux).
2. Open `.env.local` and set `MONGODB_URI` to your MongoDB Atlas connection string and `AUTH_SECRET` to a long random value. Keep this file private; never commit it.
3. In MongoDB Atlas, add your development IP under Network Access and ensure the database user has read/write access.
4. Run `npm install`, then `npm run dev`, and open http://localhost:3000.

For a fresh database, run `npm run seed` after replacing the Atlas placeholders in `.env.local`. This creates the `callcenter9141` database collections, eight departments, and four users. Seeded users use the `@callcenter9141.local` addresses and the temporary password `ChangeMe9141!`; change passwords immediately after signing in.

Atlas setup: create a free M0 cluster, create a database user under Database Access, add your development IP under Network Access, choose Connect → Drivers, and paste the generated URI into `MONGODB_URI`. The `/api/health/db` endpoint verifies connectivity.

If `MONGODB_URI` is missing, API responses identify the missing variable and point back to this setup. If Atlas cannot be reached, the response identifies the connection/network-access issue. The application uses the cached connection in `lib/db.ts` for every database-backed API route.

If the connection reports `querySrv ECONNREFUSED`, verify that the cluster hostname in the Atlas driver connection string is complete and unchanged, then confirm that DNS resolution and outbound access to MongoDB Atlas are permitted on the machine running the app. This is an Atlas/DNS configuration issue rather than an application credential error.

## Architecture
- `app/page.tsx`: responsive administrator dashboard and client event workflow.
- `app/api/events`: REST endpoint for event listing and registration.
- `models/Event.ts`: Mongoose event document schema.
- `lib/db.ts`: cached MongoDB Atlas connection.

Next implementation steps: add Auth.js credentials provider and role guards, User/Department/FollowUp schemas, and role-specific routes for operators, supervisors and department officers.
