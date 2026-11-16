---
name: stayhub-react
description: Implement or review StayHub React 19 pages, components and domain API services with Vietnamese UI, responsive states and real Laravel contracts. Use for frontend feature work and backend-to-UI integration.
---

# StayHub React

Read [ARCHITECTURE.md](../../../ARCHITECTURE.md) and the relevant [frontend guide](../../../docs/ai-frontend-development-guide.md) sections. Confirm route/layout, direct imports and the actual Laravel response, including data, pagination, role and errors.

## Place logic at the correct boundary

- Page or component owns presentation, interaction and loading/error/success state. Hooks own reusable React state and lifecycle. Keep endpoint and response normalization in the domain boundary.
- Domain service performs input normalization and coordinates API and mapper. Domain API owns HTTP method, URL, params, payload and AbortSignal. Reuse shared Axios for cookie/XSRF and session behavior. Keep the service API stable for existing consumers.
- Put reusable projection in src/mappers. Keep it pure and explicit. Preserve null, missing fields, decimal strings, timestamps, pagination and audience-specific data. Use the actual API contract to distinguish a valid empty result from malformed success.
- Keep loading, refresh, pagination, empty, error and success visible as appropriate. Use cancellation or request identity guards when filters, resource or session can change.
- Guard duplicate mutation submissions. Preserve explicit recovery behavior for network/timeout outcomes. Maintain HTTP status/error identity and map display messages at the UI boundary.

## UI and integration

- Use existing layouts, theme and React primitives. Write user-facing Vietnamese, use Intl.NumberFormat for display, and let the backend determine prices and eligibility.
- Use the real route for the role and verify denied requests. UI guards help navigation while the backend enforces authorization.
- Keep DEV fixtures visibly scoped. Prevent mutations for mock-only resources. Use real APIs in integration verification and record missing external dependencies.
- Trace keyboard focus, semantic labels, error association and overflow at mobile, tablet and desktop widths. Check shared router, context or client changes against the approved scope.

## Verification

- Exercise service, transport and mapper through existing Node tests. Mock only the Vite-configured shared transport when needed to run the real module in Node.
- Run node --experimental-test-module-mocks --test tests/*.test.js, npm run lint and npm run build for frontend changes.
- Report Node fixtures, build and browser separately. Verify browser with real auth/CSRF and API where applicable. Identify a synthetic entry context when a consumer module is still pending integration.
