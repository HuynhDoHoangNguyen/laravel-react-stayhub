---
name: stayhub-task-planning
description: Plan or resume StayHub implementation tasks across Laravel, React and MySQL; identify owner, existing code, dependencies, allowed files and acceptance criteria before edits. Use for scoped feature work, not simple factual questions.
---

# StayHub task planning

Use for scoped changes in Laravel, React and MySQL. Read applicable repository instructions and [ARCHITECTURE.md](../../../ARCHITECTURE.md). Confirm the owner in [team assignment](../../../docs/stayhub-team-work-assignment.md). Use the [TV3 checklist](../../../docs/TV3/TV3_CHECKLIST.md) for TV3 work.

## Establish the baseline

1. Capture Git branch, HEAD and existing changes. Treat the current working tree as the baseline when it contains accepted uncommitted work.
2. Follow registered routes, HTTP validation, permission checks, DTOs, workflow, persistence, response mapping, frontend consumers and tests. Check schema and seeders when the task touches data.
3. Identify which parts actually exist, which use a legacy contract and which depend on another module. Confirm actual operation before treating documentation, mocked UI or a proposed endpoint as available.
4. Confirm ownership, approved file scope and shared dependencies. Name the relevant external contract when another member owns it.
5. Choose a slice of 30–90 minutes under the repo workflow. Include both backend and frontend for user-facing behavior in scope. Split a larger change at a verifiable boundary.

## Apply the architecture

- Use the flow Route → Controller → validation/typed Request DTO → Service or QueryService → Repository/Model → Mapper/Response DTO. Keep status and envelope at the HTTP boundary.
- Choose new layers by responsibility. Reuse a concrete Repository and constructor DI when they provide a clear persistence boundary. Keep legacy HTTP entry points compatible until their consumers have been checked.
- Plan raw-input validation before conversion where type or error order matters. Pass the authenticated actor separately. Plan explicit Symfony groups and mapping projection only for the flows that use them.
- For frontend, trace Page/Component → Domain Service → Domain API → shared Axios. Name the returned shape and the mapper's job. Preserve cancellation, Auth/session handling and mutation retry behavior.
- Identify which contracts remain open. Agree on domain decisions such as transitions, time ranges or financial semantics before implementation of the affected part.

## Mini Plan on chat

Use these seven parts under the project workflow:

1. Goal, current implementation and remaining gap.
2. Approach and Backend/Frontend boundaries.
3. Schema, business rules, API contract and open decisions.
4. Verification for success, errors, permissions and applicable runtime cases.
5. Exact file table with create/modify/delete and reason.
6. Acceptance criteria with IDs, observable evidence and initial status.
7. Execution order, dependencies, risk and sizing.

Stop after the plan when approval is required. Resume an unchanged approved plan from the last verified state. Record task history and tested evidence in the authorized checklist, and keep architecture and skill text focused on durable guidance.
