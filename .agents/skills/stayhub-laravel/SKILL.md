---
name: stayhub-laravel
description: Implement or review StayHub Laravel 12 APIs and services, including HTTP validation, authorization, Eloquent, transactions, resources and PHPUnit tests. Use for backend feature changes across the assigned team modules.
---

# StayHub Laravel

Use [ARCHITECTURE.md](../../../ARCHITECTURE.md) and the actual authorized slice. Start from registered routes and middleware, trace to callers and tests, and check runtime contracts before restructuring an endpoint.

## Choose responsibilities

- Route and middleware own method/path, session, role gates, account-state checks and rate limits that apply to the route. Enforce resource ownership at the policy/workflow/query boundary. Keep established privacy and error order.
- Keep controllers focused on extracting actor/input and invoking the appropriate validator, DTO, service and response helper.
- Use one validation root under app/Validators. Put shared Symfony infrastructure in AttributeValidator and Constraints, and domain rules under their domain directory. Reuse Form Requests where the existing HTTP boundary still uses them.
- Check raw input before casting when canonical IDs, types or error messages matter. Convert to a typed Request DTO for the workflow and pass the authenticated actor separately. Keep validators query-free by passing facts resolved by the service.
- Service owns workflow, transaction and order of checks. Concrete Repository holds scoped queries, eager loading and persistence. Use constructor DI for required collaborators. Introduce binding/interface only for a concrete need.
- Eloquent Model holds casts, fillable or guarded fields, relationships and entity scopes. Whitelist fields on writes. Derive fields such as owner, status and totals from authorized server data.

## Validate and map explicitly

- Use Symfony Validator attributes where they express reusable input or model constraints. Call an explicit group for a model candidate before its insert/update when required by the workflow. Test the active and unaffected groups.
- Place mappers under app/Mappers. Use EloquentObjectMapper with loaded attributes, an explicit allowlist and the existing Symfony registration for Model-to-DTO projections. Compose nested values from loaded relations. Construct a derived response DTO directly when it only carries a command result.
- Keep mapping free of queries, writes and authorization. Use typed Response DTOs with explicit JSON fields. Preserve nullable values, absent fields, collection shape, dates and decimal strings.
- Keep public payloads scoped to the consumer. Preserve status, envelope, field errors, existing headers and route-scoped exception handling. Use Common helpers where they fit the contract and verify legacy consumers before removing a compatibility path.

## Transactions, logging and checks

- Resolve ownership, state and uniqueness at the appropriate step. Recheck facts that can change under concurrency while the necessary locks are held.
- Use exact decimal or appropriately scaled integer arithmetic for money. Source financial data from stored snapshots per module contract.
- Log meaningful operations with safe identifiers. Record committed success after the real outer commit. Let Laravel report unexpected errors. Catch only where the workflow needs exception classification, translation or compensation.
- Verify auth/role/ownership, field validation, projections and the endpoint's existing error precedence. Exercise rollback, conflict and retry when they are in scope. Use an independent expected value for financial tests.
- Run focused tests and php artisan test for Backend work with isolated data. Use the relevant engine for locking or collation. Verify frontend consumers when they are part of the slice.
