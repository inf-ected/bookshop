#!/usr/bin/env python3
"""
PreToolUse hook for Bash commands.
Blocks dangerous or confirmation-required operations.
Exit code 2 = block the tool call and show the message to the user.
"""
import sys
import json
import subprocess

data = json.load(sys.stdin)
cmd = data.get('command', '')

# ── Block force push ──────────────────────────────────────────────────────────
if 'git push' in cmd and ('--force' in cmd or ' -f ' in cmd or cmd.strip().endswith('-f')):
    print('BLOCKED: Force push is never allowed.')
    print('If you need to update a remote branch, use a different approach.')
    sys.exit(2)

# ── Block rm -rf ──────────────────────────────────────────────────────────────
if 'rm -rf' in cmd or 'rm -fr' in cmd:
    print('BLOCKED: rm -rf requires explicit user confirmation.')
    print('Describe exactly what will be deleted and wait for approval.')
    sys.exit(2)

# ── Block commit / push / PR — require explicit YES ──────────────────────────
if 'git commit' in cmd or 'git push' in cmd or 'gh pr create' in cmd:
    lines = [
        'BLOCKED: Requires explicit user confirmation.',
        'Show the plan (branch, commit message, what changes) and wait for YES.',
    ]
    try:
        r = subprocess.run(
            ['git', 'branch', '--show-current'],
            capture_output=True, text=True
        )
        branch = r.stdout.strip()
        if branch == 'master':
            lines.append('WARNING: You are currently on the master branch!')
    except Exception:
        pass
    print('\n'.join(lines))
    sys.exit(2)
