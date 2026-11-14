---
name: stayhub-database
description: Review or implement authorized StayHub MySQL schema, Laravel migrations, Eloquent mappings, constraints, indexes and isolated test data. Use when persistence integrity or schema lifecycle matters, not for UI-only changes.
---

# StayHub database

Inspect applicable instructions and the exact persistence task. This skill does not authorize migration execution, shared schema changes or reseeding. Read related migrations/seeders, models/casts/enums, services, tests and the relevant ERD section; resolve discrepancies against actual code and authorized runtime evidence.

## Establish the database target

- Distinguish a proposed schema, migration file, applied migration and observed runtime schema. Never claim a migration ran because its file exists.
- Before a command that may write data, identify connection/database/environment and confirm isolation. Do not print secrets while inspecting configuration. Cached Laravel config and process environment can override assumptions from phpunit.xml.
- Existing PHPUnit configuration uses SQLite in-memory; verify effective settings for the run. MySQL tests need an explicitly disposable owned database and narrowly scoped fixture/cleanup strategy. Never repoint tests at the owner's development data for convenience.
- Do not run migrate:fresh/refresh/rollback, seed again, truncate/drop or overwrite existing data without specific authorization for the exact target. Keep migrations additive and reviewed when existing data must survive; never silently rewrite applied migrations.

## Review integrity

- Match types, lengths, decimal scale/range, nullability, defaults, enum values, casts and relationships. Integer scaled arithmetic must have defined scale/overflow handling; money must not use floating point.
- Verify unique/FK constraints and delete behavior against business ownership. Examples: invoice/review uniqueness per booking, invoice items attached atomically, chat messages constrained to owned sessions.
- Preserve Booking/BookingService financial snapshots. Validate existing data before proposing constraints or backfills. Do not quietly coerce incompatible values.
- Assess indexes from actual filters/joins/order, including Host ownership and reporting ranges. Avoid speculative indexes and performance claims without measurements.
- Explain transaction/locking expectations and test on the relevant engine when concurrency is an AC. SQLite success is not evidence for InnoDB lock behavior or MySQL-specific SQL.

## Fixtures, execution and evidence

Use synthetic minimal fixtures in isolated tests. For an invoice-creation E2E gate, create invoice/items through the actual API/service under test; directly seeded invoices can support a read-query test only when labeled accordingly. Apply the same distinction to other business outputs.

If schema change is required, present affected files, owner, data compatibility, migration/backfill/recovery plan and validation before execution. Shared migrations/seeders remain outside member scope unless expressly authorized. An ordinary review can finish without any database mutation.

Report static inspection, migration status, migration execution, database tests and engine/version separately. Preserve evidence; cleanup only precisely identified resources owned by the test run. Record NOT VERIFIED when runtime schema or engine behavior was not inspected.

## Current Invoice persistence and validation

See [architecture](../../../ARCHITECTURE.md) sections 4/13/14. InvoiceRepository is one concrete class injected through constructors, no interface/Repository binding. It has four ownership-scoped read methods and six create methods for locked Booking lookup, duplicate/code existence, header/items persistence and eager load. Service owns DB::transaction, validation order, calculator, uniqueness recheck, workflow defaults and afterCommit; Repository doesn't commit or decide payment transitions.

InvoiceValidator does not query or lazy-load: Service obtains duplicate fact after role/ownership/status and passes bool. Repository eager-loads customer, room.property and bookingServices for create. Loaded-null keeps business errors; unloaded relation guard rejects internal misuse rather than silently querying. Read detail items must be eager-loaded before Mapper/DTO serialization.

Model attributes run explicitly on candidates from Mapper payload before respective Invoice/ROOM insert. Groups invoice-create (code) and invoice-create-room (ROOM name) don't add a blank-name rule to SERVICE. Default/save isn't globally validated; no observer or new schema constraint. Validation normalizers don't mutate snapshots and aren't guarantees against later Eloquent-event mutation. Booking/BookingService snapshots, exact decimal/BCMath and unique booking remain authoritative.

InvoiceMapper projections/DTO serialization remain query-free, immutable and explicit; installed Symfony EloquentObjectMapper is adapter/pilot for allowlisted loaded scalars, not authorization or an ORM replacement. Preserve omission-vs-empty items, pagination/order/null/date/decimal strings. Common retains legacy Resource support for other modules even though Invoice Resources are removed.

Test real persistence failures/rollback, controlled unique conflict and Model fault injection separately from MySQL concurrency. Success log must await actual outer commit, not just RefreshDatabase's wrapping test transaction. Existing commit tests own a named SQLite :memory: connection/production transaction manager and purge only their resources. No schema/data migration is implied by these architecture changes. Review and TV1/TV2 remain separately scoped; documentation acceptance does not authorize development DB writes or closing parent/browser/MySQL gates.

## Unified validation namespace

All application validation classes share app/Validators/: root AttributeValidator is infrastructure, Constraints/ holds attributes/constraint validators, domain subfolders hold rules. Namespace consolidation changes no Eloquent mapping/schema/DB query/transaction, no financial rules or Model groups. Test DI/attributes/rollback instead of modifying persistence to follow a folder rename.
