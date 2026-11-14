---
name: stayhub-task-planning
description: Plan or resume StayHub implementation tasks across Laravel, React and MySQL; identify owner, existing code, dependencies, allowed files and acceptance criteria before edits. Use for scoped feature work, not simple factual questions.
---

# StayHub task planning

Read applicable AGENTS instructions and the current request. This skill is shared by TV1/TV2/TV3 and grants no cross-owner write permission. Read AGENTS.local.md and the TV3 checklist only for explicitly applicable TV3 work. Existing root instructions still apply to everyone in their scope.

## Establish facts

1. Inspect branch, HEAD and dirty files. New branches use `feature/` unless the user specifies otherwise. Do not create a branch just to answer a question.
2. Read the project's required architecture/use-case/assignment/frontend/ERD context, then discover exact files with rg. Follow the relevant route → controller/validator → authorization/DTO → service/repository/model → DTO/Common response (legacy resource where present) → frontend API/service/mapper/page → tests. Do not scan generated vendor/node_modules or copy StayNest behavior.
3. Distinguish proposed endpoints from registered routes, migration files from applied schema, and placeholders/mock UI from implemented features. Preserve historical results as historical.
4. Identify owner: TV1 identity/catalog, TV2 booking/operations, TV3 billing/review/reporting/FAQ/chat. Track shared integration files explicitly. A dependency is not permission to implement another member's business logic.
5. Select a 30–90 minute task. Split larger work into reviewable outcomes with dependencies. UI features require a vertical slice; technical foundations may be separate but do not close the parent UC.

## Present a detailed Mini Plan on chat

Include these seven parts with concrete project paths and observable criteria:

1. Goal, current implementation, remaining gap and excluded scope.
2. Implementation approach and Backend/Frontend boundary.
3. Schema, business rules, API contract and unresolved decisions.
4. Success/error/validation/role/ownership cases plus applicable concurrency, stale-response and responsive cases.
5. File table: new/modified, responsibility and why shared files are needed.
6. AC IDs, expected evidence, initial NOT VERIFIED status and prerequisites.
7. Execution order, dependencies, risks and task sizing.

Wait for the developer's plan approval where required by project instructions. An approved unchanged plan need not be approved again. Do not invent unresolved payment/revenue rules. Continue independent read-only investigation when a dependency is missing, and report the blocked portion.

Plans and Task Results belong on chat, not newly created report/template files. Update only an authorized existing checklist for durable progress. Do not create a personal task tracker for TV1/TV2 merely because TV3 has one.

## Decision examples

- “Implement UC36”: inspect Invoice state/schema first; confirm transition/paid_at contract; plan API + Host UI + tests. Do not infer all enum transitions are allowed.
- “Finish Customer Review”: distinguish working Review API from missing TV2 booking detail endpoint. Keep integration open; propose a handoff rather than silently changing booking logic.
- “Build Host revenue dashboard”: resolve invoice status/date/refund semantics before affected query implementation; UI shell cannot satisfy revenue AC.

## Current architecture and planning boundaries

Read [ARCHITECTURE.md](../../../ARCHITECTURE.md) sections 4/13/14 and the frontend guide sections 17-19. Invoice read/create now use InvoiceValidator, InvoiceMapper, Request/Response DTOs, InvoiceRepository and Common. HTTP create calls createInvoiceFromRequest(CreateInvoiceRequest, actor) and returns mapped DTO with ApiResponse.success(201); legacy int entry point remains compatible. One invoiceService.js owns five frontend operations through invoiceApi/shared Axios and invoiceMapper.

Plans distinguish raw mixed DTO attributes from normalized typed commands, actor from session, Model validation groups, pure mapping, Repository lookup/persistence, transaction and exception/logging boundary. Concrete Repository defaults to one file/domain with constructor DI; don't generate interface/binding/provider/Form Request/Resource pairs automatically. Symfony engine registrations are justified infrastructure, not Repository bindings.

Preserve different error orders: read role-before-input/privacy lookup; create raw ID before Controller actor branch, then locked Booking lookup and Service role/ownership/status before duplicate/snapshot checks. Read uses stable codes/framework field errors; expected create errors remain message-only. Common success does not imply a universal error-envelope migration.

Model attributes are explicit invoice-create and invoice-create-room (ROOM only), not global save validation. SERVICE blank names retain current behavior. Runtime InvoiceMapper is explicit projection; Symfony EloquentObjectMapper remains adapter/pilot, not an automatic replacement or Java compile-time mapper. Workflow required DI does not mean Calculator internal optional fallback/logging has been removed.

No split create/read frontend forwarding modules by habit: factory and public exports live in invoiceService.js. Name result shapes, signal/Auth compatibility, malformed-success rejection and no-replay mutation behavior in frontend scope. Keep Review/TV1/TV2 ownership and legacy inventories separate.

Owner accepted 02C.1/02C.2/02C.3 and the audit; parent INV-ARCH-02 is CLOSED / ACCEPTED. Subsequent tasks require their own acceptance. Browser/MySQL gates and unresolved legacy/advisories stay explicit. Historical names/counts belong in dated checklist evidence, not current inventories. Docs/skills-only tasks validate links/content/skills/scope; don't relabel old PHP/Node/build results as fresh execution.

## Unified validation namespace

Use one application validation root: app/Validators/AttributeValidator.php for the shared Symfony adapter, app/Validators/Constraints/ for custom attributes/constraint validators, and Validators/<Domain>/ for business validators. Plan import/autoload/constraint-resolution tests when moving classes; keep HTTP/DTO groups/messages unchanged. Parent INV-ARCH-02 is CLOSED / ACCEPTED; VAL-STRUCT-01 is a separate namespace task.
