"""Run the existing service suites against disposable service copies."""
from __future__ import annotations

import subprocess

from stack import SERVICES


def run_suites(stack):
    results = []
    for service in SERVICES:
        root = stack.root / service
        for runner in sorted((root / "tests").glob("*.php")):
            if runner.name == "TestCase.php":
                continue
            if service == "identity" and runner.name not in ("run.php", "validate-migrations.php"):
                continue
            # This suite tests the uninstalled distribution, including absence of private state.
            hidden = []
            if service == "integrations" and runner.name in ("run.php", "unified-platform.php"):
                for relative in ("config/local.env.php", "storage/installed.lock"):
                    path = root / relative
                    backup = path.with_suffix(path.suffix + ".test-backup")
                    path.rename(backup)
                    hidden.append((path, backup))
            try:
                run = subprocess.run(stack.php_command() + [str(runner)], cwd=root,
                                     env=stack.env, capture_output=True, text=True, timeout=120)
            finally:
                for path, backup in hidden:
                    backup.rename(path)
            output = run.stdout + run.stderr
            result = {"suite": service + "/" + runner.name, "passed": run.returncode == 0,
                      "output": output.strip().splitlines()}
            results.append(result)
            print(("PASS " if result["passed"] else "FAIL ") + result["suite"], flush=True)
            if not result["passed"]:
                print(output[-4000:], flush=True)
    return results
