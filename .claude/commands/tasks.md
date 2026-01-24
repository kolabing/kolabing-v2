# Command: /tasks

## Purpose

The `/tasks` command is used to **execute a development task end-to-end** by:

1. Reading the project context from:
   - `CLAUDE.md` / README
   - `.agent/system/*`
2. Loading the task definition from:
   - `.agent/task/todo/<task-name>.md`
3. Creating an implementation plan
4. Assigning work to the correct agents
5. Implementing the task according to project rules

This command turns a task file into **real implementation**.

---

## Task Folder Workflow

```
.agent/task/
├── todo/        → Task definitions waiting to be executed
├── inprogress/  → Currently executing task
└── done/        → Completed tasks for reference
```

### Task Lifecycle
1. **Pending** → Task file exists in `todo/`
2. **Executing** → Task moved to `inprogress/`
3. **Complete** → Task moved to `done/`

---

## Source of Truth

Before doing anything, the agent MUST read:

1. `CLAUDE.md`
2. `.agent/system/architecture.md` (if exists)
3. `.agent/system/database-schema.md` (if exists)

These documents define:
- Architecture
- Tech stack
- Role boundaries
- Agent responsibilities
- Design & MCP rules

If there is a conflict:
> **CLAUDE.md always wins.**

---

## Task Input

Tasks must exist in:

```text
.agent/task/todo/<task-name>.md
```

---

## Execution Flow

### Phase 1 – Task Loading

1. Read task file from `todo/`
2. Move task file to `inprogress/`
3. Update task file with start timestamp
4. Parse task requirements

**Status**: Task now in `inprogress/`

---

### Phase 2 – Planning

**Handled by:** `@agent-orchestrator`

1. Break down task into subtasks
2. Identify required agents
3. Define execution order
4. Update task file with plan

---

### Phase 3 – Frontend Phase (if applicable)

**Agents triggered:**
- `@vue-nuxt-expert`
- `@ui-designer`
- `@design-system-builder` (if needed)

Actions:
- Fetch Figma designs (via MCP)
- Define pages, components, layouts
- Specify required props and data fields
- List UI states

Update task file with frontend details.

---

### Phase 4 – API Contract Phase (if applicable)

**Agents triggered:**
- `@best-practice-finder`
- `@api-designer`

Actions:
- Convert frontend data needs into API endpoints
- Define request/response JSON
- Ensure naming, minimal data, frontend-friendly

Update task file with API contract.

---

### Phase 5 – Backend Phase (if applicable)

**Agents triggered:**
- `@backend-developer`
- `@laravel-code-reviewer`
- `@database-planner` (if schema required)

Actions:
- Implement API contract
- Controller → Service → Repository pattern
- No extra/renamed fields

Update task file with backend progress.

---

### Phase 6 – Documentation

**Agent triggered:** `@agent-orchestrator`

- Final task summary
- Frontend + API + Backend overview
- Key decisions
- Follow-ups

---

### Phase 7 – Task Completion

1. Move task file from `inprogress/` to `done/`
2. Add completion timestamp
3. Update any related documentation

**Status**: Task now in `done/`

---

## Task File Template

Create in `.agent/task/todo/<task-name>.md`:

```markdown
# Task: <task-name>

## Status
- Created: YYYY-MM-DD HH:MM
- Started: (updated when moved to inprogress)
- Completed: (updated when moved to done)

## Description
<detailed task description>

## Requirements
- [ ] Requirement 1
- [ ] Requirement 2

## Assigned Agents
- [ ] @agent-name

## Plan
(filled during planning phase)

## Progress

### Frontend
(to be filled)

### API Contract
(to be filled)

### Backend
(to be filled)

## Files Created/Modified
- (list files)

## Notes
(any additional context)

## Follow-ups
(tasks spawned from this work)
```

---

## Error Handling

- Errors logged to `.agent/sop/` as separate markdown files
- Task remains in `inprogress/` until resolved
- Include:
  - Error description
  - Context
  - Recommended fix

---

## Agent Selection Rules

- Agents are assigned ONLY by Agent Orchestrator
- Agents never self-assign
- Communication goes through orchestrator
- Agent progress MUST be recorded in task file
- You must use agents for developments!

---

## Definition of Done

A `/tasks` execution is DONE only if:
- Task file moved to `done/` folder
- All requirements marked complete
- Implementation delivered
- Documentation updated
- Errors logged in `.agent/sop/` (if any)
