---
agent: web-agent
date: 2026-11-05T02:15Z
status: done
breaking: false
---

## What changed
- website/.env.example — replaced every live production secret with a safe placeholder (JWT_SECRET, DB_PASSWORD, GOOGLE_MAPS_BROWSER_KEY now empty; DB_HOST/DB_DATABASE/DB_USERNAME now generic), deleted the plaintext `#HazraApp password : HazraApp@2026` comment, and added a header comment block marking the file an example-only template. Key names, ordering and structure unchanged.

## API contract impact
- none — no route, response shape, status code or auth rule touched.

## Needs doing next
- [ ] **ROTATE ALL FOUR CREDENTIALS** — they were committed to a public repo and are still
      recoverable from git history (`git log -S` finds the JWT secret in commits 9024878,
      b7e43d3, 438679f, 0dc04d9). Scrubbing the working tree does not unpublish them.
      Owner: repo owner / human, not an agent.
      1. Hostinger MySQL password for `u402700136_hazra` (host 193.203.184.191)
      2. `JWT_SECRET` — rotating invalidates every live app session; mobile-agent should
         expect a forced re-login wave. Generate with `php vayu setup` or
         `php -r 'echo bin2hex(random_bytes(32));'`
      3. Google Maps browser key `AIzaSyClKxaIZ6…` — restrict/regenerate in Google Cloud
      4. The `HazraApp@2026` account password
- [ ] Update the `PROD_ENV_FILE` GitHub Actions secret after rotation — it is an
      independent paste of this file's old contents and still holds the old values.
      Owner: repo owner / human.
- [ ] Consider making an empty `JWT_SECRET` fail loudly instead of falling back to
      `'change-me-in-production'` (website/core/JwtAuth.php:11). Owner: web-agent,
      pending a decision — it is a behaviour change on misconfigured deploys.

## Blocked on
- nothing. Changes left uncommitted in the working tree as instructed.
