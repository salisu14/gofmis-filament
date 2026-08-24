# AGENTS.md — GOF MIS Engineering Instructions

## 1. Purpose

This file defines the repository-level engineering rules for AI coding agents
working on GOF MIS.

These instructions apply to all coding agents, including Antigravity (ATG),
Jules, Codex, and other automated development tools operating on this
repository.

The purpose of this file is to:

- preserve architectural consistency;
- protect development and production data;
- prevent destructive or speculative changes;
- preserve authorization and zone isolation;
- enforce domain invariants;
- prevent duplicate implementations;
- maintain reliable Git history;
- ensure changes are properly tested and auditable.

Read this file BEFORE modifying the repository.

---

# 2. Instruction Precedence

When instructions appear to conflict, use this order:

1. Explicit instructions in the current user/task request.
2. This `AGENTS.md`.
3. Existing documented GOF MIS architecture and domain rules.
4. Existing tests and established implementation patterns.
5. Agent assumptions.

A task-specific instruction may intentionally override a repository default,
but only when the override is explicit.

If requirements remain ambiguous, conflict with established domain invariants,
or require destructive actions that were not explicitly authorized:

**STOP AND REPORT THE CONFLICT. DO NOT GUESS.**

Never silently reinterpret a business requirement.

---

# 3. Project Technology Baseline

GOF MIS is primarily built using:

- PHP 8.4
- Laravel 13.x
- Filament 5.x
- Livewire
- PostgreSQL
- Pest / PHPUnit
- Spatie Laravel Permission where applicable
- UUID-based domain records where currently configured

Follow the versions actually installed in `composer.lock` and the repository.

Do not downgrade packages or introduce alternative frameworks without explicit
authorization.

Do not write code based on Filament 2/3/4 APIs when the repository uses
Filament 5.

Always inspect existing project patterns before introducing framework-specific
code.

---

# 4. PostgreSQL Is Canonical

PostgreSQL is the canonical database engine.

Do not introduce:

- MySQL-specific SQL;
- SQLite-only workarounds;
- database behavior that bypasses PostgreSQL constraints;
- fake compatibility code that changes production semantics.

Tests and migrations should remain compatible with the project's PostgreSQL
deployment model.

---

# 5. CRITICAL DATABASE SAFETY RULES

The database may contain important development, UAT, staging, or production
data.

## 5.1 Destructive commands are prohibited by default

NEVER run any of the following unless the current task explicitly authorizes
the exact destructive operation:

```bash
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan migrate:refresh
php artisan db:wipe
dropdb
DROP DATABASE
DROP SCHEMA
TRUNCATE ...
