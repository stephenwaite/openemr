# Dry run: upgrading one site (1100) in a sandbox

A practice migration of one production site into the cms-rel-840 Docker
stack, on a machine where production can't be affected. It uses a copy of
the site folder and a database dump taken at the same time, and can be
reset and repeated. The server this site lives on has no real `default`
site, so the container gets a stock one.

The backups contain patient data. Keep them, and the working copy below,
outside any git checkout, on storage that's encrypted or otherwise locked
down. Delete them when the dry run is finished.

## What you need

- The site backup (`sites/1100/`) and its database dump (`.sql.gz`) from the
  same moment, both checked complete:
  - `gzip -t` passes on the dump;
  - its last line is `-- Dump completed on …`.
- A machine or sandbox with Docker, network access to GitHub, and enough
  Docker disk: about 5 GB for the image, plus the size of the dump and of
  `sites/1100`.

## 1. Workspace

```sh
mkdir -p ~/cms-dryrun/{backup,sites,db}
cd ~/cms-dryrun
git clone --branch cms-rel-840 https://github.com/stephenwaite/openemr.git src
cp -r src/contrib/cms/docker kit
```

Copy the backups into `~/cms-dryrun/backup/` (for example
`backup/sites/1100/` and `backup/1100.sql.gz`). Keep `backup/` untouched;
the stack works on copies, so you can reset from it.

## 2. Build the image

```sh
cd ~/cms-dryrun/src
docker build \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
```

## 3. Working copy of the sites folder

```sh
cd ~/cms-dryrun
# sudo: the backup keeps production's owners, so your account can't read all of it
sudo rsync -aHAX --numeric-ids --delete backup/sites/1100/ sites/1100/

# Stock default site from the image: configured on first start as a new, empty site
docker run --rm --entrypoint tar cmsvt/openemr:cms-rel-840 \
  -C /var/www/localhost/htdocs/openemr/sites -c default | tar -C sites -x

# The container's apache is uid 1000
sudo setfacl -R -m u:1000:rwX -m d:u:1000:rwX sites
```

Edit `sites/1100/sqlconf.php` (with `sudo`, e.g. `sudo nano`):
- set `$host = 'mysql';`;
- note `$dbase`, `$login` and `$pass` for step 5.

## 4. Configure the stack (`kit/`)

In `kit/docker-compose.yml`:
- **Sites folder:** replace the `sitevolume` line under `openemr:` with a
  bind mount, and remove `sitevolume: {}` at the bottom:
  ```yaml
      - ../sites:/var/www/localhost/htdocs/openemr/sites
  ```
- **Passwords:** replace every `change-me`. `MYSQL_ROOT_PASSWORD` (mysql)
  and `MYSQL_ROOT_PASS` (openemr) must match.
- **New default site:** `MYSQL_DATABASE` and `MYSQL_USER` must differ from
  site 1100's `$dbase` and `$login`. They're `openemr_default` by default.
- **Ports:** if 80/443 are taken, change them, e.g. `8080:80` and
  `8443:443`. In a Docker Sandbox, also publish the port on the host
  (`sbx ports <sandbox> --publish 8443:8443`).

In `kit/config/sql/`, keep `all-sites.sql`, delete the other sites' files,
and add a `1100.sql` only for CMS settings 1100 had in production.
In `kit/config/sites/`, add `1100/statement.inc.php`, copied from
`src/sites/default/statement.inc.php` (not 1100's own copy, which production
never used). The letterhead is normally already in place: production read
the same Statement Logo global and `sites/1100/images/` file. Check after
step 5 that `statement_logo` names a PNG in `sites/1100/images/`; only if
not, add the PNG under `kit/config/sites/1100/images/` and set
`statement_logo` in `1100.sql`.

## 5. Load the database

```sh
cd ~/cms-dryrun/kit
docker compose up -d --wait mysql   # returns once the database accepts connections
docker compose exec mysql mariadb -uroot -p -e "
  CREATE DATABASE \`<dbase>\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  CREATE USER '<login>'@'%' IDENTIFIED BY '<pass>';
  GRANT ALL PRIVILEGES ON \`<dbase>\`.* TO '<login>'@'%';"
zcat ../backup/1100.sql.gz | docker compose exec -T mysql mariadb -uroot -p<root password> <dbase>
```

State the collation: the upgrade creates new tables in the database's
default, and MariaDB 11.5+ otherwise defaults to `utf8mb4_uca1400_ai_ci`.
Joins between those and the dump's `utf8mb4_general_ci` tables then fail
("SQL Statement failed on preparation", e.g. on the patient's contacts).
The dump's own mix (1100: `utf8mb3_general_ci`, `utf8mb4_general_ci` and a
few `latin1`) is fine and stays as it is.

Before the first start, check `x12_submitter_id` (DEPLOYMENT.md section 1):

```sh
docker compose exec mysql mariadb -uroot -p <dbase> \
  -e "SHOW COLUMNS FROM x12_partners LIKE 'x12_submitter_id'"
# if it's tinyint(1):
docker compose exec mysql mariadb -uroot -p <dbase> \
  -e "ALTER TABLE x12_partners MODIFY x12_submitter_id smallint(6) DEFAULT NULL"
```

Then switch off everything that sends messages or files. OpenEMR runs its
background services from the browser of whoever is logged in, so without
this, logging in would send MedEx reminders, queued emails (including
statements), lab orders and SFTP claim uploads. Run it again every time you
reload the dump:

```sh
docker compose exec -T mysql mariadb -uroot -p<root password> <dbase> <<'SQL'
UPDATE background_services SET active = 0;
UPDATE procedure_providers SET active = 0;
-- every module but CMS Vermont, which is what's being tested
UPDATE modules SET mod_active = 0 WHERE mod_directory <> 'oe-module-cmsvt';
UPDATE globals SET gl_value = '0'
 WHERE gl_name IN ('medex_enable', 'auto_sftp_claims_to_x12_partner', 'phimail_enable');
-- can't resolve, so anything that tries to send email fails instead
UPDATE globals SET gl_value = 'smtp.invalid' WHERE gl_name = 'SMTP_HOST';
-- optional: log in without the users' authenticator apps
TRUNCATE login_mfa_registrations;
SQL
```

To log in as yourself, set a known password hash on your own account in
`users_secure` the same way.

## 6. Start and watch

```sh
docker compose up -d
docker compose logs -f openemr
```

Expected, in order:
1. `Schema upgrade detected for 1100: database is at revision … (7.0.1)`,
   then `Completed: schema upgrade for 1100 from 7.0.1`. Note how long it
   takes; that's the downtime estimate for the real cutover.
2. `Running quick setup!` and `Setup Complete!` for the new `default`.
3. The prelaunch hooks: `cms prelaunch: …` (files) and `cms settings: …`
   (SQL applied to 1100 and default).
4. `Starting Apache!`.

If it stops, the last lines say why (a failed upgrade statement, a hook
error). Fix it, reset (step 8) and start again.

## 7. Check site 1100

Log in at `https://localhost:<port>/interface/login/login.php?site=1100`
with a real 1100 account, and work through DEPLOYMENT.md's smoke tests
that apply to this site. At least:
- **Documents:** patients' documents open (old file paths are rebuilt
  under the new site folder automatically).
- **Fee sheet:** pick a drug code; check its units and price. Run the
  per-unit price review query from DEPLOYMENT.md section 6 against
  `<dbase>`.
- **Claims:** generate a few 837P claims, primary and secondary, and
  compare them with production's 837 for the same encounters:
  ```sh
  python3 kit/tools/x12-diff.py <production>.x12 <dry-run>.x12
  ```
  It matches claims by CLM01, ignores control numbers and creation dates,
  and prints each claim's differing segments. Its output contains claim
  data; keep it with the backups.
- **Statements:** a PDF download with Without Update checked writes only
  the file. Compare its text with a production statement for the same
  patient: wording and columns should be identical.
- **Reports, ERA posting and labs**, as far as test data allows.

Don't send anything real from the dry run: no claims to clearinghouses, no
statements by email, no portal notices. Step 5's switch-off covers the
automatic senders; the manual ones (Email Selected, claim uploads from the
Billing Manager) are still up to you.

## 8. Next site (e.g. 1400)

Each run starts clean: the previous site's stack, database and working
copies are removed, the image and kit come from the current cms-rel-840, and
steps 3–7 are repeated for the new site. The example moves from 1100 to
1400.

**Tear down the previous run:**

```sh
cd ~/cms-dryrun/kit
docker compose down -v                    # removes the database volume too
cd ~/cms-dryrun
sudo rm -rf sites kit
mkdir sites
```

Delete the previous site's backup from `backup/` once you no longer need it.

**Update the code and image:**

```sh
cd ~/cms-dryrun/src
git pull
docker build \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
cd ~/cms-dryrun
cp -r src/contrib/cms/docker kit
```

**Back up the new site** as in "What you need": `sites/1400/` and its
database dump from the same moment, checked complete, in
`backup/sites/1400/` and `backup/1400.sql.gz`.

**Then repeat steps 3–7 with the new site's name:**
- **Step 3:** rsync `backup/sites/1400/` to `sites/1400/`.
  - If `default` is a real site on 1400's server, rsync it from its backup
    too and skip the stock `default`.
  - Otherwise copy the stock one, as before.
  - Edit `sites/1400/sqlconf.php` (`$host = 'mysql';`).
- **Step 4:**
  - in `kit/config/sql/`, keep `all-sites.sql` and `1400.sql` (Hide Export
    to Collections) and delete the rest;
  - in `kit/config/sites/1400/`, add `statement.inc.php` from
    `src/sites/default/statement.inc.php`;
  - make the same compose edits as before. `MYSQL_DATABASE` and
    `MYSQL_USER` must differ from 1400's `$dbase` and `$login`.
- **Step 5:**
  - create 1400's database with `COLLATE utf8mb4_general_ci` and load
    `backup/1400.sql.gz`;
  - check `x12_submitter_id` and its values;
  - run the switch-off SQL.
- **Step 6:** note the schema upgrade time for 1400.
- **Step 7:** log in at `?site=1400`. In addition to the checks there:
  - Reports → Collections doesn't offer Export to Collections (agency);
  - compare 837P claims with production using `kit/tools/x12-diff.py`.

To redo the same site from scratch instead, tear down as above, but keep
`kit/` (or copy it fresh if cms-rel-840 changed), and start again from
step 3.

When the dry runs are finished, delete `~/cms-dryrun/backup` and `sites`.
