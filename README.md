# Kakade Monthly Incentive

PHP 8.2+ and PDO SQLite application. Production demo seeding is OFF by default. Only explicitly isolated local/verification environments set INCENTIVE_DEMO=1.

## Features

Three units: Unit One, Unit Two, Unit Three. Admin manages departments, employee punching codes/names, accounts, role/unit assignments and separate report grants in User Access (sidebar and top bar). Requester submits monthly requests with reasons and evidence. Approval order is Plant Head → CEO → HR Head assigns INR amount → HR Head marks Processed / Not Processed. Requester acceptance is not required. Every stage is audited; rejected and final requests are immutable.

The blue-and-white clickable process tree is supplied by the server with permitted stages and scoped pending counts. Admin has read-only oversight of all stages in assigned units. Requesters track only their own requests even with report grants. Plant Head/CEO receive their actionable stages; HR gets assignment, processing and outcomes. Counts cover all months, while the dashboard month picker controls monthly analytics.

Amounts are returned only for CEO/HR Head in server responses and authorized CSV exports. Dashboard ranking is ordered by total processed incentive amount for each employee in the month, computed server-side; totals and amounts are never transmitted in dashboard payloads. Ties use employee name.

Uploads have two explicit classifications: nonfinancial approval evidence visible to authorized request viewers including Plant Head, and confidential financial files visible only to CEO/HR. The uploader must confirm approval evidence contains no incentive amounts or financial details; arbitrary file contents cannot be automatically classified or safely redacted. Existing uploads migrate as private. Files are stored with opaque names outside the public directory, checked for extension/MIME agreement, capped at 5 MB each, and delivered through the scoped download endpoint.

## Hosting

Deploy through GitHub to Hostinger. Preferred document root is public/; protected root entry points also support a repository-root document root with Apache/LiteSpeed .htaccess. Never publish storage, backups, credentials or private release keys. The default storage/ directory is server-created and ignored by Git. Configure upload_max_filesize=5M and post_max_size=12M for two optional files. PHP requires PDO SQLite, fileinfo, mbstring and openssl. Use HTTPS and back up the SQLite database and upload directory together.

Initial Admin provisioning is a one-time, signed, host-bound operation. Only the public verification key is tracked. The private key, supplied identity and password hash remain outside the repository. A supplied initial credential requires a new password of at least 12 characters at first login before any workspace operations. Subsequent passwords use PHP password_hash/password_verify. A PBKDF2-SHA256 hash with 600,000 iterations is supported solely for securely provisioning the initial credential without storing its plaintext.

Signed live verification uses a separate private SQLite/upload namespace. Expiring host-bound signatures are required on every request; tests do not touch production rows. The cleanup endpoint removes only the signed test namespace. Public/untrusted callers cannot provision accounts, seed demonstrations or start verification. The production-counts check is restricted by that signature.

## Local testing

Run PHP with -S 127.0.0.1:8765 -t public. Set INCENTIVE_STORAGE to a separate directory and INCENTIVE_DEMO=1 for synthetic local fixtures. Do not delete or replace existing storage.

INCENTIVE_TEST_URL=http://127.0.0.1:8766 python3 tests/release.py tests authentication, strict workflow, HR outcomes, field/CSV confidentiality, audits, upload access, all five roles across all units, stage filtering/counts, report independence, role revocation, and highest-incentive ranking. Synthetic credentials in test fixtures are exclusively for the explicitly enabled test namespace. No real identity, credential or data belongs in source control.
