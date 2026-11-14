---
name: stayhub-verification
description: Verify StayHub changes against approved acceptance criteria, distinguish test and runtime evidence, report detailed Task Results on chat, and update authorized checklists. Use for feature acceptance, review and completion checks.
---

# StayHub verification

Read approved scope/AC, applicable instructions, changed code and actual test tooling. Do not infer authorization to fix unrelated failures, create a harness, mutate a shared DB or start the next task.

## Evidence gates

1. Inspect Git status/diff and identify changes that predate the task. Check every required Backend/Frontend part; technical foundations do not close a parent UI UC.
2. Match each AC to an observable assertion and relevant test. Validate prerequisites and effective database isolation before running commands.
3. Run required checks: `php artisan test` for Backend work; `npm run lint` and `npm run build` when Frontend changes; Git status and `git diff --check`. Documentation/skill-only work uses link/content/scope/skill validation, not unnecessary application suites unless project instructions explicitly require them.
4. Separate unit, HTTP feature, SQLite integration, MySQL integration/concurrency, frontend build, component/mock tests and browser E2E. One category cannot stand in for another.
5. For real E2E, identify actual served build/source, real auth and API, isolated DB/storage, browser/viewport, request/response assertions, persisted results and cleanup ownership. Intercepting a real delayed response differs from fabricating success JSON. A runner not yet built remains NOT VERIFIED.
6. Exercise applicable cross-owner denial, duplicate submission, stale responses and decimal boundary cases. Expected values must not merely repeat production calculations; use independent fixtures/invariants and negative controls when validating a custom verifier.
7. Missing prerequisites/unexecuted cases are NOT VERIFIED; failed commands/assertions are FAIL. No skipped success. Record warning-only checks accurately. Preserve failed-run evidence when it explains a remaining issue.

Evidence files are optional and created only when needed under an authorized task/run directory. Record timestamp, HEAD plus dirty state, command/exit, environment, counts and limitations. Do not dump tokens, cookies, credentials or personal data. Never fabricate logs/screenshots/RESULT.json.

## Detailed Task Result — on chat only

State Task ID/goal, technical Status (TECHNICAL PASS / PARTIAL / FAIL) and Owner Acceptance separately. Then provide:

- **Implemented:** distinguish ALREADY EXISTED, IMPLEMENTED, MODIFIED and VERIFIED; Backend and Frontend outcomes or why UI does not apply.
- **Files Changed:** exact paths, action, reason; include deviations. Read-only tasks say NONE.
- **Tests Executed:** command/category, PASS/FAIL/NOT RUN, count/exit when available, evidence, environment and limits.
- **Business & Contract Fidelity:** Source-confirmed / Implementation inference / Unspecified–unresolved.
- **Acceptance Criteria:** each AC ID, PASS/FAIL/NOT VERIFIED, supporting evidence. TECHNICAL PASS requires every mandatory AC.
- **Risks / Deviations:** unresolved dependencies, scope/environment limits, failed or untested cases.
- **Recommended Next Task:** one concrete next step; do not execute it automatically.

Do not generate a standalone plan or Task Result file. Update only authorized checklists: implementation, verification, acceptance, blocker/evidence/next action. `[x]` requires mandatory AC PASS and recorded owner acceptance; plan approval is not acceptance. Historical results must remain dated and attributed, not represented as newly executed.

## Review controls

- Review UI implemented but TV2 booking API absent: integration remains open even if Review feature tests pass.
- Invoice unit/API tests on SQLite pass: MySQL lock/race behavior remains unverified until tested there.
- Lint/build pass but no browser run: report build PASS and browser NOT VERIFIED.
- Owner approves plan: proceed within scope, but don't mark delivery ACCEPTED.

## Current Invoice verification matrix

Read [architecture](../../../ARCHITECTURE.md) sections 4/13/14 and actual approved scope. Verify InvoiceValidator/InvoiceMapper, raw attribute DTOs vs typed CreateInvoiceRequest/InvoiceFilterRequest, concrete InvoiceRepository, Service/QueryService and JsonSerializable Response DTO/Common. Four read and six create Repository methods exist; no Repository interface/binding is required. Symfony engine registrations still have a purpose.

Read role precedes malformed query/ID/lookup; guest middleware owns 401, Repository preserves ownership/privacy-404 and QueryService checks role outside HTTP. Create preserves its different order: raw ID then Controller actor branch, locked Booking lookup then Service role/ownership/status before duplicate and snapshot/financial checks. Expected create errors remain message-only 403/404/409/422; read codes/framework fields and Auth/Web/header contracts remain unchanged.

HTTP create must consume CreateInvoiceRequest through typed entry point and serialize Mapper/InvoiceResponse via ApiResponse.success(201), with exact envelope/fields/date/null/decimal/items and create-to-read parity. Invoice Resources are deleted; tests use independent expected payloads rather than production Mapper/DTO as the oracle. Do not delete other modules' legacy Requests/Resources or Common compatibility branches.

Model validation explicitly calls invoice-create (Invoice) and invoice-create-room (ROOM only) before corresponding insert; default/save and SERVICE semantics stay unchanged. Verify blank/null/whitespace constraints, zero queries/no mutation, header rejection before insert, ROOM failure rolling header back, no success log and real outer-commit behavior. Candidate validation doesn't prove every later Eloquent-event mutation is rejected. Runtime InvoiceMapper is explicit; Symfony mapping adapter/pilot is not a fully adopted MapStruct equivalent. Calculator legacy logging/optional DI and security advisories remain separately unresolved.

Frontend: one invoiceService.js factory/public exports for five create/read operations, invoiceApi/shared Axios and invoiceMapper. Test rejection identity/Auth session versions/account-vs-role failures, signal, malformed success, decimal/null/SERVICE values, invalid-ID no HTTP and no replay POST. Run node --experimental-test-module-mocks --test tests/*.test.js, lint/build when frontend source changes. Module mock replaces only Vite-configured Axios for tests; adapters are not live backend/browser evidence.

Docs/skills-only changes run five skill validators, current links/anchors/content/source review, UTF-8/whitespace and a before/after hash scope including ignored AGENTS.local.md/skill metadata. Historical deleted paths/counts in dated checklist evidence stay labeled as history; don't rewrite them into current links. Keep AGENTS.md/application code/metadata unchanged when excluded. Application suites aren't rerun solely to synchronize docs; cite previous results with their task source. Static walkthrough is not observed skill auto-selection or browser execution. Parent technical readiness, owner acceptance and open UI/MySQL gates stay separate.

## Unified validation namespace

For VAL-STRUCT-01 verify App\Validators\AttributeValidator and App\Validators\Constraints classes autoload, container resolves the adapter, and Symfony validatedBy()/DTO reflection execute the renamed pair. Old namespace is allowed only in explicit negative alias tests or dated history, not runtime imports. Check 3 namespace-only moves, 8 consumer/test edits plus 9 guidance/checklist edits; preserve AGENTS.md and parent INV-ARCH-02 CLOSED / ACCEPTED.
