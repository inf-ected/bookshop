# Workflow Rules

## Commits & Pushes

- **Never commit without explicit user confirmation.** Show branch + commit message and wait for YES before running `git commit`.
- **Never push directly to master.** Always create a feature branch and open a PR. Let the user merge via GitHub UI.
- `git push --force` is never allowed.
- An instruction like "commit this" is NOT confirmation — show the plan first, then wait for explicit approval.

## Pull Requests

- Run `laravel-reviewer` before opening any PR. It must give a clean verdict.
- Run `php artisan test --compact` — all tests must pass before opening a PR.
- Never open a PR until both reviewer and tests are green.

## Branches

- Never work directly on master. If you find yourself on master, stop and create a branch first.
- Branch naming: `feat/`, `fix/`, `docs/`, `chore/` prefixes.
