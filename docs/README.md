# Backend documentation

Written against the app as it stands — every field, endpoint and column below is traceable
to something a screen renders or a repository method returns.

| Doc | What it answers |
| --- | --- |
| [01 — Data requirements](01-DATA-REQUIREMENTS.md) | Every entity and every field: type, required/optional/derived, who produces it. Includes the admin-owned tracking configuration, the device preferences it interacts with, and a list of data the UI needs that no model carries yet |
| [02 — API plan](02-API-PLAN.md) | The route table, built so one endpoint serves every caller that wants the same shape. Ends with a repository-method → endpoint map |
| [03 — Database schema](03-DATABASE-SCHEMA.md) | PostgreSQL DDL, partitioning and rollup strategy, query → index map, model → table coverage |

Read them in order. Each one closes with a coverage table, so "did we miss anything" has an
answer rather than an opinion.

## The three rules everything else follows from

1. **Employee payloads carry no owner id.** `/api/*?subject=me` resolves from the token.
   Admin routes address an employee explicitly. This is why the shared models have no
   `employeeId` and the admin envelopes (`TeamMember`, `EmployeeDay`, `RouteTrack`,
   `ReportInboxItem`) do.
2. **Tracking thresholds are organisation config owned by the admin**, applied at read
   time. Changing a threshold re-classifies what counts as a stop; it never rewrites a
   stored GPS fix.
3. **What the seller typed is what gets stored.** Company, branch, deal value and payment
   are free text; product name and colour are denormalised onto the sale line. A catalogue
   edit or a delisting can never change what a report says.
