# Site files

Put files here under the site's directory name, at the path they belong in
`sites/<site>/`. The prelaunch hook copies them into place on every start
(existing sites only).

`statement.inc.php` isn't needed here: the hook gives every configured site
the image's copy at each start. Production printed from
`library/statement.inc.php` and never loaded the sites' 7.0.1 copies, which
lack the PDF Custom branch; rel-840 loads the site copy. Put one here only
for a site that must differ; it then replaces the image's.

For a site without its letterhead (production's sites normally keep it,
since Statement Logo and `sites/<site>/images/` work the same way):

    sites/<site>/images/<letterhead>.png  the letterhead named in Statement Logo (612x792 pt)

The records-review site also needs:

    sites/<site>/documents/custom_menus/chart_review.json   from cms-rel-840
