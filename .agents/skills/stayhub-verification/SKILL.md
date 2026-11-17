---
name: stayhub-verification
description: Verify StayHub changes against approved acceptance criteria, distinguish test and runtime evidence, report detailed Task Results on chat, and update authorized checklists. Use for feature acceptance, review and completion checks.
---

# StayHub verification

Use the approved scope, observable acceptance criteria and [ARCHITECTURE.md](../../../ARCHITECTURE.md). Track owner acceptance in the authorized checklist for the relevant module.

## Verify the work

1. Record Git branch, HEAD and existing changes. Compare scoped file changes against the baseline and check whitespace.
2. Map each mandatory AC to a concrete test or inspection. Use production entry points that users and existing consumers exercise.
3. Check endpoint status, JSON shape, error order, role and ownership, nullable/omitted fields, pagination and response mapping when applicable. Verify transaction rollback, outer commit, locks and data integrity in the right environment.
4. Run php artisan test for Backend work and Node tests, lint and build for Frontend work. Documentation and skill changes use content, syntax/frontmatter, links, anchors and scope checks.
5. Separate SQLite, XAMPP/MySQL, mocked transport, build, browser and live-provider evidence. Check the effective database target and record ownership of fixtures.
6. For real browser tests, verify actual React source or build, Laravel API, session/CSRF, persisted results and applicable role, viewport, cancellation or duplicate actions. Label any synthetic entry context. Close temporary services and clean owned fixtures.
7. Record actual command results. Use FAIL for failed checks and NOT VERIFIED where a case could not be observed. Report relevant warnings and the effect of any rerun or deviation.

Use independent expected values and negative controls where they establish actual behavior. Keep evidence dated, attributable to the original run and free of secrets.

## Task Result on chat

State Task ID, goal, technical status and owner acceptance separately. Use these seven parts:

1. Implemented or verified Backend and Frontend outcomes.
2. Files changed with exact paths, actions and deviations.
3. Tests executed, exit/result, counts, engine and limits.
4. Business and contract fidelity from code and observed behavior.
5. Acceptance criteria checklist with status and evidence per ID.
6. Remaining risks, dependencies and deviations.
7. One proposed next task. Wait for its own scope.

Use TECHNICAL PASS when every mandatory AC has supporting evidence. Mark an item closed only when its required checks passed and the owner accepted that scope. Keep refactor approval and use-case closure distinct. Update authorized checklists with evidence and remaining action. Report Mini Plan and Task Result on chat.
