# cms-rel-840 deployment checklist

Everything a site needs to behave like production (cms-rel-701) after it
moves to cms-rel-840. The details and reasons for each item are in
PORTING.md, by cluster. Work through this on a **staging copy of a
production database first**, then repeat it for each production site.

## 0. Docker

cms-rel-840 runs in OpenEMR's release image built from the fork (PHP 8.3+
and MariaDB 10.11+ are required, so the existing server can't run it as
is). Everything for that is in `contrib/cms/docker/`; its README has the
image build, moving the 7.0.1 install in (database, site directories, a
stock `sites/default` where `default` isn't a real site), and the vendor
hooks. The image upgrades each site's database straight from 7.0.1 on
first start.

Two prelaunch hooks, run on every start, automate most of sections 2–6
below:
- `10-cms-site-files` copies the site files (sections 4–5);
- `20-cms-site-settings` applies `config/sql/all-sites.sql` (module,
  claim balancing, drug units) and each `config/sql/<site>.sql` (section 3),
  once per file per site.

Fill in `config/` from the sections below, then use them as the checklist
to verify each site.

Each server pins the build it runs with `CMS_IMAGE` in `.env` (an image
tagged with its cms-rel-840 commit). Later fixes (e.g. upstream cherry-picks)
follow the kit README's "Updating": build, back up the databases, change
`CMS_IMAGE`, `docker compose up -d`; going back is the same with the old
tag.

- [ ] **TLS certificate.** One hostname per server: set `CMS_DOMAIN` and
      `CMS_LETSENCRYPT_EMAIL` in `.env`; the container gets and renews the
      certificate (kit README, "TLS certificate"). DNS must point at the
      server and port 80 must be open. Port 80 only answers the Let's
      Encrypt check and redirects the rest to HTTPS (`06-cms-https-redirect`),
      so it stays open; no more enabling the port-80 site by hand for
      renewals. After cutover, disable the host certbot's renewal. Until then, make sure it still renews: one
      server's certificate had 22 days left on 2026-10-04 (certbot renews
      at 30), so check `sudo certbot renew --dry-run`.

## 1. Before upgrading the database

- [ ] **`x12_partners.x12_submitter_id` values.** cms-rel-701 has the
      column as `tinyint(1)` (upstream #6456 without its fix #6459), which
      caps the submitter's user ID at 127. `config/sql/all-sites.sql`
      widens it to `smallint(6)` at first start. Check for clipped IDs:
      ```sql
      SELECT p.id, p.name, p.x12_submitter_id, u.fname, u.lname
        FROM x12_partners p LEFT JOIN users u ON u.id = p.x12_submitter_id;
      ```
      A 127 may be clipped: pick the submitter again after the upgrade.
- [ ] **Each site's database user reaches only its own database.** With
      all sites in one MariaDB, a site user with wider grants (or a login
      shared between sites) exposes other practices' data. For each site's
      `$login` from its `sqlconf.php` (production's users may be
      `@'localhost'`; list them first):
      ```sql
      SELECT user, host FROM mysql.user ORDER BY user;
      SHOW GRANTS FOR '<login>'@'<host>';
      ```
      Only `USAGE` and the one site's database (`` `<dbase>`.* ``) should
      appear: no `*.*` privileges and no other site's database. Give each
      site its own login when creating the users.
- [ ] **No database root password in the running container.** Pass it only
      for the first start of a stock `default`
      (`CMS_SETUP_DB_ROOT_PASS=… docker compose up -d`), then recreate the
      container without it (kit README, step 5).
- [ ] **Database collation.** Create the new database with
      `CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci` before loading the
      dump. MariaDB 11.5+ otherwise defaults to `utf8mb4_uca1400_ai_ci`,
      the upgrade's new tables get it, and joins with the old tables fail
      (seen in the S2 dry run on `contact_relation`).
- [ ] Back up the database and the site directory (`sites/<site>/`). The
      container then uses the live `sites/` folder in place
      (`CMS_SITES_DIR`); the copy is only the rollback fallback.
- [ ] **One server, one cutover.** All of a server's sites share one stack
      and one `sites/` folder, so they move together: disable the host's
      Apache and cron, dump every site's database at that moment, load
      them, start. Downtime is about the sum of the sites' upgrade times
      plus the dump and load.
- [ ] Run the normal OpenEMR upgrade (7.0.1 → 8.4), then log in as an admin.

## 2. Every site

- [ ] **Enable oe-module-cmsvt** (Administration → Modules → Manage Modules:
      register, install, enable). Installing runs its `table.sql`, which
      creates `patient_statements` if it doesn't exist; it's a no-op where it
      does.
- [ ] **Force claim balancing: OFF** (Administration → Globals → Billing,
      `force_claim_balancing`). The upstream default is ON; production never
      balanced claims (cluster 9).
- [ ] **PHP limits.** Statement runs now allow 5 minutes
      (`set_time_limit(300)`), but `memory_limit` still comes from php.ini.
      Large statement or PDF runs need enough memory, and the web server or
      proxy timeout must not be shorter than 5 minutes.
- [ ] The CMS menu changes (Payment, Posting Payments, EDI History and
      Electronic Reports in their own tabs, Reports → Visits → Press Ganey
      Export) come from the module automatically; nothing to configure.

## 3. Per-site settings (Administration → Globals → CMS Vermont)

All default to off/0/blank, which behaves like stock OpenEMR. Set only
what the site used in production.

| Site | Setting | Value |
|---|---|---|
| S1 | Eligibility (270) Provider Override (`cmsvt_elig_provider_id`) | user ID of the provider hardcoded in production commit f5de47125c (`src/Billing/EDI270.php`); confirm that user's NPI matches |
| S1 | Eligibility (270) Receiver Name Override (`cmsvt_elig_receiver_name`) | the receiver name from the same commit |
| S1 | Billing Manager Default Date-of-Service Months (`cmsvt_billing_manager_dos_months`) | 2 |
| S4 | Hide Export to Collections (`cmsvt_collections_hide_agency_export`) | on |
| podiatry sites (primary business entity taxonomy 213E00000X) | Label Onset Date as Date Last Seen (`cmsvt_encounter_date_last_seen`) | on |
| podiatry site billing as NPI <podiatry-npi> | Claims: Routine Foot Care Billing NPIs (`cmsvt_claim_routine_foot_care_npis`) | <podiatry-npi> |
| S6 | Match Lab Results by MRN Only (`cmsvt_hl7_match_patient_by_mrn`) | on |
| S6 | Electronic Reports Default to Reviewed (`cmsvt_lab_list_default_reviewed`) | on |
| S7 | Default Lab Results Processed Per Lab (`cmsvt_lab_results_per_lab`) | 50 |
| S5 | Claims: Payers Without NDCs (`cmsvt_claim_ndc_skip_payer_ids`) | 87726, 39026, TREST, PAMCD (not 25169) |
| S5 | Claims: Bill Under Provider (`cmsvt_claim_rendering_provider_id`) | 6 |
| S3 | Claims: Closed Service Facility NPIs (`cmsvt_claim_closed_facility_npis`) | the closed facility's NPI |
| Press Ganey sites | Press Ganey Client ID / Survey Designator (`pg_client_id`, `pg_survey_designator`) | carried over: same keys as 7.0.1; check they show their old values |

## 4. Statements (every site)

Production printed every site's statements from `library/statement.inc.php`
(a cms-rel-701 change); the site copies were never loaded. cms-rel-840's PDF
Custom layout reproduces that output byte for byte (cluster 10).

- [ ] **`sites/<site>/statement.inc.php` = cms-rel-840's**
      `sites/default/statement.inc.php`, the same on every site. rel-840
      loads the site copy, and upgrades don't replace it; the old copy has
      no PDF Custom branch. The Docker kit's `10-cms-site-files` hook copies
      the image's file into every site at each start.
- [ ] **Statement Appearance = PDF Custom** (Administration → Globals →
      Billing, `statement_appearance` = 2; set by `all-sites.sql`).
      Production's value was probably 0 (any value but 1 printed the CMS
      layout there); here 0 gives the stock plain-text layout.
- [ ] **Statement Logo** = the letterhead **PNG** (612×792 pt) in
      `sites/<site>/images/`. Production used the same global and file, so
      a migrated site normally has it already; check it isn't blank or the
      default `practice_logo.gif`, which can't be drawn.

## 5. Records-review user (sites that have one)

- [ ] Put the user in an ACL group **without `patients/appt`** and with
      **`patients/demo` view only** (no write). Assign the `chart_review`
      menu.
- [ ] PatientFilter: move the reviewer's pids from `blacklist` to
      **`whitelist`** in
      `interface/modules/zend_modules/module/PatientFilter/config/blacklist.php`
      (e.g. the output of `contrib/util/chart_review_pids.php`). With
      `blacklist` they would be hidden instead of allowed.
- [ ] Known gap until 8.5.0: the Immunizations card still shows Edit to that
      user (upstream fix 78bd686104 arrives with 8.5.0).

## 6. Drug codes: units, NDC and prices

cms-rel-840 includes upstream #14330 (cherry-picked):
- **Inventory → Drugs** has Billing Units, NDC Unit and NDC Quantity. When a
  HCPCS code is picked on the fee sheet, units and NDC come from the active
  drug related to it (a drug with stock first), else from `codes.units`
  and the last billed NDC.
- **A price is now per unit:** a newly picked code's fee is price × units.
  Production treated the price as the whole line (fee ÷ units was shown as
  the unit price).

- [ ] **Convert prices for codes billed with more than one unit, before
      go-live.** Otherwise a newly picked J2777 (60 units) charges 60 × the
      old price. First list them; check each price is a whole-dose amount:
      ```sql
      SELECT c.code, c.modifier, c.units, p.pr_level, p.pr_price,
             ROUND(p.pr_price / c.units, 2) AS per_unit
      FROM codes c
      JOIN prices p ON p.pr_id = c.id AND p.pr_selector = ''
      WHERE c.code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS')
        AND c.units > 1
      ORDER BY c.code, p.pr_level;
      ```
      Then convert once (only the codes that are whole-dose prices):
      ```sql
      UPDATE prices p JOIN codes c ON c.id = p.pr_id
      SET p.pr_price = ROUND(p.pr_price / c.units, 2)
      WHERE p.pr_selector = ''
        AND c.code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS')
        AND c.code IN ('C9257', 'Q5124', 'J0178', 'J0177', 'J2777');
      ```
      Running it twice divides twice; back up `prices` first. Drugs whose
      units come from Inventory (Billing Units) need the same per-unit price.
- [ ] **Default units:** `config/sql/all-sites.sql` sets `codes.units` for
      C9257 = 5, Q5124 = 5, J0178 = 2, J0177 = 8, J2777 = 60 (production
      forced these). Administration → Codes still can't edit Units; for
      drugs, prefer Inventory's Billing Units, which wins over `codes.units`.
- [ ] **Depo-Medrol (optional):**
      - deactivate J1020/J1030/J1040;
      - price J1010 at $1.00 per unit;
      - add an Inventory drug related to `HCPCS:J1010`, with its NDC, Billing
        Units (e.g. 40 for 40 mg) and NDC Unit/Quantity (e.g. ML 1 for the
        40 mg/mL vial).

## 7. Background services (cron)

Production runs background services from root's crontab, per site:
`php library/ajax/execute_background_services.php <site>` (S6 every 15
minutes; S2, S4, S5, S7 and S8 six times a day). In cms-rel-840:
- OpenEMR's CLI refuses to run as root (`RootCliGuard`), so those lines
  would fail even outside Docker;
- the container runs no cron for OpenEMR (only certificate renewal);
- upstream rel-840 ran every non-default site's services against the
  `default` database (run-all-due subprocesses had no `--site`). Fixed in
  cms-rel-840 (325cabacf7, being sent upstream); without it, only the
  named-service form `execute_background_services.php <site> <service>`
  runs against the right site.

- [ ] **New services after the upgrade.** The 7.0.1 → 7.0.2 step adds
      `Email_Service` **active** where a database doesn't have it (upstream
      default; its queue, `email_queue`, starts empty). Decide per site
      whether it should run, and include it in that site's schedule if so.
- [ ] **List each site's active services** in production:
      ```sql
      SELECT name, active, execute_interval, next_run FROM background_services WHERE active = 1;
      ```
- [ ] **Replace the crontab** with host cron calling into the container as
      `apache`, one line per site, keeping today's schedules:
      ```cron
      */15 * * * * cd /opt/cms/kit && docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console background:services run --site=S6 >> /var/log/openemr-bg.log 2>&1
      ```
      `background:services run` runs every service that's due; `list`
      shows them, `unlock` frees one left running, and `crontab` prints
      per-service lines if a service needs its own schedule. Remove the old
      root lines when the site moves.

## 8. API callers

- [ ] Lab Observations can be searched by the lab's visit number:
      `GET /fhir/Observation?external_id=<visit no.>`.
- [ ] Lab Observation `effectiveDateTime` is the specimen collection time
      (OBR-7), falling back to the report date. For results-only orders it's
      the same value production sent; callers reading `effectiveDateTime`
      need no change. It's local time with offset; a caller that converts to
      UTC moves late-evening results to the next day.

## 9. Smoke tests (staging)

Test data or a de-identified copy only.

- [ ] **Eligibility (site S1):** a real-time 270 sends the override provider
      and receiver name.
- [ ] **Billing Manager:** default date range (site S1: last 2 months),
      re-open and MBO options; Collections report shows/hides the agency
      export (site S4).
- [ ] **Fee sheet:** review shows today's prices; a drug code arrives with its
      default units; fee = price × units.
- [ ] **Encounter form / report:** "Date Last Seen" label on podiatry sites.
- [ ] **Reports:** Appointments report (DOB, phone, patient due, primary
      insurer, reminder CSV); Press Ganey export; records-review user sees
      only allowed patients.
- [ ] **ERA/EOB posting:** post a test 835 (claims don't balance; MOA silent;
      N4/modifier 51 handled; posting moves to the next unposted level); the
      invoice screen shows Ins2/Ins3 only when that payer exists and Save
      Current works.
- [ ] **Statements:** Print, Download, PDF and Email runs:
      - the PDF Custom layout prints on the letterhead;
      - Without Update writes only the file (no emails, no documents);
      - Email Selected asks for a count, and refuses when Without Update is
        checked;
      - a second click asks before starting another run;
      - the Due Pt list renders fully and shows the PRINT badge for patients
        with 2+ unpaid emailed statements.
- [ ] **Labs:** import a results-only ORU (site S6: matched by MRN; visit
      number stored; no encounter or provider notice); Electronic Reports
      defaults (S6 reviewed, S7: 50 per lab); signing a result returns
      to the list; the patient link opens the latest encounter.
- [ ] **Claims (837P):** generate primary and secondary claims and compare
      with what cms-rel-701 produces for the same encounters
      (`contrib/cms/docker/tools/x12-diff.py`):
      - Vermont Medicaid carrier codes in 2330B/SVD;
      - secondary claims carry only patient responsibility (no CO/OA), as
        production;
      - podiatry DTP*304 and supervisor;
      - pay-to address; Medicare IDs without dashes;
      - site S5: NDC skip and billing provider.
- [ ] **SFTP:** an upload to BCBS VT's MOVEit server goes out as
      `007111NN.x12`.
- [ ] **Console:** `php bin/console cmsvt:fees-increase --site=<site>
      --percent=1 --dry-run` lists the new prices without changing anything;
      `cmsvt:x12-sftp-password` is available.

## 10. Cutover runbook (one server, all its sites)

All of a server's sites move in one go: they share one stack and the live
`sites/` folder. Times are an example for a 22:30 nightly dump. Paths:
`/var/www/html/openemr/sites` (live sites), `/opt/cms/kit` (the kit; adjust),
`<dumps>` (where the dump job writes).

### Days before

- [ ] All per-site dry runs done; note each site's upgrade time (the sum,
      plus dump and load time, is the downtime estimate).
- [ ] Image built from the final cms-rel-840 commit and tagged with it
      (kit README section 1); `docker images` on the server shows it.
- [ ] Kit in `/opt/cms/kit`, with:
  - [ ] `.env`: `CMS_IMAGE` (the tag), `CMS_SITES_DIR=/var/www/html/openemr/sites`,
        strong `CMS_DB_ROOT_PASS` / `CMS_DEFAULT_DB_PASS` /
        `CMS_DEFAULT_ADMIN_PASS`, `CMS_DOMAIN`, `CMS_LETSENCRYPT_EMAIL`,
        ports 80/443; `chmod 600 .env`.
  - [ ] `config/sql/<site>.sql` for every site with its own settings (from
        the private `site-config/`).
  - [ ] `config/sites/<site>/` only for exceptions (letterhead, records
        review `chart_review.json`, PatientFilter config under `config/code/`).
- [ ] `x12_submitter_id` values checked on every site (section 1).
- [ ] Each site's grants and user known (`sqlconf.php`); no shared logins.
- [ ] Docker disk: room for every site's database once loaded
      (`df -h /var/lib/docker`).
- [ ] The container's apache can use the live folder (harmless to the host
      Apache meanwhile; takes a while on big trees):
      ```sh
      sudo setfacl -R -m u:1000:rwX -m d:u:1000:rwX /var/www/html/openemr/sites
      ```
- [ ] Host certbot renewing until the cutover (`sudo certbot renew --dry-run`).
- [ ] Production lab providers keep their real settings: the dry-run
      switch-off (protocol `FS`, inactive) is **never** run on production.

### Cutover night

1. **~22:20 Stop everything that writes, so a reboot can't restart it:**
   ```sh
   sudo systemctl disable --now apache2
   sudo crontab -e      # comment out only the execute_background_services.php lines
   ```
   Leave the dump job: it's the backup for step 2.
2. **22:30 Dump** (the nightly job, or run it by hand now), then check every
   file finished:
   ```sh
   for f in <dumps>/*.sql.gz; do gzip -t "$f" && zcat "$f" | tail -1 | grep -q 'Dump completed' && echo "ok   $f" || echo "BAD  $f"; done
   ```
3. **Fallback copy of the sites folder** (rollback only, not used to run):
   ```sh
   sudo ./sync-sites.sh <every site>
   ```
4. **Load every site's dump** into the container's MariaDB. Each site gets
   its database and user from its own `sqlconf.php`:
   ```sh
   cd /opt/cms/kit
   RP=$(grep '^CMS_DB_ROOT_PASS=' .env | cut -d= -f2-)
   docker compose up -d --wait mysql
   S=/var/www/html/openemr/sites
   conf() { sudo sed -nE "s/^\\\$$2[[:space:]]*=[[:space:]]*['\"](.*)['\"];.*/\1/p" "$S/$1/sqlconf.php"; }
   for site in <every site>; do
     DB=$(conf "$site" dbase); U=$(conf "$site" login); P=$(conf "$site" pass)
     echo "== $site ($DB)"
     docker compose exec -T mysql mariadb -uroot -p"$RP" -e "
       CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
       CREATE USER '$U'@'%' IDENTIFIED BY '$P';
       GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$U'@'%';"
     zcat "<dumps>/$DB.sql.gz" | docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB" || echo "LOAD FAILED: $site"
   done
   ```
   (Adjust the dump file name to the job's naming.)
5. **Point the sites at the container's database:**
   ```sh
   for site in <every site>; do
     sudo cp -p "$S/$site/sqlconf.php" "$S/$site/sqlconf.php.pre-docker"
     sudo sed -i -E "s/^(\\\$host[[:space:]]*=[[:space:]]*)['\"][^'\"]*['\"];/\\1'mysql';/" "$S/$site/sqlconf.php"
     sudo grep -H '^\$host' "$S/$site/sqlconf.php"
   done
   ```
6. **Stock `default`** (only where `default` isn't a real site):
   ```sh
   docker run --rm --entrypoint tar "$(grep '^CMS_IMAGE=' .env | cut -d= -f2-)" \
     -C /var/www/localhost/htdocs/openemr/sites -c default | sudo tar -C "$S" -x
   sudo setfacl -R -m u:1000:rwX -m d:u:1000:rwX "$S/default"
   ```
7. **Start and watch** (root password only for the stock `default`'s setup):
   ```sh
   CMS_SETUP_DB_ROOT_PASS="$RP" docker compose up -d
   docker compose logs -f openemr
   ```
   Each site: `Schema upgrade detected …` then `Completed …` (one at a time,
   alphabetical); `Setup Complete!` for `default`; the `cms …` hooks;
   Let's Encrypt for `CMS_DOMAIN`; `Starting Apache!`. If a site's upgrade
   fails, `docker compose stop openemr`, read the error, restore that
   site's dump if needed, start again.
8. **Drop the root password:** after `Starting Apache!`, `docker compose up -d`;
   `docker compose exec openemr printenv MYSQL_ROOT_PASS` shows `unset`.
9. **Check every site:** log in (`?site=<site>`); globals from sections 2–4;
   a statement PDF; Billing Manager; Electronic Reports; `https://` with the
   real certificate and port 80 redirecting.
10. **Background services:** add the host crontab lines (section 7), one per
    site, as apache, keeping each site's schedule; disable the host
    certbot's renewal.

### Rollback (if the night goes wrong)

The host MariaDB still has the pre-cutover data (stopped, not removed):
```sh
cd /opt/cms/kit && docker compose down          # keeps volumes
# put every site's sqlconf.php back as it was (saved in step 5):
for site in <every site>; do sudo cp -p "$S/$site/sqlconf.php.pre-docker" "$S/$site/sqlconf.php"; done
sudo systemctl enable --now apache2             # and restore the crontab lines
```
Anything the container changed in `sites/` (statement.inc.php, cms-applied/,
new documents) is in the fallback copy from step 3 if needed. Keep the host
MariaDB, the dumps and the copy for a few days after a good cutover.
