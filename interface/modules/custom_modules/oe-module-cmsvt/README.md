# CMS Vermont Customizations (oe-module-cmsvt)

Site customizations for CMS Vermont practices, kept out of core OpenEMR.
Enable it in Administration → Modules → Manage Modules (Register, Install,
Enable).

## Console commands

### `cmsvt:x12-sftp-password`

Rotates the SFTP password stored for an X12 partner, such as the NGS
clearinghouse login. It replaces `contrib/multisite/updateNgsPassword.php`
from the 7.0.1 branch.

```sh
# prompts for the new password without echoing it
php bin/console cmsvt:x12-sftp-password --site=default --login=<sftp login>

# non-interactive: read the password from STDIN
php bin/console cmsvt:x12-sftp-password --site=default --login=<sftp login> --password-stdin < password.txt
```

Unlike the old script, the password is never passed on the command line, so
it doesn't end up in the process list or shell history. The command fails if
`auto_sftp_claims_to_x12_partner` is off, or if the login doesn't match
exactly one partner. The value is stored with `encryptForDatabase()`, the
counterpart of the `decryptFromDatabase()` that X12RemoteTracker and EDI270
use to read it.

### `cmsvt:fees-increase`

Raises every code price (all price levels) by a percentage, rounded to whole
dollars. It replaces `contrib/util/billing/update_fee_schedule_by_percentage.php`
from the 7.0.1 branch. `codes.fee` follows the standard price level. The update
runs in one transaction, so it either completes or changes nothing.

```sh
php bin/console cmsvt:fees-increase --site=default --percent=5 --dry-run
php bin/console cmsvt:fees-increase --site=default --percent=5
```

## Reports

### Press Ganey survey export

`public/press_ganey_export.php` (Reports → Visits → Press Ganey Export,
`encounters/coding_a` ACL) writes encounters in Press Ganey's upload CSV
layout. The Client ID and Survey Designator are the `pg_client_id` and
`pg_survey_designator` settings in Administration → Globals → CMS Vermont;
the keys keep their 7.0.1 names so existing values carry over. The field
layout is pinned by `tests/Tests/Isolated/Modules/Cmsvt/PressGaneyRecordFormatterTest.php`.

## Menu

The module adjusts the standard menu through `MenuEvent`: Payment, Posting
Payments, EDI History and Electronic Reports open in their own tabs (`pay`,
`edi`, `edih`, `lab`), and it adds the Press Ganey export.

## Patient statements

`table.sql` creates `patient_statements`, a log of statements sent (one row
per statement, with the delivery method and the saved document). Module
Manager runs it on install; it is a no-op where the table already exists.

- `PatientStatementSavedEvent` (dispatched by the statements screen after a
  statement is saved to the patient's documents) adds a row: `mail` for
  print, download and PDF runs, `email` only when the email went out.
- `StatementPrintSuggestionFilterEvent` (dispatched when listing "Due Pt")
  flags patients with two or more emailed statements that are 21+ days old
  with no payment posted on the encounter since. Their Due Pt box starts
  unchecked and the row shows a PRINT badge, so a biller can email the
  checked set, then use Invert Selection to print the rest.
