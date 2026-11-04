OSS Attribution & Dependency Policy
===================================

Applies to: Native American Tours engineering work in tour-frontend and
tour-backend.

Why this exists
---------------
Outside counsel confirmed NAT has a legal obligation to deliver proper
attribution notices (copyright, license text, warranty disclaimer, and any
upstream NOTICE file contents) with every distributed binary or client-side
bundle. This policy documents how we meet that obligation continuously:
the approved license list, the release gate, the escalation path when a new
dep arrives with an unfamiliar license, and the commercial-dependency
registry.

Approved licenses (allowlist)
-----------------------------
Any production dependency whose SPDX license identifier is on this list can
be added without further review:

MIT, MIT-0, BSD-2-Clause, BSD-3-Clause, Apache-2.0, ISC, 0BSD,
CC0-1.0, Unlicense, Python-2.0, PSF-2.0, WTFPL, CC-BY-4.0, CC-BY-3.0,
Zlib, BlueOak-1.0.0, OFL-1.1, MPL-2.0.

MPL-2.0 is weak-copyleft at file-level scope; it permits linking and
bundling without triggering copyleft on surrounding code. Modifications to
MPL-2.0 files must be re-published.

OFL-1.1 is for fonts only.

The canonical list lives in each repo at
`scripts/oss-notices/allowed-licenses.json`. Keep the two copies in sync
when changing the list; there is no single source of truth because each
repo is its own git repository.

Licenses that require review
----------------------------
Any copyleft license that is NOT MPL-2.0 requires legal review before use:

- GPL-2.0, GPL-3.0, GPL-*-or-later, LGPL-2.1, LGPL-3.0, AGPL-3.0,
  EPL-1.0, EPL-2.0, SSPL, CDDL, custom commercial licenses.

If you need such a dep, stop and talk to Scott before running `npm install`
or `composer require`. The allowlist gate will block your PR anyway.

Release gate
------------
Every build runs the OSS license check:

- tour-frontend (Vue client): the scan runs in CI on every pull request via
  `.github/workflows/oss-license-scan.yml`, which executes
  `npm run licenses:scan`. A non-zero exit fails the PR check.
- tour-backend (Laravel server): the scan runs inside `docker build` using
  `Dockerfile.cicd` only (not the production Dockerfile). The CICD image
  executes `php scripts/oss-notices/generate_notices.php --out-dir .` after
  `composer install`; a non-zero exit fails the CICD image build and the
  GitHub Actions PR workflow that depends on it.

The gate fails if any production dep reports a license that is not on the
allowlist and is not covered by a manual override.

Delivery of notices (per-product)
---------------------------------
- tour-frontend: `THIRD-PARTY-NOTICES.txt` and `third-party-notices.html`
  are regenerated via `npm run licenses:generate` before the Vue build. The
  HTML file can be served statically and linked from a legal/open-source
  footer route.
- tour-backend: `THIRD-PARTY-NOTICES.txt` is written to the container's
  working directory during CICD image build. tour-backend runs as SaaS and
  does not ship binaries, so notices are in place for hygiene and future
  on-prem readiness rather than out of a current legal requirement.

Manual overrides
----------------
`scripts/oss-notices/allowed-licenses.json` in each repo has a
`manualOverrides` section with three operating modes:

1. `license` only: attests to a license when upstream metadata is missing
   or malformed. Must include a `reason` note identifying where the
   attestation came from (README excerpt, LICENSE file quote, etc.).
2. `exempt: true` + `license` + `exemptUntil` + `reason`: keeps the dep
   in the notices file but bypasses the allowlist gate. Used for
   time-limited tolerances; the gate fails once the date passes. Only
   Scott may add exemptions.
3. `exclude: true` + `reason`: drops the dep from notices entirely. Used
   for commercial / non-OSS deps tracked separately in the Commercial
   Dependencies Registry below.

Override keys can be exact (`name@version`) or glob patterns
(`@scope/*@*` or `name@*`). Pattern keys save duplication for families of
packages that share a license.

Commercial Dependencies Registry
--------------------------------
None currently. Any new commercial / proprietary dependency must be added
here with a `manualOverrides` entry marked `exclude: true` in the
corresponding repo's allowlist.

How to add a new dep
--------------------
1. Check the package's stated license before installing. If it is on the
   allowlist above, proceed.
2. If unclear or missing, read the package's LICENSE/README. If permissive
   but mis-declared, add a manual override (`license` + `reason`).
3. If copyleft or commercial, stop. Escalate to Scott.
4. After install, run `npm run licenses:scan` (tour-frontend) or
   `php scripts/oss-notices/generate_notices.php --mode scan`
   (tour-backend) to confirm the gate passes locally.
5. Commit the allowlist change with the code change; CI will re-verify.

Escalation path
---------------
Questions about a license, an unclear override, or a copyleft dep:
- Primary: Nat Dev Team (info@nativeamericantours.com)
