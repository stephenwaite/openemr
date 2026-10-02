# Site files

Put files here under the site's directory name, at the path they belong in
`sites/<site>/`. The prelaunch hook copies them into place on every start
(existing sites only). For every site (all printed the CMS statement layout):

    sites/<site>/statement.inc.php        cms-rel-840's sites/default/statement.inc.php
    sites/<site>/images/<letterhead>.png  the letterhead named in Statement Logo (612x792 pt)

Don't reuse a site's own 7.0.1 `statement.inc.php`: production printed from
`library/statement.inc.php` and never loaded the site copies, which lack the
PDF Custom branch. rel-840 loads the site copy, so it must be replaced.

The records-review site also needs:

    sites/<site>/documents/custom_menus/chart_review.json   from cms-rel-840
