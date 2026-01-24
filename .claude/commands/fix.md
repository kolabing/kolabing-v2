# /fix Command – Agent Orchestrator Specification

## Purpose

The `/fix` command is used to **detect, analyze, and fix existing issues** in the codebase.

This command is **NOT** for new feature development.

It is strictly for:
- Bugs
- Contract mismatches
- Architecture violations
- Best practice improvements
- Incomplete or incorrect implementations
- You must use agents for developments!

---

## Task Folder Workflow

```
.agent/task/
├── todo/        → Fix task created here
├── inprogress/  → Move here when work starts
└── done/        → Move here when fix is complete
```

---

## When to Use `/fix`

Use `/fix` when:
- A feature exists but behaves incorrectly
- Frontend and backend JSON contracts do not match
- API responses break frontend expectations
- Code violates project architecture rules
- Performance, security, or best practices need improvement
- Documentation is missing or outdated for an implemented feature

Do NOT use `/fix` for:
- New pages
- New endpoints
- New flows
- New business logic

---

## High-Level Flow

When `/fix` is issued, the **Agent Orchestrator** must follow this exact flow.

---

## Phase 1 – Task Creation

**Action:** Create fix task file

1. Create `.agent/task/todo/fix-<issue-name>.md`
2. Use the fix task template (see below)
3. **Status**: Task in `todo/`

---

## Phase 2 – Issue Understanding

**Handled by:** `@product-manager`

1. Move task from `todo/` to `inprogress/`
2. Update task file with start timestamp
3. Read the `/fix` input
4. Identify:
   - Affected area (Frontend / Backend / API / Docs)
   - User role (if applicable)
5. Determine if the issue is:
   - UI bug
   - API contract mismatch
   - Backend logic bug
   - Architecture violation
   - Best practice issue

**Status**: Task now in `inprogress/`

No fixes are made in this phase.

---

## Phase 3 – Agent Assignment

**Handled by:** `@product-manager`

Based on the issue type, the orchestrator assigns agents:

### Frontend Issues
- `@vue-nuxt-expert`
- `@ui-designer` (if visual or UX-related)
- `@design-system-builder` (if design token or Tailwind issues exist)

### API / Contract Issues
- `@api-designer`
- `@best-practice-finder`

### Backend Issues
- `@backend-developer`
- `@laravel-code-reviewer`
- `@database-planner` (if schema-related)

Agents are NEVER self-assigned.

Update task file with assigned agents.

---

## Phase 4 – Root Cause Analysis

**Handled by:** Assigned agents

Each agent must:
- Identify the root cause
- Explain what is wrong
- Reference:
  - API contracts
  - Frontend expectations
  - Architecture rules
- Propose a fix before implementation

Output (update in task file):
- Clear problem statement
- Proposed solution
- You must use agents for developments!

---

## Phase 5 – Fix Implementation

**Handled by:** Assigned agents

Rules:
- Fix ONLY the identified issue
- Do NOT introduce new features
- Do NOT change API contracts unless explicitly required
- Respect existing architecture:
  - Frontend-first
  - API contract-driven
  - Controller → Service → Repository (backend)

Update task file with implementation details.

---

## Phase 6 – Validation

**Handled by:** `@agent-orchestrator`

Validation checklist:
- Issue is resolved
- No new regressions introduced
- Frontend and backend remain aligned
- Naming conventions preserved
- No unnecessary refactors

If validation fails → return to Phase 4.

---

## Phase 7 – Fix Documentation (MANDATORY)

Every `/fix` execution MUST produce documentation.

Update task file with:
- Issue summary
- Root cause
- Fix applied
- Files affected
- Any follow-up recommendations

---

## Phase 8 – Task Completion

1. Move task file from `inprogress/` to `done/`
2. Add completion timestamp
3. Log lessons learned to `.agent/sop/` if applicable

**Status**: Task now in `done/`

No documentation = fix is NOT complete.

---

## Fix Task Template

Create in `.agent/task/todo/fix-<issue-name>.md`:

```markdown
# Fix: <issue-name>

## Status
- Created: YYYY-MM-DD HH:MM
- Started: (updated when moved to inprogress)
- Completed: (updated when moved to done)

## Issue Type
- [ ] UI Bug
- [ ] API Contract Mismatch
- [ ] Backend Logic Bug
- [ ] Architecture Violation
- [ ] Best Practice Issue

## Affected Area
- [ ] Frontend
- [ ] Backend
- [ ] API
- [ ] Documentation

## Problem Statement
(to be filled by assigned agent)

## Root Cause
(to be filled after analysis)

## Proposed Solution
(to be filled before implementation)

## Implementation Details
(to be filled during fix)

## Files Affected
- (list files)

## Assigned Agents
- [ ] @agent-name

## Follow-up Recommendations
(any additional notes)
```

---

## Agent Behavior Rules for `/fix`

- Agents must be conservative
- Prefer minimal changes
- Avoid refactors unless required
- Never "improve" unrelated code
- Never expand scope
- You must use agents for developments!

---

## Definition of Done

A `/fix` is DONE only if:
- Task file moved to `done/` folder
- Root cause documented
- Fix implemented and validated
- Files affected listed
- SOP updated (if pattern worth remembering)
