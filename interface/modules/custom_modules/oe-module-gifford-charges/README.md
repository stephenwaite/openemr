# Gifford Charge Import

Imports Gifford's radiology charge files (`RAD_*.xlsx`, one a day) into site 2400.

## What it does now (staging)

An hourly background service, `Gifford_Charge_Import`, reads each new `.xlsx` or
`.csv` file in the charge file directory and:

- skips a file whose bytes it has already seen, moving it to `processed/`;
- saves a file it cannot read (wrong type, missing columns) as a failed batch in
  `gifford_charge_batch` and moves it to `failed/`;
- otherwise saves the file as a batch and every row as a line in
  `gifford_charge_line` (`staged`, or `held` with its problems), then moves it to
  `processed/`.

Moved files are renamed `<batch id>-<name>`. A file modified in the last two minutes
is left for the next run, so one still arriving is not read half-written. Nothing is
posted to patients or billing yet.

## Setting it up on a site

1. Modules > Manage Modules: register, install and enable **Gifford Charge Import**.
   Installing creates the two tables and the background service; enabling switches
   the service on.
2. Admin > Config > Gifford Charge Import > **Charge File Directory**: the directory
   as the server (the container) sees it. The import must be able to read it and
   create `processed/` and `failed/` inside it.
3. The site's background services cron runs the service (contrib/cms/docker
   README, "Background services"). To run it by hand:

       docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console background:services run --name=Gifford_Charge_Import --force --site=2400

A run started by name prints a warning for a file it could not read. In the cron's
"run all due" mode services run in a child process whose output is not kept, so look
for failed files in `gifford_charge_batch` (`status = 'failed'`, `failure`).
