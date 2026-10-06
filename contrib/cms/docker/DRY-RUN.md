# Dry run: upgrading one site in a sandbox

A practice migration of one production site into the cms-rel-840 Docker
stack, on a machine where production can't be affected. It uses a copy of
the site folder and a database dump taken at the same time, and can be
reset and repeated. The server this site lives on has no real `default`
site, so the container gets a stock one.

The backups contain patient data. Keep them, and the working copy below,
outside any git checkout, on storage that's encrypted or otherwise locked
down. Delete them when the dry run is finished.

## What you need

- The site backup (`sites/<site>/`) and its database dump (`.sql.gz`) from the
  same moment, both checked complete:
  - `gzip -t` passes on the dump;
  - its last line is `-- Dump completed on …`.
- A machine or sandbox with Docker, network access to GitHub, and enough
  Docker disk: about 5 GB for the image, plus the size of the dump and of
  `sites/<site>`.

## 1. Workspace

```sh
mkdir -p ~/cms-dryrun/{backup,sites,db}
cd ~/cms-dryrun
git clone --branch cms-rel-840 https://github.com/stephenwaite/openemr.git src
cp -r src/contrib/cms/docker kit
```

Keep the backups in one place and never write to them: the stack works on
copies, so you can reset from them. They can stay where they are (e.g. on a
separate backup disk); the examples use `~/cms-dryrun/backup/`, with
`sites/<site>/` and `<site>.sql.gz` inside. Don't copy them there as well if
they're already on another disk: a big site's documents would take the
space twice.

## 2. Build the image

```sh
cd ~/cms-dryrun/src
docker build --no-cache-filter openemr-source \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
```

## 3. Working copy of the sites folder

```sh
cd ~/cms-dryrun
SITE=<site>                       # the site's directory name under sites/ (replace)
BACKUP=~/cms-dryrun/backup        # or wherever the backups are, e.g. /media/<disk>/backup
# sudo: the backup keeps production's owners, so your account can't read all of it
sudo rsync -aHAX --numeric-ids --delete --info=progress2 "$BACKUP/sites/$SITE/" sites/$SITE/

# Stock default site from the image: configured on first start as a new, empty site
docker run --rm --entrypoint tar cmsvt/openemr:cms-rel-840 \
  -C /var/www/localhost/htdocs/openemr/sites -c default | tar -C sites -x

# The container's apache is uid 1000 (the image's default APACHE_UID)
sudo setfacl -R -m u:1000:rwX -m d:u:1000:rwX sites
```

Point the site at the database container. In `sqlconf.php`, `$host` must be
`mysql`: `localhost` inside the container is the container itself, and the
start fails with "could not read the version table for <site>":

```sh
sudo sed -i -E "s/^(\\\$host[[:space:]]*=[[:space:]]*)['\"][^'\"]*['\"];/\\1'mysql';/" sites/$SITE/sqlconf.php
sudo grep -n '^\$host' sites/$SITE/sqlconf.php      # $host = 'mysql';
```

## 4. Configure the stack (`kit/`)

Create `kit/.env` from `kit/.env.example`. Keep a copy outside `kit/`
(e.g. `~/cms-dryrun/dryrun.env`) so a fresh kit only needs it copied back:
- `CMS_SITES_DIR=../sites` (the default) bind-mounts `~/cms-dryrun/sites`.
- `CMS_DB_ROOT_PASS`: MariaDB's root password (step 6 passes it to the
  openemr container for its first start only).
  It's only read when the database volume is created; to change it
  later, `docker compose down -v` first.
- `CMS_DEFAULT_DB_PASS` and `CMS_DEFAULT_ADMIN_PASS`: for the stock
  `default` site.
- `CMS_HTTP_PORT`/`CMS_HTTPS_PORT`: if 80/443 are taken, e.g. 8080 and
  8443. In a Docker Sandbox, also publish the port on the host
  (`sbx ports <sandbox> --publish 8443:8443`).

`MYSQL_DATABASE` and `MYSQL_USER` in `kit/docker-compose.yml` (for the new
`default`) must differ from the site's `$dbase` and `$login`. They're
`openemr_default`.

In `kit/config/sql/`, keep `all-sites.sql`, delete the other sites' files,
and add a `<site>.sql` (from `site.sql.example`, or your private copy)
only for CMS settings the site had in production.
Every site gets the image's `statement.inc.php` automatically at start (the
`10-cms-site-files` hook). The letterhead is normally already in place: production read
the same Statement Logo global and `sites/<site>/images/` file. Check after
step 5 that `statement_logo` names a PNG in `sites/<site>/images/`; only if
not, add the PNG under `kit/config/sites/<site>/images/` and set
`statement_logo` in `<site>.sql`.

## 5. Load the database

Set these once per terminal; the rest of this step uses them. They read the
site's database name, user and password from its `sqlconf.php` and the root
password from `kit/.env`, so nothing is retyped:

```sh
cd ~/cms-dryrun/kit
SITE=<site>                                  # as in step 3 (set again in a new terminal)
DUMP=${BACKUP:-~/cms-dryrun/backup}/$SITE.sql.gz   # its database dump
CONF=~/cms-dryrun/sites/$SITE/sqlconf.php
conf() { sudo sed -nE "s/^\\\$$1[[:space:]]*=[[:space:]]*['\"](.*)['\"];.*/\1/p" "$CONF"; }
DB=$(conf dbase); DBUSER=$(conf login); DBPASS=$(conf pass)
RP=$(grep '^CMS_DB_ROOT_PASS=' .env | cut -d= -f2-)
echo "db=$DB user=$DBUSER pass set: ${DBPASS:+yes}"
```

A password containing a single quote breaks the `CREATE USER` line below;
create that user by hand.

Check the dump, then create the database and user and load it:

```sh
gzip -t "$DUMP" && zcat "$DUMP" | tail -1   # -- Dump completed on …
docker compose up -d --wait mysql           # returns once the database accepts connections
docker compose exec -T mysql mariadb -uroot -p"$RP" -e "
  CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
  CREATE USER '$DBUSER'@'%' IDENTIFIED BY '$DBPASS';
  GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBUSER'@'%';"
zcat "$DUMP" | docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB"
```

State the collation: the upgrade creates new tables in the database's
default, and MariaDB 11.5+ otherwise defaults to `utf8mb4_uca1400_ai_ci`.
Joins between those and the dump's `utf8mb4_general_ci` tables then fail
("SQL Statement failed on preparation", e.g. on the patient's contacts).
The dump's own mix (typically `utf8mb3_general_ci`, `utf8mb4_general_ci`
and a few `latin1`) is fine and stays as it is.

Check the stored `x12_submitter_id` values (DEPLOYMENT.md section 1). It
holds the user ID of the claims' submitter contact, and production's
`tinyint(1)` capped it at 127. `all-sites.sql` widens the column at first
start; any 127 here may be a clipped ID, so pick the submitter again in
Administration → Practice → X12 Partners after starting:

```sh
docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB" -e "
  SELECT p.id, p.name, p.x12_submitter_id, u.fname, u.lname
    FROM x12_partners p LEFT JOIN users u ON u.id = p.x12_submitter_id"
```

Then switch off everything that sends messages or files. OpenEMR runs its
background services from the browser of whoever is logged in, so without
this, logging in would send MedEx reminders, queued emails (including
statements), lab orders and SFTP claim uploads. Run it again every time you
reload the dump:

```sh
docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB" -e "
  UPDATE background_services SET active = 0;
  UPDATE procedure_providers SET active = 0, protocol = 'FS', remote_host = '',
    results_path = CONCAT('/tmp/dryrun-hl7/', ppid, '/results'),
    orders_path = CONCAT('/tmp/dryrun-hl7/', ppid, '/orders');
  UPDATE modules SET mod_active = 0 WHERE mod_directory <> 'oe-module-cmsvt';
  UPDATE globals SET gl_value = '0'
   WHERE gl_name IN ('medex_enable', 'auto_sftp_claims_to_x12_partner', 'phimail_enable');
  UPDATE globals SET gl_value = 'smtp.invalid' WHERE gl_name = 'SMTP_HOST';
  TRUNCATE login_mfa_registrations;
  SELECT name, active FROM background_services;"
```

Labs are pointed at local folders (protocol `FS`). `active = 0` alone
isn't enough: Process Results in Electronic Reports polls every lab
regardless, and its SFTP fetch **deletes each result file from the lab's
server**, so production would never get those results. With `FS`, each
lab's results are read from `/tmp/dryrun-hl7/<ppid>/results` in the
container and its orders written to `/tmp/dryrun-hl7/<ppid>/orders`. Each
lab needs its own folder: Process Results goes through the labs in name
order, and a shared folder would let the first one take every file.
Every module but CMS Vermont (what's being tested) is switched off.
`smtp.invalid` can't resolve, so anything that tries to send email fails
instead. Truncating `login_mfa_registrations` is optional: it lets you log
in without the users' authenticator apps. Every background service should
show `active` 0.

To log in as yourself, set a known password hash on your own account in
`users_secure` the same way.

Last, check the collation: the database should be `utf8mb4_general_ci` and
no table `uca1400`:

```sh
docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB" -e "
  SELECT @@collation_database;
  SELECT table_collation, COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE() GROUP BY 1"
```

## 6. Start and watch

The first start configures the stock `default`, which needs MariaDB's root
password; pass it for this start only:

```sh
CMS_SETUP_DB_ROOT_PASS="$RP" docker compose up -d
docker compose logs -f openemr
```

Wait for `Setup Complete!` and then `Starting Apache!` before going on.
Recreating the container earlier replaces its environment at once, and the
stock `default`'s setup then fails with "unable to connect to database as
root" (rerun the command above to recover). Then recreate the container
without it, so the running container holds no database root password:

```sh
docker compose up -d                       # recreates openemr; nothing is reconfigured
docker compose exec openemr printenv MYSQL_ROOT_PASS      # unset
```

Later starts and restarts don't need it: the upgrade check and the hooks
use each site's own database user.

Then switch the background services off again, before logging in: the
upgrade adds services a 7.0.1 database didn't have (7.0.2's
`Email_Service`), and adds them active.

```sh
docker compose exec -T mysql mariadb -uroot -p"$RP" "$DB" -e "
  UPDATE background_services SET active = 0;
  SELECT name, active FROM background_services;"
```

Expected, in order:
1. `Schema upgrade detected for <site>: database is at revision … (7.0.1)`,
   then `Completed: schema upgrade for <site> from 7.0.1`. Note how long it
   takes; that's the downtime estimate for the real cutover.
2. `Running quick setup!` and `Setup Complete!` for the new `default`.
3. The prelaunch hooks: `cms prelaunch: …` (files) and `cms settings: …`
   (SQL applied to the site and default).
4. `Starting Apache!`.

If it stops, the last lines say why (a failed upgrade statement, a hook
error). Fix it, reset (step 8) and start again. If quick setup keeps failing
for `default`, the root password wasn't passed: rerun the first command.

## 7. Check the site

Log in at `https://localhost:<port>/interface/login/login.php?site=<site>`
with a real account on that site, and work through DEPLOYMENT.md's smoke tests
that apply to this site. At least:
- **Documents:** patients' documents open (old file paths are rebuilt
  under the new site folder automatically). Open a few across years and
  types, then check that every active document's file exists. The path is
  rebuilt the way `Document::get_filesystem_filepath()` does it, from the
  file name plus `path_depth` folders. From `~/cms-dryrun`, with your site:
  ```sh
  docker compose -f kit/docker-compose.yml exec -T mysql mariadb -uroot -p<root password> <dbase> -N -e "
    SELECT id, SUBSTRING_INDEX(url, '/', -(path_depth + 1)) FROM documents
     WHERE deleted = 0 AND storagemethod = 0 AND url <> ''" > docs.tsv
  sudo bash -c 'while IFS=$'"'"'\t'"'"' read -r id rel; do
    [ -f "sites/<site>/documents/$rel" ] || printf "%s\t%s\n" "$id" "$rel"
  done < docs.tsv' > missing.tsv
  wc -l docs.tsv missing.tsv
  ```
  `missing.tsv` should be empty. Delete both files afterwards: they hold
  document names.
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
  data; keep it with the backups. With `--summary` it prints only which
  kinds of segment differ (type, qualifier, element numbers) and how often,
  with no values or claim IDs: safe to share. Both files must cover the same
  encounters: take a production batch from before the dump, re-open its
  encounters in the Billing Manager and generate them again.
- **Statements:** a PDF download with Without Update checked writes only
  the file. Compare its text with a production statement for the same
  patient: wording and columns should be identical.
- **Reports and ERA posting**, as far as test data allows.
- **Labs** (on a site with a results-only feed): re-import a result file that
  production already processed, and compare the two. Production archives
  each file under `sites/<site>/documents/procedure_results/<ppid>-<npi>/`.
  Copy one into that lab's local results folder, then click Process Results
  in Procedures → Electronic Reports:
  ```sh
  docker compose exec openemr sh -c 'mkdir -p /tmp/dryrun-hl7/<ppid>/results /tmp/dryrun-hl7/<ppid>/orders && chown -R apache /tmp/dryrun-hl7'
  docker compose cp tools/decrypt-hl7.php openemr:/tmp/decrypt-hl7.php
  docker compose cp tools/poll-labs.php openemr:/tmp/poll-labs.php
  # archives are encrypted when drive encryption is on: always decrypt first
  docker compose exec -u apache openemr php /tmp/decrypt-hl7.php --site=<site> \
    --in=/var/www/localhost/htdocs/openemr/sites/<site>/documents/procedure_results/<ppid>-<npi>/<file> \
    --out=/tmp/dryrun-hl7/<ppid>/results/<file>
  docker compose exec -u apache openemr php /tmp/poll-labs.php --site=<site> --lab=<ppid>
  ```
  `decrypt-hl7.php` prints the layers removed and the segment types (it
  refuses to write anything that isn't HL7 afterwards, e.g. wrong keys);
  `poll-labs.php` runs Process Results for that lab and prints a summary
  without patient details. Or click Process Results in the UI instead.
  Importing an encrypted file doesn't fail cleanly: it ends in "No Lab
  Match", and the import's own archive copy (same path and name as
  production's) is overwritten with the ciphertext encrypted again. To put
  back an archive copy from the backup, rsync that one file, then rerun
  `sudo setfacl -m u:1000:rw <file>`: rsync restores the backup's ACL, and
  the import can't write its archive copy over it ("Cannot create file").
  Use a file production processed before the dump to compare with its
  original: the import attaches to production's existing order (adding a
  second, identical report, as production does for a resent message); a
  newer file creates a new order.
  The new order should match production's for the same file: the same
  patient (by MRN, where that's set), the visit number stored, no encounter, no
  provider notice. The file disappears from the folder once processed.
  Electronic Reports should default to Reviewed, which hides new results
  until they're reviewed: choose All, with a date range (the list stops at
  500 rows), to find it. The folders are inside the container, so they're
  gone after a restart.

Don't send anything real from the dry run: no claims to clearinghouses, no
statements by email, no portal notices. Step 5's switch-off covers the
automatic senders; the manual ones (Email Selected, claim uploads from the
Billing Manager) are still up to you.

## 8. Next site

Each run starts clean: the previous site's stack, database and working
copies are removed, the image and kit come from the current cms-rel-840, and
steps 3–7 are repeated for the new site (`<next>` below).

**Tear down the previous run:**

```sh
cd ~/cms-dryrun/kit
docker compose down -v                    # removes the database volume too
cd ~/cms-dryrun
cp kit/.env dryrun.env                    # if you haven't kept a copy yet
sudo rm -rf sites kit
mkdir sites
```

Delete the previous site's backup from `backup/` once you no longer need it.

**Update the code and image:**

```sh
cd ~/cms-dryrun/src
git pull                                  # for the kit; the build clones from GitHub
docker build --no-cache-filter openemr-source \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
cd ~/cms-dryrun
rsync -a src/contrib/cms/docker/ kit/
```

The trailing slashes matter: this updates `kit/` in place. (`cp -r` into an
existing `kit/` would make a new `kit/docker/` instead.) Files that aren't in
the repo, like `.env` and your private `config/sql/<site>.sql`, are left as
they are.

**Back up the new site** as in "What you need": `sites/<next>/` and its
database dump from the same moment, checked complete, next to the other
backups (`$BACKUP/sites/<next>/` and `$BACKUP/<next>.sql.gz`). Check disk space
first (`df -h ~/cms-dryrun /var/lib/docker`): the working copy takes the
full size of the site folder (several GB with many documents), and so does the backup if
it's on the same disk.

**Then repeat steps 3–7 with the new site's name:**
- **Step 3:** rsync `$BACKUP/sites/<next>/` to `sites/<next>/`.
  - Copy the stock `default` from the new image, as before, if the new site
    is on a multisite server without a real `default`. (For a site
    on a server where `default` is real, migrate that one instead.)
  - Set `$host` to `mysql` in `sites/<next>/sqlconf.php` (the `sed` in step 3).
- **Step 4:**
  - copy the new site's private `<next>.sql`, if it has one, into
    `kit/config/sql/`. Other sites' files there are ignored (the hook only
    applies `<site>.sql` to sites under `sites/`), but you can delete them;
  - copy your `.env` back in (`cp ../dryrun.env kit/.env`). Check that
    `openemr_default` differs from <next>'s `$dbase` and `$login`.
- **Step 5:**
  - set `SITE=<next>` (and `DUMP`, if the dump is elsewhere), then run it
    as written;
  - check the `x12_submitter_id` values;
  - run the switch-off SQL.
- **Step 6:** note the schema upgrade time for the new site.
- **Step 7:** log in at `?site=<next>`. In addition to the checks there,
  check the settings from its `<next>.sql` (DEPLOYMENT.md section 3), e.g.
  that Reports → Collections hides Export to Collections where that's set.

To redo the same site from scratch instead, tear down as above, but keep
`kit/` (or copy it fresh if cms-rel-840 changed), and start again from
step 3.

When the dry runs are finished, delete `~/cms-dryrun/backup` and `sites`.
