"""Repeat only lightweight checks; no application boot/build, UI tests or external DB."""
from pathlib import Path
import json
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(__file__).resolve().parent
php = shutil.which('php')
dart_launcher = shutil.which('dart')
if not php or not dart_launcher:
    raise SystemExit('PHP and Dart must already be installed.')
dart = Path(dart_launcher).parent / 'cache/dart-sdk/bin/dart.exe'
if not dart.exists():
    dart = Path(dart_launcher)
results = []

def run(label, command, directory=ROOT, timeout=60):
    result = subprocess.run([str(x) for x in command], cwd=directory,
                            capture_output=True, text=True, encoding='utf-8', errors='replace', timeout=timeout)
    results.append({'check': label, 'exit_code': result.returncode,
                    'output': result.stdout + result.stderr})
    print(label, 'PASS' if result.returncode == 0 else 'FAIL')

entries = json.loads((OUT / 'changed_files.json').read_text())
for relative in entries:
    if relative.endswith('.php'):
        run('php -l ' + relative, [php, '-l', ROOT / relative])
run('PHP unit contracts', [php, 'vendor/phpunit/phpunit/phpunit', '--no-coverage', '--do-not-cache-result',
    'tests/Unit/SaudiTimeContractTest.php', 'tests/Unit/SlotTimezoneTest.php',
    'tests/Unit/BranchWorkspaceTest.php', 'tests/Unit/OperationalPresentationTest.php'], ROOT / 'Admin Panel')
dart_files = [ROOT / relative for relative in entries if relative.endswith('.dart')]
run('Dart formatting', [dart, 'format', '--output=none', '--set-exit-if-changed', *dart_files])
for app in ['User app and web', 'Provider app', 'Service man app']:
    # Bypass pub build hooks; execute the pure Dart VM check directly.
    run(app + ' time contract', [dart, '--disable-dart-dev', '--packages=.dart_tool/package_config.json',
        'test/unit/saudi_time_contract_check.dart'], ROOT / app)
node = shutil.which('node')
if node:
    run('Web date formatter contract', [node, OUT / 'check_web_time.cjs'])
    run('Branch JavaScript syntax', [node, '--check', ROOT / 'Admin Panel/public/assets/common/js/nirab-branch.js'])
(OUT / 'light_check_run.json').write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding='utf-8')
raise SystemExit(1 if any(item['exit_code'] for item in results) else 0)
