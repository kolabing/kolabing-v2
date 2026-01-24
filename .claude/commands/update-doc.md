# Command: /update-doc

## Purpose

The `/update-doc` command is used to **safely update project documentation** whenever:
- Architecture changes
- New features are added
- Database schema evolves
- Frontend or backend structure changes
- Business rules are modified

This command ensures documentation always reflects the **current system state**.

---

## Task Folder Workflow

```
.agent/task/
├── todo/        → Doc update task created here
├── inprogress/  → Move here when updating
└── done/        → Move here when complete
```

---

## When to Use

Run `/update-doc` when:
- A new domain is introduced
- An API endpoint is added or changed
- A database table or field is modified
- A new user flow is created
- MCP / Figma integration logic changes
- Access rules change
- A task is completed and documentation needs updating

---

## Execution Flow

### Phase 1 – Task Creation

1. Create `.agent/task/todo/doc-update-<topic>.md`
2. **Status**: Task in `todo/`

---

### Phase 2 – Start Update

1. Move task from `todo/` to `inprogress/`
2. Update task file with start timestamp
3. Identify scope of documentation update

**Status**: Task now in `inprogress/`

---

### Phase 3 – Update Documentation

Depending on the change, update one or more of the following:

#### System Docs
- `.agent/system/architecture.md`
- `.agent/system/database-schema.md`
- `.agent/system/design-mapping.md`

#### Task Docs
- `.agent/task/done/*.md` (add references)

#### Root Docs
- `CLAUDE.md`

---

### Phase 4 – Task Completion

1. Move task file from `inprogress/` to `done/`
2. Add completion timestamp

**Status**: Task now in `done/`

---

## Update Rules

1. **Never remove existing information unless it is incorrect**
2. **Append or refine instead of rewriting everything**
3. **Keep language concise and technical**
4. **Respect domain boundaries**
5. **Do not invent features that do not exist**
6. **If unsure, mark sections as TODO**

---

## Doc Update Task Template

Create in `.agent/task/todo/doc-update-<topic>.md`:

```markdown
# Doc Update: <topic>

## Status
- Created: YYYY-MM-DD HH:MM
- Started: (updated when moved to inprogress)
- Completed: (updated when moved to done)

## Trigger
<what change triggered this update>

## Scope
- [ ] CLAUDE.md
- [ ] .agent/system/architecture.md
- [ ] .agent/system/database-schema.md
- [ ] .agent/system/design-mapping.md
- [ ] Other: ___

## Changes Made
(list what was updated)

## Affected Domains
- (list domains)
```

---

## Update Format

Each update must:
- Clearly describe what changed
- Specify affected domains
- Reflect the current implementation state

### Example Update Block

```md
## Update – Game Catalog API (2025-03-XX)

- Added new endpoint: `/api/games/{id}`
- Updated pagination logic
- Affects:
  - Game domain
  - Frontend product page
  - API documentation
```

---

## Definition of Done

A `/update-doc` is DONE only if:
- Task file moved to `done/` folder
- All relevant docs updated
- Changes documented in task file
- No stale or contradictory information remains
