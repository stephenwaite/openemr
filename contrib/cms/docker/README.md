# Running cms-rel-840 in Docker

cms-rel-840 needs PHP 8.3+ and MariaDB 10.11+. Instead of upgrading the
server, it runs in OpenEMR's release image built from the fork, with the CMS
configuration applied by the image's vendor hooks (`docker/HOOKS.md`).

    contrib/cms/docker/
      docker-compose.yml                 the stack (MariaDB + the cms-rel-840 image)
      hooks/prelaunch/10-cms-site-files  every start: copies config files into place
      hooks/prelaunch/20-cms-site-settings
                                         every start: applies per-site SQL, once per file
      hooks/prelaunch/cms-apply-sql.php  helper for the above
      tools/x12-diff.py                  compares two 837P files claim by claim
      config/                            sample CMS config, mounted at /cms-config
        sql/all-sites.sql                applied to every site
        sql/<site>.sql                   applied to that site (the directory name under sites/)
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
docker build \
  --build-arg OPENEMR_GIT=https://github.com/stephenwaite/openemr.git \
  --build-arg OPENEMR_VERSION=cms-rel-840 \
  -t cmsvt/openemr:cms-rel-840 docker/release
```

The build clones the branch from GitHub, runs `composer install --no-dev` and
the npm build, and needs network access and several GB of disk. Rebuild after
every change to cms-rel-840.

## 2. Fill in config/

- `sql/<site>.sql`: one file per site, from DEPLOYMENT.md section 3. Fill in
  or remove the placeholders (site 200's eligibility override, site 1300's
  NPI). Add a file for each podiatry site from `podiatry-site.sql.example`.
- `sql/all-sites.sql`: sets Statement Appearance to PDF Custom on every site
  (production's layout). Sites keep their production Statement Logo.
- `sites/<site>/`: for every site, cms-rel-840's `statement.inc.php` (the
  letterhead PNG only if a site lacks it); `chart_review.json` for the
  records-review site (see `sites/README.md`).
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
2. **Before the first start:** check and fix `x12_partners.x12_submitter_id`
   on every site's database (DEPLOYMENT.md section 1). The upgrade won't
   change an existing column.
3. **Site directories.** Bind-mount the site folders, or copy them into the
   `sitevolume` volume. They must be readable and writable by uid 1000, the
   image's `apache`; use `setfacl` if the host owner must stay. In each
   migrated `sqlconf.php`, set `$host = 'mysql';`.
4. **`sites/default`.** The image decides whether OpenEMR is installed by
   reading `sites/default/sqlconf.php`, and only restores a missing
   `default` in swarm mode.
   - **If `default` is one of your real sites,** migrate it like the others.
   - **If it isn't** (as on the server with site 1100), give the container
     a stock `default` from the image. On first start it configures it as a
     new, empty site:
     ```sh
     docker run --rm --entrypoint tar cmsvt/openemr:cms-rel-840 \
       -C /var/www/localhost/htdocs/openemr/sites -c default | tar -C <sites folder> -x
     ```
     `MYSQL_DATABASE` and `MYSQL_USER` in the compose file are used for that
     new site; keep them different from every migrated site's database and
     user.
5. **Start it:** `docker compose up -d`, then follow
   `docker compose logs -f openemr`. In order:
   - `Schema upgrade detected for <site> … (7.0.1)` and `Completed: schema
     upgrade` for each migrated site;
   - `Running quick setup!` if a stock `default` is being configured;
   - the prelaunch hooks: `cms prelaunch:` (files) and `cms settings:`
     (SQL);
   - `Starting Apache!`.

   A failing hook stops the start; the log shows which file and why. The
   upgrade time is your downtime estimate for the cutover.
6. Log in to each site and work through DEPLOYMENT.md's smoke tests.

## How the hooks behave

Both run on every start, after the database upgrade and before Apache:

- **`10-cms-site-files`** overwrites, never deletes, and skips `sites/<site>`
  folders that don't exist. It's safe to run any number of times.
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
