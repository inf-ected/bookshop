---
description: Prepare context for a phase or sub-phase and launch the correct agent
argument-hint: <phase> (e.g. 5.1, 5.2, 5, 6)
allowed-tools: [Read, Grep, Bash]
---

The user wants to start phase: $ARGUMENTS

## Your job

Read the blueprint, extract the relevant section, determine which agent to launch, and present a clear start plan. Do NOT start implementing anything yourself.

## Steps

**1. Read the phase section from the blueprint**

```
docs/architecture-blueprint.md
```

Find the section matching the requested phase. If a sub-phase is given (e.g. `5.1`), find the "Implementation Sub-phases" block under that phase and extract the relevant sub-phase description.

**2. Check if architect is needed**

The architect is needed ONLY if the sub-phase description says "Invoke architect before starting" or if the phase introduces genuinely new infrastructure not yet designed (new external service, new integration pattern).

For standard sub-phases (data layer, CRUD, frontend) — skip the architect.

**3. Determine the agent**

| Sub-phase type | Agent |
|---|---|
| Data layer (migrations, models, enums, factories) | `laravel-backend-dev` |
| Backend (services, controllers, jobs, events) | `laravel-backend-dev` |
| Backend + Frontend combined | `laravel-backend-dev` first, then `laravel-frontend-dev` |
| Frontend only (Blade, Alpine, Tailwind) | `laravel-frontend-dev` |
| New infrastructure / integration | `laravel-architect` first, then `laravel-backend-dev` |

**4. Output this exact structure**

```
## Phase [X.Y] — [name from blueprint]

### Scope
[Bullet list of what will be built — copied from blueprint sub-phase description]

### Agent
[Which agent to launch and why]

### Architect needed?
[Yes/No — reason]

### Key business rules for this sub-phase
[List only the rules from the blueprint relevant to this specific sub-phase, e.g. Rule 23, 27, 28]

### Before starting — verify
[List 1-3 things to confirm are in place before the agent starts, e.g. "Phase 5.1 migrations must be run", "Stripe keys must be in .env"]

### Ready to launch
Invoke [agent name] with: "[brief instruction to pass to the agent]"
```

**5. Wait for user confirmation before invoking any agent.**

Do not auto-launch. The user confirms, then you invoke the agent.
