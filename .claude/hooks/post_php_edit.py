#!/usr/bin/env python3
"""
PostToolUse hook for Edit and Write tools.
Reminds to run Pint and PHPStan after modifying PHP files.
PostToolUse hooks are informational only — exit code does not block anything.
"""
import sys
import json

try:
    data = json.load(sys.stdin)
    fp = data.get('file_path', '')
    if fp.endswith('.php'):
        print('PHP file modified. Before finalizing:')
        print('  1. ./vendor/bin/pint --dirty --format agent   (code style)')
        print('  2. composer analyse                            (PHPStan level 5)')
except Exception:
    pass
