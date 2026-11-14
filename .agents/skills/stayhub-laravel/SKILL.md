---
name: stayhub-laravel
description: Implement or review StayHub Laravel 12 APIs and services, including HTTP validation, authorization, Eloquent, transactions, resources and PHPUnit tests. Use for backend feature changes across the assigned team modules.
---

# StayHub Laravel

Use current instructions and approved scope; this shared skill does not grant ownership of all modules. Read ARCHITECTURE.md and the actual request-to-persistence chain rather than forcing a new directory convention.

## Trace and implement

- Inspect registered routes, auth middleware, validators/DTOs, services/repositories, models/enums, Common responses and any legacy Form Requests/Resources, plus matching tests. Confirm frontend consumer contract when the task exposes an API.
- Keep controllers as coordinators. For new/migrated slices, input validators handle input/authorization rules, domain validators handle business/internal constraints, services own workflow/transactions, concrete repositories own persistence, models own relationships/casts/scopes, and explicit JsonSerializable Response DTOs plus Common helpers serialize output. Keep legacy Form Requests/Resources until separately migrated.
- Use Sanctum session/cookie authentication and existing active-account rules. Enforce role and ownership on the server through the project's approved policy/middleware/service boundary. Do not infer protection from React guards or leak resource existence contrary to the established error contract.
- Derive prices and identifiers from trusted server state. Use exact decimal/BCMath or suitable scaled integers for money, including aggregate response serialization.
- For mutations, define atomicity, uniqueness, legal transitions and conflict/retry behavior. Add locks/constraints where justified by actual concurrent writes, not as boilerplate. Do not change shared schema without approved scope.
- Preserve endpoint naming, pagination and error contracts. Reject mass-assigned role/owner/status/total fields that are server-owned. Repository and central exception infrastructure follow ARCH-01; implement bindings/global registration only in the approved task scope.
- Log meaningful business operations with safe identifiers. Avoid per-calculation logs, broad catch solely to log/rethrow, and tokens/cookies/credentials/PII. Translate expected domain exceptions only at a boundary that owns that contract.

## Domain checks

- Identity/catalog: follow TV1's current auth/account/property visibility contract; do not redesign authentication while integrating another module.
- Booking/services: use TV2's approved availability/workflow and snapshot contract; do not recompute booking prices in another module.
- Invoice: unique booking, snapshot amounts, atomic header/items, no double discount/surcharge. Payment transitions require an agreed workflow, not just an enum list.
- Review: own COMPLETED booking, one review per booking, rating 1–5; Host reads but cannot edit Customer content.
- Reporting: read-only queries; Host ownership scope on every aggregate; confirm eligible invoice statuses/date/refund treatment before revenue queries.
- FAQ/chat: role-scoped management and session ownership; provider failures/timeouts handled; context excludes secrets and unauthorized data; V1 performs no booking/payment mutation.

## Verify

Cover success and meaningful validation, unauthenticated/wrong-role/wrong-owner, not-found/conflict paths. Financial tests use independently specified decimal expectations rather than calling the production formula to calculate expected results. Add regression tests for real defects.

Inspect test database safety first. Run focused tests while developing and the required backend gate `php artisan test` before reporting completion. Report SQLite and MySQL evidence separately; SQLite tests do not prove row-lock concurrency. Finish Backend and required Frontend scope before claiming the whole use case is complete.

## Current Invoice boundary

See [architecture](../../../ARCHITECTURE.md) sections 4/13/14. InvoiceValidator groups read/create validation; InvoiceMapper owns normalized Request DTOs, persistence snapshots and Response DTO projection. InvoiceRepository is one concrete class with four read and six create methods, injected through constructors without Repository binding. AppServiceProvider still configures Symfony Validator/ObjectMapper engines; don't remove those registrations to eliminate Repository binding.

Read: authorize expected role before query/ID validation, preserve framework 422 field errors and privacy-404/INVOICE_NOT_FOUND; QueryService retains role checks outside HTTP. Create: Controller validates raw ID through DTO attributes before mapping to CreateInvoiceRequest; actor comes from session. Service locks/loads Booking, checks role/ownership/status, then queries duplicate fact via Repository before snapshots/financial integrity. Validator receives bool fact, never Repository/query callback; loaded-null business errors differ from unloaded-relation LogicException. Don't apply read ordering mechanically to create.

HTTP create calls createInvoiceFromRequest and maps loaded Invoice to InvoiceResponse -> ApiResponse.success(201); compatibility createInvoice(int, User) shares the workflow. Keep expected create 403/404/409/422 message-only catches. GlobalExceptionHandler remains route-scoped, read codes/Auth/Web/framework headers unchanged. Invoice Resources are removed; retain Auth/Profile/Review legacy consumers and Common Resource compatibility.

InvoiceService dependencies Repository/Validator/Calculator/Mapper/Logger are required; InvoiceValidator requires AttributeValidator/Calculator. Service owns transaction, requested lock lookup, uniqueness recheck, exact money, DB exception classification and safe success-afterCommit logging. Calculator internal optional fallback and begin/end INFO are still legacy, not resolved by this documentation or authority for an unrelated fix.

Raw InvoiceCreateInputRequest/InvoiceIdInputRequest/InvoiceQueryInputRequest attributes run before cast; CreateInvoiceRequest(int bookingId)/InvoiceFilterRequest are application data. AttributeValidator applies explicit invoice-create to Invoice code and invoice-create-room only to ROOM name before insert. No observers/global save validation; trim doesn't alter snapshots; SERVICE empty/whitespace/'0' behavior remains. Don't add new constraints that change accepted business inputs under refactor scope.

Production InvoiceMapper remains explicit PHP projection; EloquentObjectMapper is a runtime Symfony adapter/pilot restricted to loaded scalar allowlists. Neither mapper/DTO nor serialization may query/lazy-load, calculate totals or mutate. JsonSerializable output keeps decimal/date/null, omitted list items vs loaded [] detail and one data envelope.

Verify canonical IDs/error order, ownership, exact response/create-to-read parity, no-query validation/projection, rollback/unique conflict/Model faults, safe outer-commit logging and full php artisan test. SQLite isn't MySQL concurrency evidence. Read/create implementation does not close parent/module/browser acceptance; Review migration remains separate.

## Unified validation namespace

Validation namespaces are unified under App\Validators: shared AttributeValidator at the root, custom constraint/validator pairs in App\Validators\Constraints, domain rules under Invoice/Review. Keep pairs together so Symfony validatedBy() resolves the new FQCN. No alias, forwarding class, provider/binding or dependency change is needed; composer dump-autoload --no-scripts refreshes generated autoload metadata without running setup/migration hooks.
