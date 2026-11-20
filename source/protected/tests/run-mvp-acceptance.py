#!/usr/bin/env python3
"""Serial acceptance runner; pre-created synthetic local schemas only, no scheduler setup."""
import argparse
import datetime
import json
import os
from pathlib import Path
import subprocess
import signal
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
TESTS = Path(__file__).resolve().parent


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default='/opt/homebrew/opt/php@7.4/bin/php')
    parser.add_argument('--report', type=Path, default=ROOT / 'protected/runtime/wp09-acceptance.json')
    args = parser.parse_args()
    if os.environ.get('SALEREPORT_TEST_DSN') != 'mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot':
        parser.error('Set SALEREPORT_TEST_DSN exactly to the local synthetic sale_report_mvp_test_pilot schema.')
    common = ['models-smoke.php', 'management-smoke.php', 'access-smoke.php',
              'mvp-schema-smoke.php', 'mvp-policy-smoke.php', 'mvp-import-smoke.php',
              'mvp-import-durability.php', 'mvp-customer-smoke.php', 'mvp-customer-durability.php',
              'mvp-task-smoke.php', 'mvp-task-durability.php', 'mvp-report-smoke.php',
              'mvp-report-durability.php', 'mvp-pilot-acceptance.php', 'mvp-backup-recovery.php']
    jobs = [('pilot', [args.php, str(TESTS / name)]) for name in common]
    jobs += [('pilot_restore', [args.php, str(TESTS / 'mvp-pilot-migration.php')]),
             ('reminder_pilot', [args.php, str(TESTS / 'mvp-reminder-smoke.php')]),
             ('reminder_pilot', [args.php, str(TESTS / 'mvp-reminder-durability.php')]),
             ('pilot', ['node', str(TESTS / 'mvp-reminder-client-smoke.js')]),
             ('performance', [args.php, str(TESTS / 'mvp-performance.php')]),
             ('pilot', [args.php, str(ROOT / 'protected/yiic.php'), 'pilot', 'check', '--actor=1'])]
    report = {'started_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
              'result': 'RUNNING', 'scope': 'local synthetic DBs only',
              'human_uat': 'pending', 'cron_modified': False, 'jobs': []}
    for suffix, command in jobs:
        env = os.environ.copy()
        env['SALEREPORT_TEST_DSN'] = 'mysql:host=127.0.0.1;dbname=sale_report_mvp_test_' + suffix
        started = time.monotonic()
        try:
            process = subprocess.Popen(command, cwd=ROOT.parent, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, start_new_session=True)
            stdout, stderr = process.communicate(timeout=180)
            code = process.returncode
        except subprocess.TimeoutExpired:
            # Stop the entire test process group, including CLI workers, then inspect fixtures.
            os.killpg(process.pid, signal.SIGTERM)
            try:
                process.communicate(timeout=2)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.communicate()
            stdout, stderr, code = '', 'Timeout; test process group stopped. Inspect owned fixtures before retrying.', 124
        job = {'schema': 'sale_report_mvp_test_' + suffix, 'test': Path(command[1]).name,
               'seconds': round(time.monotonic() - started, 3), 'exit_code': code,
               'stdout': stdout.strip(), 'stderr': stderr.strip()}
        report['jobs'].append(job)
        print(('PASS' if code == 0 else 'FAIL') + ': ' + job['test'] + ' (' + str(job['seconds']) + 's)', flush=True)
        if code:
            report['result'] = 'FAIL'
            print(stdout + stderr, file=sys.stderr)
            break
    else:
        report['result'] = 'PASS'
    report['finished_at_utc'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n')
    print('Acceptance evidence: ' + str(args.report.resolve()), flush=True)
    return 0 if report['result'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
