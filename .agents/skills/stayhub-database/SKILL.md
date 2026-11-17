---
name: stayhub-database
description: Review or implement authorized StayHub MySQL schema, Laravel migrations, Eloquent mappings, constraints, indexes and isolated test data. Use when persistence integrity or schema lifecycle matters, not for UI-only changes.
---

# StayHub database

Read [ARCHITECTURE.md](../../../ARCHITECTURE.md), the relevant [ERD](../../../docs/stayhub-erd.md), migrations, models, casts and services. Use only the authorized data target and migration scope.

## Establish the target

- Identify proposed schema, migration files, applied schema and observed runtime separately. Check engine, database, connection and effective config before a write.
- Verify the selected database on the actual application connection. Cached configuration or environment overrides may differ from test defaults. Keep credentials and sensitive data out of output.
- Use an explicitly disposable owned schema for database tests. Confirm that RefreshDatabase and setup/cleanup commands target that schema; never reset the development database.
- Preserve development data. Destructive migration commands, reseeding and shared schema changes require a specifically authorized target. Review compatibility, backfill and recovery before migration changes.

## Current StayHub runtime and UC36 verification

- Development uses MySQL through root docker-compose.yaml; XAMPP supplies PHP. Read root .env locally and forward DB variables to the PHP process when needed: Laravel does not read that root file automatically. Keep backend/.env unchanged unless explicitly authorized, and verify current container/connection rather than assuming a port or version from old evidence.
- Owner requires UC36 database acceptance on MySQL Docker, including HTTP validation/authorization, transitions, rollback, outer commit and concurrency. Use a separate owned schema such as stayhub_uc36_test_<guid32>; preserve the seeded stayhub database and the old XAMPP datadir/backups.
- Provision a narrowly scoped test user/schema only under an approved implementation/QA plan. Do not assume the Compose application user can create schemas or grant privileges. Fail closed before writes on a missing opt-in, unexpected effective connection or schema; confirm SELECT DATABASE() and instance identity. Cleanup only owned test resources, never the Compose volume or demo data.
- Keep existing SQLite-only suite guards intact. Add MySQL-capable tests for UC36 instead of weakening guards or treating SQLite results as UC36 database evidence. Node tests and lint/build retain their frontend role. This UC36 choice does not authorize converting unrelated legacy suites.
- Consult [UC36 payment specification](../../../docs/TV3/INVOICE_PAYMENT_WORKFLOW.md) for the approved contract and pending runtime gates. A documentation task does not authorize provisioning or database writes.

## Review persistence

- Match types, length, decimal scale, nullability, defaults, enum casts and relationships. Keep money exact and preserve historical snapshots.
- Check ownership scope, FK delete actions, uniqueness and collation. Verify legacy records that may affect constraints or cross-property relations.
- Use existing Repository methods and indexes where suitable. Add or change indexes based on actual query predicates, joins, ordering and measurements.
- Workflow Service owns transaction and check order. Repository performs persistence and scoped locks on the same connection. Verify which rows are protected and reread facts after waiting for a lock when stale data could affect the decision.
- For file-backed operations, check relationship between DB commit and file creation, cleanup or compensation. Test cleanup only on owned fixtures.

## Evidence

- Use synthetic fixtures. Exercise the actual mutation path to verify persisted results. Directly seeded data can set up read coverage and should be identified as such.
- Test failure after partial work and after-commit behavior with the appropriate transaction lifecycle. Use separate connections on the authorized MySQL/MariaDB instance for lock, race and collation assertions; identify Docker versus XAMPP evidence explicitly.
- Report static review, schema inspection, migration execution, SQLite and MySQL behavior separately. Record environment and engine for runtime evidence. Clean only resources owned by the test run.
