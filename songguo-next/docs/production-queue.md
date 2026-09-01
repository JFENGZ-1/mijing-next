# Production queue workers

Mijing Next dispatches long-running staff jobs to Laravel queues:

| Job class | Trigger | Domain table |
|---|---|---|
| `App\Jobs\ProcessExportJob` | `POST /api/v1/staff/sites/{site}/exports/members` | `export_jobs` |
| `App\Jobs\ProcessPayrollRecomputeJob` | `POST /api/v1/staff/sites/{site}/payroll/recompute-jobs` | `payroll_recompute_jobs` |

Previously these ran synchronously inside the HTTP request. They now create a `pending` row, dispatch a queue job, and return immediately when `QUEUE_CONNECTION` is not `sync`.

## Local / dev defaults

- `.env.example` sets `QUEUE_CONNECTION=database`.
- PHPUnit forces `QUEUE_CONNECTION=sync` so jobs run inline during tests.
- Demo API (`php artisan serve`) works with `sync` if you do not want a worker process.

## Production setup

1. Choose a driver in `.env`:

```env
QUEUE_CONNECTION=database
# or
QUEUE_CONNECTION=redis
```

2. Ensure queue tables exist (included in default migrations):

```bash
php artisan migrate
```

3. Run a worker process (supervisor/systemd recommended):

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

For Redis, start Redis first and keep the same `queue:work` command.

4. Poll job status from the staff APIs:

- `GET /api/v1/staff/sites/{site}/exports/jobs`
- `GET /api/v1/staff/sites/{site}/payroll/recompute-jobs`

Clients should poll until `status` is `completed` or `failed`.

## Failed jobs

Failed jobs are stored per `config/queue.php` (`failed_jobs` table by default).

Inspect failures:

```bash
php artisan queue:failed
```

Retry one job:

```bash
php artisan queue:retry {id}
```

Retry all:

```bash
php artisan queue:retry all
```

Flush failed table (destructive):

```bash
php artisan queue:flush
```

After repeated failures, check `failed_jobs.exception` and domain row `error_message` (payroll) or audit `export.job.failed` events.

## Monitoring checklist

- Worker process alive (supervisor heartbeat)
- Queue depth not growing unbounded
- `failed_jobs` count near zero
- Export download only when `downloadAvailable=true`
- Disk space for `storage/app/exports/`
