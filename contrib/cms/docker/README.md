# Running cms-rel-840 in Docker

cms-rel-840 needs PHP 8.3+ and MariaDB 10.11+. Instead of upgrading the
server, it runs in OpenEMR's release image built from the fork, with the CMS
configuration applied by the image's vendor hooks (`docker/HOOKS.md`).

    contrib/cms/docker/
      docker-compose.yml                 the stack (MariaDB + the cms-rel-840 image)
      .env.example                       per-server settings; copy to .env
      hooks/prelaunch/10-cms-site-files  every start: the image's statement.inc.php to
                                         every site, then config files into place
      hooks/prelaunch/20-cms-site-settings
                                         every start: applies per-site SQL, once per file
      hooks/prelaunch/cms-apply-sql.php  helper for the above
      tools/x12-diff.py                  compares two 837P files claim by claim
      config/                            sample CMS config, mounted at /cms-config
        sql/all-sites.sql                applied to every site
        sql/site.sql.example             template for sql/<site>.sql, applied to that site
                                         (the directory name under sites/); keep those private
        sites/<site>/...                 copied into sites/<site>/ on every start
        code/...                         copied into the code tree on every start
        php/*.ini                        copied into PHP's conf.d on every start

For a practice run on a copy of one site first, see `DRY-RUN.md`.

Copy this folder to the server and keep the real `config/` there. Don't
commit it back: it holds site files and, for the records-review user, a pid
list.

## 1. Build the image

From a checkout of the repository:

```sh
docker build --no-cache-filter openemr-source \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
```

The build clones the branch from GitHub (not your local checkout), runs
`composer install --no-dev` and the npm build, and needs network access and
several GB of disk. Rebuild after every change to cms-rel-840.
`--no-cache-filter openemr-source` makes it clone again; without it, Docker
reuses the cached clone and the image keeps the old code. The image has no
`.git`, so to check what's in it, look for something recent, e.g.
`docker run --rm --entrypoint grep cmsvt/openemr:cms-rel-840 -c adjustmentReason
/var/www/localhost/htdocs/openemr/src/Billing/Statement/CustomPdfStatementText.php`.

## 2. Fill in .env and config/

Copy `.env.example` to `.env` and fill it in: the sites folder, the
database root password, the stock `default` site's passwords and the ports.
`.env` holds passwords; keep it only on the server.

- `sql/<site>.sql`: one file per site that has its own settings (DEPLOYMENT.md
  section 3), made from `sql/site.sql.example`. These files identify the
  sites: keep them only on the server (or in a private repository), never in
  this public one.
- `sql/all-sites.sql`: sets Statement Appearance to PDF Custom on every site
  (production's layout). Sites keep their production Statement Logo.
- `sites/<site>/`: `chart_review.json` for the records-review site, and a
  letterhead PNG only for a site that lacks it (see `sites/README.md`).
  `statement.inc.php` comes from the image automatically.
- Records-review user: put production's PatientFilter config, with the pids
  under `whitelist`, at
  `code/interface/modules/zend_modules/module/PatientFilter/config/blacklist.php`.
- `php/99-cms.ini`: optional, from `99-cms.ini.example`, to raise
  `memory_limit`.

## 3. Move an existing 7.0.1 install in

How the image upgrades: at every start, for each site folder with a
configured `sqlconf.php`, it reads that database's `version` row and runs
`sql_upgrade.php --from=<that release>` if the code is newer
(`check_schema_upgrade` in `docker/release/openemr.sh`). Each site is
upgraded on its own, straight from 7.0.1; no version marker is needed.

1. **Database.**
   - Start only the database: `docker compose up -d --wait mysql` (`--wait`
     returns once it accepts connections; the first start takes a while).
   - Load each site's database from a dump of production
     (`mariadb-dump --single-transaction` → `mariadb`).
   - Create each site's database with
     `CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci`, before loading it.
     Without the collation, MariaDB 11.5+ uses `utf8mb4_uca1400_ai_ci` for
     the tables the upgrade creates, and joins with the dump's tables fail.
   - Create each site's database user with the password in its
     `sqlconf.php`.
2. **Before the first start:** check the stored
   `x12_partners.x12_submitter_id` values on every site's database
   (DEPLOYMENT.md section 1). `all-sites.sql` widens the column itself.
3. **Site directories.** The compose file bind-mounts the folder named by
   `CMS_SITES_DIR` in `.env` as the image's `sites/`: a copy of the 7.0.1
   `sites/` folder, not the live one. It must be readable and writable by
   uid 1000, the image's `apache`; use `setfacl` if the host owner must
   stay. In each migrated `sqlconf.php`, set `$host = 'mysql';`.
4. **`sites/default`.** The image decides whether OpenEMR is installed by
   reading `sites/default/sqlconf.php`, and only restores a missing
   `default` in swarm mode.
   - **If `default` is one of your real sites,** migrate it like the others.
   - **If it isn't** (as on a multisite server without a real `default`), give the container
     a stock `default` from the image. On first start it configures it as a
     new, empty site:
     ```sh
     docker run --rm --entrypoint tar cmsvt/openemr:cms-rel-840 \
       -C /var/www/localhost/htdocs/openemr/sites -c default | tar -C <sites folder> -x
     ```
     `MYSQL_DATABASE` and `MYSQL_USER` in the compose file are used for that
     new site; keep them different from every migrated site's database and
     user.
5. **Start it.** The first start of a stock `default` needs MariaDB's root
   password, passed for that start only:
   `CMS_SETUP_DB_ROOT_PASS=<root password> docker compose up -d`. Follow
   `docker compose logs -f openemr`. Once Apache is up, run
   `docker compose up -d` without it, so the running container holds no
   root password (every later start works without it: upgrades and hooks
   use each site's own database user). In order:
   - `Schema upgrade detected for <site> … (7.0.1)` and `Completed: schema
     upgrade` for each migrated site;
   - `Running quick setup!` if a stock `default` is being configured;
   - the prelaunch hooks: `cms prelaunch:` (files) and `cms settings:`
     (SQL);
   - `Starting Apache!`.

   A failing hook stops the start; the log shows which file and why. The
   upgrade time is your downtime estimate for the cutover.
6. Log in to each site and work through DEPLOYMENT.md's smoke tests.
7. **Background services:** the container runs no cron for OpenEMR, and
   rel-840's CLI refuses root. Schedule them from the host's crontab, per
   site, as `apache` (DEPLOYMENT.md section 7):
   ```cron
   */15 * * * * cd /opt/cms/kit && docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console background:services run --site=<site> >> /var/log/openemr-bg.log 2>&1
   ```

## How the hooks behave

Both run on every start, after the database upgrade and before Apache:

- **`10-cms-site-files`** first gives every site with a `sqlconf.php` the
  image's `statement.inc.php` (from `/swarm-pieces/sites/default/`, the
  image's own copy of `sites/`), unless `config/sites/<site>/` has one. A
  rebuilt image updates every site. Then it copies `config/` files; it
  overwrites, never deletes, and skips `sites/<site>` folders that don't
  exist. It's safe to run any number of times.
- **`20-cms-site-settings`** applies `table.sql`, `all-sites.sql` and each
  `<site>.sql` to a site **once per file**; its checksum is kept in
  `sites/<site>/cms-applied/`. Later starts don't overwrite settings changed
  in OpenEMR. Edit a file and it applies again at the next start, or right
  away with:
  ```sh
  docker compose exec openemr /root/hooks/prelaunch/20-cms-site-settings
  ```
- Why not `postupgrade`: that hook only fires on the image's
  `docker-version` path, which needs a code marker that a new image doesn't
  have. In practice, schema upgrades go through `check_schema_upgrade`, which
  runs no hook.
- The SQL helper connects with each site's `sqlconf.php` and doesn't handle
  database TLS certificates. If the database requires TLS, apply the SQL by
  hand instead.
- The hooks run as root inside the container. Keep `hooks/` and `config/`
  writable only by administrators on the host.
