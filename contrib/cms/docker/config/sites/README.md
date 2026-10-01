# Site files

Put files here under the site's directory name, at the path they belong in
`sites/<site>/`. The prelaunch hook copies them into place on every start
(existing sites only). For each site that uses the CMS statement layout:

    sites/<site>/statement.inc.php        from cms-rel-840's sites/default/statement.inc.php,
                                          or the site's own copy with the PDF Custom branch
    sites/<site>/images/<letterhead>.png  the letterhead named in Statement Logo (612x792 pt)

The records-review site also needs:

    sites/<site>/documents/custom_menus/chart_review.json   from cms-rel-840
