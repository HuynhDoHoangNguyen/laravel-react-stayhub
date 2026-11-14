---
name: stayhub-react
description: Implement or review StayHub React 19 pages, components and domain API services with Vietnamese UI, responsive states and real Laravel contracts. Use for frontend feature work and backend-to-UI integration.
---

# StayHub React

Read applicable instructions, docs/ai-frontend-development-guide.md, existing layout/theme/routes and the target's direct imports. Work within assigned ownership. Shared AppRouter.jsx and Axios changes require authorized scope.

## Contract and UI

- Trace Page → component/hook → domain service in frontend/src/services → frontend/src/api/axios.js → registered Laravel endpoint/DTO JSON (or legacy Resource). Invoice/Review services already delegate HTTP to domain API modules; other legacy domains migrate separately while services own mapping/coordination. Reuse shared Axios and Sanctum cookie/CSRF behavior.
- Confirm actual response fields, pagination metadata, statuses and errors. A planned endpoint or matching template name is not an implemented API. Missing external dependencies remain explicit blockers.
- Use Vietnamese copy, existing components/theme and Intl.NumberFormat for VND display. Backend determines final prices; formatting is not permission to calculate financial totals in the browser.
- Implement initial loading, refresh/pagination, true empty, error, success and validation states. Preserve the distinction between 401, 403, 404, 409 and 422 where the API uses them. Avoid displaying raw stack traces.
- Prevent duplicate submits. Specify deliberate retry behavior after an ambiguous network result; do not silently replay a mutation. Avoid showing stale data after filter/resource/auth changes by cancellation or response identity guards appropriate to existing auth infrastructure.
- Inspect narrow mobile, tablet and desktop layout, readable forms, keyboard focus, semantic labels, overflow and pagination behavior. Use dangerouslySetInnerHTML only if the task has an explicit trusted sanitization contract; ordinary user content renders as text.
- Never hardcode demo data into production UI or fall back to mock on real API failure. Development fixtures must be explicitly gated/labeled; a mock-backed success cannot close an integration AC.
- Reuse StayNest visual patterns only; don't import its unrelated endpoints or business logic.

## Verification and reporting

Run `npm run lint` and `npm run build` for frontend changes. Browser checks should exercise real contract behavior when claiming integration; record what was actually tested, including viewport and auth/ownership conditions.

No frontend test runner or browser harness should be assumed available: inspect package.json and existing tooling first. Propose tooling as a separate scoped task if necessary. Report build success, mocked/component tests, manual browser inspection and real E2E as distinct evidence.

On chat, describe changed screens, Backend dependency, states covered, commands/results, responsive evidence and remaining gaps. Update a checklist only when the task authorizes it; no separate plan/result files.

## Current Invoice service and backend compatibility

See [architecture](../../../ARCHITECTURE.md) sections 13/14. One services/invoiceService.js owns create/list/detail Host-Customer. createInvoiceService(invoiceApi) factory and existing named exports live in that same file; don't recreate split create/read forwarding modules. invoiceApi.js owns transport, shared Axios owns cookie/XSRF/Auth infrastructure, invoiceMapper.js owns pure projection. Keep signatures/defaults/filter normalization/options.signal and helper behavior.

Create/detail both use mapInvoiceDetailResponse and return {data: Invoice}; lists use mapInvoiceListResponse, preserving meta/links and extra pagination metadata. Component uses result.data; no raw-body fallback. Preserve snake_case, decimal strings/null/date/items and SERVICE names empty/whitespace/'0'. Malformed success throws INVALID_INVOICE_RESPONSE; unknown nonempty payment status isn't coerced to UNPAID. No frontend financial calculations or automatic POST replay.

Backend Invoice read/create use InvoiceValidator, typed DTOs, concrete Repository and Common; Invoice Resources are removed. Read stable error codes/framework 422 fields and expected create message-only errors coexist. Create still responds 201 with one data envelope. Global renderer applies selected Invoice/Review routes, not a new Auth/Web contract. Keep existing interceptors/version/rejection identity/cancellation; never register logout handling in a domain service.

Frontend checks: node --experimental-test-module-mocks --test tests/*.test.js, npm run lint, npm run build. tests/invoiceService.test.js mocks only the Vite-configured shared Axios module before importing the real factory/public exports; this requires the module-mocks flag. Don't change production Axios or add dependencies to make Node load import.meta.env. Adapter fixtures and compiled builds are not browser/real API evidence.

Exercise all five public operations, malformed success, decimal/null/SERVICE compatibility, invalid-ID no request, HTTP/Auth account-vs-role failures/session versions and network/timeout/cancel no retry. UI double-click/stale/unmount/auth-change/responsive gates need browser evidence; do not infer them from service tests. Review and other domains migrate under their own scope. Docs-only work doesn't rerun or relabel historical application tests; parent acceptance remains distinct.

## Unified validation namespace

Backend validation now uses one app/Validators root (shared AttributeValidator, Constraints/, domain validators). This internal namespace move preserves Symfony attributes, API/Auth/status/body contracts; do not mirror it into frontend modules or introduce a new service. Existing Node adapter regressions still gate compatibility.
