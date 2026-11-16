---
name: stayhub-database
description: Review or implement authorized StayHub MySQL schema, Laravel migrations, Eloquent mappings, constraints, indexes and isolated test data. Use when persistence integrity or schema lifecycle matters, not for UI-only changes.
---

# StayHub database

Read [ARCHITECTURE.md](../../../ARCHITECTURE.md), the relevant [ERD](../../../docs/stayhub-erd.md), migrations, models, casts and services. Use only the authorized data target and migration scope.

## Establish the target

- Identify proposed schema, migration files, applied schema and observed runtime separately. Check engine, database, connection and effective config before a write.
- Verify the selected database on the actual application connection. Cached configuration or environment overrides may differ from test defaults. Keep credentials and sensitive data out of output.
- Use an explicitly disposable owned schema for XAMPP tests. Confirm that RefreshDatabase and setup/cleanup commands target that schema. For SQLite use the configured isolated in-memory connection.
- Preserve development data. Destructive migration commands, reseeding and shared schema changes require a specifically authorized target. Review compatibility, backfill and recovery before migration changes.

## Review persistence

- Match types, length, decimal scale, nullability, defaults, enum casts and relationships. Keep money exact and preserve historical snapshots.
- Check ownership scope, FK delete actions, uniqueness and collation. Verify legacy records that may affect constraints or cross-property relations.
- Use existing Repository methods and indexes where suitable. Add or change indexes based on actual query predicates, joins, ordering and measurements.
- Workflow Service owns transaction and check order. Repository performs persistence and scoped locks on the same connection. Verify which rows are protected and reread facts after waiting for a lock when stale data could affect the decision.
- For file-backed operations, check relationship between DB commit and file creation, cleanup or compensation. Test cleanup only on owned fixtures.

## Evidence

- Use synthetic fixtures. Exercise the actual mutation path to verify persisted results. Directly seeded data can set up read coverage and should be identified as such.
- Test failure after partial work and after-commit behavior with the appropriate transaction lifecycle. Use separate connections on MySQL/XAMPP for lock, race and collation assertions.
- Report static review, schema inspection, migration execution, SQLite and MySQL behavior separately. Record environment and engine for runtime evidence. Clean only resources owned by the test run.
