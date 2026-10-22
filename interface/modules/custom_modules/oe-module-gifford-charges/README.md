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

## Payers

Each Gifford payer mnemonic (`ins1_mnemonic` ...) is billed to one of the site's
insurance companies; `gifford_payer_map` holds which. The companies are created from
the payer crosswalk CSV (one row per mnemonic; `SP`, self pay, is not a payer):

| Column | Use |
|---|---|
| `gifford_mnemonic`, `gifford_name` | the map key, and the company's name |
| `electronic` | `Y`: payer ID, X12 partner and address are set; `N`: paper, name only |
| `payer_id_to_use` | the company's payer ID (electronic only) |
| `x12_partner` | starts with the partner id, e.g. `111462 Optum iEDI` (electronic only) |
| `ins_type_code` | the insurance type (2 Medicare B, 3 Medicaid, 6 BCBS, 17 commercial, ...) |
| `street`, `city`, `state`, `zip` | the address (electronic only; zip 5 or 9 digits) |

A header is matched up to its first ` (`, so columns may carry a description; other
columns are ignored. Every bad row is listed and nothing is written. The X12 partners
and insurance types the file names must exist on the site.

    docker compose cp payer-crosswalk.csv openemr:/tmp/payer-crosswalk.csv
    docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console gifford:payer-import --site=2400 --file=/tmp/payer-crosswalk.csv --dry-run

then again without `--dry-run`. A mnemonic already mapped is left alone, so billers'
changes to a company (a paper payer's address and payer ID, once its first claim comes
up) are kept, and running it again only adds new mnemonics. A mapped company that was
deleted is created again.
