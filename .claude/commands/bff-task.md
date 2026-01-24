# Command Execution Contract – Task Folder Workflow

This document defines how **slash commands** trigger agent execution
using the `.agent/task/` folder structure with `todo`, `inprogress`, and `done` subfolders.

---

## Task Folder Structure

```
.agent/task/
├── todo/        → New tasks waiting to be started
├── inprogress/  → Currently active tasks (max 1 recommended)
└── done/        → Completed tasks for reference
```

### Task Lifecycle

1. **Created** → File created in `todo/`
2. **Started** → File moved to `inprogress/`
3. **Completed** → File moved to `done/`

---

## Core Rule

Any command starting with `/` is an **explicit execution request**.

Slash commands are NOT documentation. Slash commands MUST trigger agents.

---

## Plan Mode Override

If the system is running in **plan / analysis mode**:

- Slash commands automatically override plan-only behavior
- The system switches to **execution intent**
- Agents MUST be invoked according to the command flow
- Plan mode can only be used for task breakdown and ordering steps
- You must use agents for developments!

---

## Agent Invocation Rule

When a slash command is detected, the Agent Orchestrator MUST:

1. Identify required agents
2. Explicitly invoke each agent by name
3. Wait for their output
4. Move task file between folders as progress changes
5. Log any encountered issues to `.agent/sop/`

Implicit reasoning is NOT sufficient. Agents must be actively called.

---

## Supported Commands

### `/bff-tasks`
Triggers the **Backend For Frontend workflow**.

Execution Flow:

1. **Task Creation**
   - A new markdown file is created in `.agent/task/todo/<task-name>.md`
   - Includes:
     - Task description
     - Assigned agents
     - Initial placeholders for progress updates
   - **Status**: Task remains in `todo/`

2. **Task Start**
   - Move task file from `todo/` to `inprogress/`
   - Update task file with start timestamp
   - **Status**: Task now in `inprogress/`

3. **Frontend Phase**
   **Agents triggered:**
   - `@vue-nuxt-expert`
   - `@ui-designer`
   - `@fullstack-engineer ` (if needed)


3. **AI Phase**
   **Agents triggered:**
   - `@prompt-engineer`
   - `@fullstack-engineer ` (if needed)

   Actions:
   - Fetch Figma designs (via MCP)
   - Define pages, components, layouts
   - Specify required props and data fields
   - List UI states

   Output:
   - Updated `.agent/task/inprogress/<task>.md` with frontend details

4. **API Contract Phase**
   **Agents triggered:**
   - `@product-manager`
   - `@api-designer`
   - `@fullstack-developer`
   - `@laravel-specialist`

   Actions:
   - Convert frontend data needs into API endpoints
   - Define request/response JSON
   - Ensure naming, minimal data, frontend-friendly

   Output:
   - Updated `.agent/task/inprogress/<task>.md` with API contract

5. **Backend Phase**
   **Agents triggered:**
   - `@fullstack-developer`
   - `@laravel-specialist`
   - `@database-planner` (if schema required)
   - `@api-designer`

   Actions:
   - Implement API contract
   - Controller → Service → Repository pattern
   - No extra/renamed fields

   Output:
   - Updated `.agent/task/inprogress/<task>.md` with backend progress

6. **Documentation Phase**
   **Agent triggered:** `@agent-orchestrator`
   - Final task summary
   - Frontend + API + Backend overview
   - Key decisions
   - Follow-ups

7. **Task Completion**
   - Move task file from `inprogress/` to `done/`
   - Add completion timestamp to task file
   - **Status**: Task now in `done/`

8. **Error Handling**
   - Any errors encountered are logged to `.agent/sop/` as separate markdown files
   - Includes:
     - Error description
     - Context
     - Recommended fix
   - Task remains in `inprogress/` until resolved

---

### `/fix`
Fix existing issues.
- Create fix task in `todo/`, move to `inprogress/` when starting
- Minimal changes only
- Move to `done/` when complete
- Errors logged in `.agent/sop/`

### `/task`
Create new features.
- Task created in `todo/`
- Move to `inprogress/` when work begins
- All phases (frontend → API → backend → docs) executed
- Move to `done/` when complete
- Errors logged to sop

### `/refactor`
Improve code quality.
- Task created in `todo/`
- Move to `inprogress/` during work
- Architecture preserved
- No API contract changes
- Move to `done/` when complete
- Errors logged to sop

---

## Task File Template

When creating a new task in `todo/`:

```markdown
# Task: <task-name>

## Status
- Created: YYYY-MM-DD HH:MM
- Started: (updated when moved to inprogress)
- Completed: (updated when moved to done)

## Description
<task description>

## Assigned Agents
- [ ] @agent-name

## Progress
### Frontend
(to be filled)

### API Contract
(to be filled)

### Backend
(to be filled)

## Notes
(any additional context)
```

---

## Agent Selection Rules

- Agents are assigned ONLY by Agent Orchestrator
- Agents never self-assign
- Communication goes through orchestrator
- Agent progress MUST be recorded in task file
- You must use agents for developments!

---

## Execution Guarantee

- Slash commands MUST trigger agents
- Plan mode does NOT block execution
- Skipping agent invocation = system error
- Task files moved between folders to reflect status
- SOP logs created for errors

---

## Definition of Done

A command is DONE only if:
- Required agents were invoked
- Expected outputs produced
- Task file moved to `done/` folder
- Errors logged in `.agent/sop/` (if any)
- Documentation produced (if required)
