# CMSVT RRI Billing

Imports RRMC's daily charge file into a site and turns it into patients,
policies, encounters and billing lines. The file is RRMC's fixed-width export
(`##` patient, `++` patient continued, `$$` charge records), first built for
Rutland Radiologists (site 2400) and reused by RRMC for Dr. Eisemann's oncology
account (eis, site 1600).

This is a port of the 2024 `oe-module-cmsvt-rri-billing` that ran on OpenEMR
7.0.2. The directory name is unchanged, so a site moved from 7.0.2 keeps its
Module Manager registration, settings and `cmsvt_*` tables. The differences
between the oncology and radiology rules are per-site settings.

The port is in progress. Done so far:

- Settings (Administration → Config → RRI Billing): the SFTP connection RRMC's
  files are fetched from, debug output, and the coding page's auto-save
  interval. The keys are the 7.0.2 module's, so a moved site keeps its values.
- `table.sql`: the module's tables. Installing again, or installing over a
  database from the 7.0.2 module, changes nothing except
  `cmsvt_ordfile_record.accession`, which becomes `CHAR(8)` allowing NULL (as
  on trisquel, where it was changed by hand). Unlike 7.0.2 it does not replace
  `x12_partners` or insert provider users.

Still to come: the charge file parser and the RRMC rules, the lookup-file
install, the console steps (`cmsvt:qqq`, `cmsvt:rri-248`, `-245`, `-247`,
`-250`) and the coding page.
