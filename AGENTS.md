# Miranda Production Safety Rules

These rules are mandatory for every person or automated agent working in this repository. If a requested action conflicts with them, stop and explain the risk instead of proceeding.

## Production identity

- The deployment at `37.27.95.226` and the database configured by `/home/shane/Miranda/.env` are production.
- Treat any database whose identity has not been positively verified as production.
- Production data is irreplaceable unless a tested backup proves otherwise.

## Absolute prohibitions

- Never run PHPUnit, Pest, `RefreshDatabase`, `DatabaseMigrations`, seeders, factories, or test suites against production or a shared database.
- Never run `php artisan migrate:fresh`, `db:wipe`, `schema:drop`, destructive SQL, or an equivalent reset against production.
- Never point a test worktree at the production `.env`, production database, or production application bootstrap through a symlink or shared path.
- Never assume `APP_ENV=testing` alone makes a database safe. Verify the effective connection, host, database name, and schema used by the running process.
- Never restore, rebuild, delete, truncate, or overwrite production infrastructure or data without the user's explicit confirmation for that exact action and target.
- Never expose passwords, API keys, OAuth secrets, database URLs, or private keys in terminal output, logs, commits, or chat.

## Required test isolation

Tests may run only when all of the following are true:

1. `APP_ENV` is `testing` in the effective runtime configuration.
2. The effective database is SQLite `:memory:` or a disposable database/schema created solely for that test run.
3. The database host, name, and schema are not the production values.
4. Laravel's cached configuration has not imported production settings.
5. The command is running from the intended checkout, with no production `.env` or bootstrap symlink.

If any check is uncertain or cannot be performed, do not run the tests. Fail closed.

Before a database-writing test, print only non-secret safety facts: environment, driver, database name, schema, and checkout path. Do not print credentials. For PostgreSQL test databases, use a unique throwaway database or schema and verify its name has an obvious test-only prefix before running anything destructive.

## Production migrations and deployments

- Inspect every pending migration before deployment. Production migrations should normally be additive and reversible.
- Confirm the effective application environment and database identity before running a migration.
- Require a recent, verified backup before a migration that drops, renames, rewrites, or truncates data.
- Run only `php artisan migrate --force` for approved production migrations; never substitute `migrate:fresh`.
- Deploy code and migrate production separately so each action can be inspected and stopped independently.
- Do not run experiments or automated tests on the production server. Use a genuinely isolated local or disposable environment.
- Keep workers paused during database recovery or any migration whose compatibility with queued jobs is uncertain. Resume them only after application and database verification.

## Backup requirements

- Enable Hetzner automatic backups for the production server.
- Create scheduled PostgreSQL dumps, encrypt them, and store copies off the server.
- Retain multiple restore points so corruption discovered later does not replace every good backup.
- Test restoration periodically into an isolated environment. A backup is not considered reliable until a restore has succeeded.
- Before risky database work, create and verify an additional on-demand database backup.

## Incident response

If unintended production data loss or corruption is suspected:

1. Stop all database-writing workers and scheduled tasks using a reversible method.
2. Do not restart services, rerun jobs, migrate, seed, or attempt speculative repairs.
3. Preserve the current database and relevant logs before making changes.
4. Inventory local dumps, off-server backups, provider backups, snapshots, replicas, and external systems of record.
5. Report verified facts plainly, including what was changed and what remains unknown.
6. Obtain explicit user approval before any restore or other destructive recovery action.

## Current production reminder

The production database was accidentally reset on 31 August 2026 when an isolated test run inherited the production PostgreSQL connection. The immediate cause was trusting nominal test configuration while application paths/configuration still resolved to production. The rules above specifically prevent that failure mode: tests must prove effective database isolation before they run, and destructive test tooling is forbidden on the production host.
