# Running cms-rel-840 in Docker

cms-rel-840 needs PHP 8.3+ and MariaDB 10.11+. Instead of upgrading the
server, it runs in OpenEMR's release image built from the fork, with the CMS
configuration applied by the image's vendor hooks (`docker/HOOKS.md`).

    contrib/cms/docker/
      docker-compose.yml                 the stack (MariaDB + the cms-rel-840 image)
      hooks/prelaunch/10-cms-site-files  every start: copies config files into place
      hooks/postupgrade/10-cms-site-settings
                                         after an upgrade: applies per-site SQL once
      hooks/postupgrade/cms-apply-sql.php  helper for the above
      config/                            sample CMS config, mounted at /cms-config
        sql/all-sites.sql                applied to every site
        sql/<site>.sql                   applied to that site (the directory name under sites/)
        sites/<site>/...                 copied into sites/<site>/ on every start
        code/...                         copied into the code tree on every start
        php/*.ini                        copied into PHP's conf.d on every start

Copy this folder to the server and keep the real `config/` there. Don't
commit it back: it holds site files and, for the records-review user, a pid
list.

## 1. Build the image

From a checkout of the repository:

```sh
docker build \
  --build-arg OPENEMR_REPO=https://github.com/stephenwaite/openemr.git \
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
- `sql/all-sites.sql`: uncomment the statement settings if every site uses the
  CMS layout; otherwise put them in the sites' own files.
- `sites/<site>/`: `statement.inc.php` and the letterhead PNG for statement
  sites, and `chart_review.json` for the records-review site (see
  `sites/README.md`).
- Records-review user: put production's PatientFilter config, with the pids
  under `whitelist`, at
  `code/interface/modules/zend_modules/module/PatientFilter/config/blacklist.php`.
- `php/99-cms.ini`: optional, from `99-cms.ini.example`, to raise
  `memory_limit`.

## 3. Move an existing 7.0.1 install in

1. **Database.**
   - Start only the database: `docker compose up -d mysql`.
   - Load each site's database from a dump of production
     (`mariadb-dump` → `mariadb`).
   - Recreate the OpenEMR database users with the passwords in each
     `sqlconf.php`, or change the passwords there.
   - Alternatively, mount the old data directory and let
     `MARIADB_AUTO_UPGRADE` upgrade it.
2. **Before the upgrade:** check and fix `x12_partners.x12_submitter_id` on
   every site's database (DEPLOYMENT.md section 1). The upgrade scripts won't
   change an existing column.
3. **Site directories.** Copy each `sites/<site>/` from the old server into
   the `sitevolume` volume, owned by `apache` (uid 1000 in the image). In each
   `sqlconf.php`, set `$host = 'mysql';`.
4. **Upgrade marker.** Write `5` to `sites/default/docker-version`. The image
   tracks upgrades with this number: `fsupgrade-6.sh` upgrades every site's
   database from 7.0.1, and so on through `fsupgrade-15.sh` (from 8.4.0).
   Without the marker it would start from 5.0.1.
5. **Start it:** `docker compose up -d`, then follow
   `docker compose logs -f openemr`. In order:
   - the leader container runs `fsupgrade-6` … `fsupgrade-15` for every site;
   - then the **postupgrade** hook applies `table.sql`, `all-sites.sql` and
     each `<site>.sql`;
   - on every start, the **prelaunch** hook copies the config files;
   - Apache starts.

   A failing hook stops the start; the log shows which file and why.
6. Log in to each site and work through DEPLOYMENT.md's smoke tests.

## How the hooks behave

- **prelaunch** runs on every start. It overwrites, never deletes, and skips
  `sites/<site>` folders that don't exist. It's safe to run any number of
  times.
- **postupgrade** runs when the image upgrades the install, that is when
  the image's `docker-version` is newer than `sites/default/docker-version`.
  It does **not** run on a plain restart or on a schema-only migration.
  - Each SQL file is applied to a site **once**; its checksum is kept in
    `sites/<site>/cms-applied/`. Later upgrades don't overwrite settings
    changed in OpenEMR.
  - Edit a file and it applies again next time.
  - To apply new settings without waiting for an upgrade:
    ```sh
    docker compose exec openemr /root/hooks/postupgrade/10-cms-site-settings
    ```
- The SQL helper connects with each site's `sqlconf.php` and doesn't handle
  database TLS certificates. If the database requires TLS, apply the SQL by
  hand instead.
- The hooks run as root inside the container. Keep `hooks/` and `config/`
  writable only by administrators on the host.
