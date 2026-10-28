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

### `cmsvt:procfile-import`

Loads a site's codes and standard prices from a CMS fee file: fixed-width
records laid out by the COBOL copybook `PROCFILE01` (charge master number,
CPT/HCPCS, modifier, type, title, amount with two implied decimals; 46
characters a line).

- $0 records are skipped. Lines that aren't 46-character records, and codes
  that are neither CPT4 nor HCPCS, are listed and skipped.
- One price per code and modifier (`71046` and `71046:26` are separate). When
  a code's records disagree, the most common fee wins, and of equally common
  fees the highest; every disagreement is listed with the fees not used.
- A missing code is added with the fee file's title (the wording most of its
  records share), 1 unit, active, and its standard price. An existing code keeps
  its description, active flag and other price levels; only its standard price
  (and `codes.fee`) changes. Running the same file again changes nothing.
- `--dry-run` lists every addition and change and writes nothing. `-v` also
  lists the codes already right.

```sh
php bin/console cmsvt:procfile-import --site=2400 --file=/tmp/wste --dry-run
php bin/console cmsvt:procfile-import --site=2400 --file=/tmp/wste
```

## Reports

### Press Ganey survey export

`public/press_ganey_export.php` (Reports → Visits → Press Ganey Export,
`encounters/coding_a` ACL) writes encounters in Press Ganey's upload CSV
layout. The Client ID and Survey Designator are the `pg_client_id` and
`pg_survey_designator` settings in Administration → Globals → CMS Vermont;
the keys keep their 7.0.1 names so existing values carry over. The field
layout is pinned by `tests/Tests/Isolated/Modules/Cmsvt/PressGaneyRecordFormatterTest.php`.

### MIPS Quality Measures

`public/mips_report.php` (Reports → Clinic → MIPS Quality Measures,
`patients/med` ACL) runs one measure for one performance year, picked on the
page: 039 (osteoporosis screening for women 65-85), 176 (TB screening before a
first biologic), 178 (rheumatoid arthritis
functional status assessment), 180 (rheumatoid arthritis glucocorticoid
management), 410 (psoriasis clinical response to systemic medications) or 509
(melanoma tracking and evaluation of recurrence). For each it lists the
eligible patients, their qualifying visits and how the measure is met (quality
data codes on their fee sheets; for 178 and 180, the clinical rule entries
`act_rafunc` and `act_glucocorticoid`; for 039, DXA orders and `act_osteo`), with each patient's status, data
completeness and performance rate over the denominator, and flags to review.
Both tables export to CSV. It's off unless the site turns on "Show MIPS Quality
Measures Report" in Administration → Globals → CMS Vermont, and "MIPS Measures
Shown" picks the measures (site 200: 176, 410, 509; site 4800: 039, 178, 180).

Each measure is a class in `src/Mips/Measures/` implementing `MipsMeasure`: its
codes, how it finds its patients (`fetch()`, SQL) and how it turns their codes
into statuses (`build()`, pure). The page, template, counts and CSV are shared;
a new measure is one class registered in `MipsMeasures`; 178 and 180 share
`RheumatoidArthritisMeasure`. Started from Margaret Haywood's
`interface/reports/mips_*_report.php` (margarethaywood/openemr,
`Jones-Reporting`; 039, 178 and 180 from `DD-Reporting`); the rules are pinned by
`tests/Tests/Isolated/Modules/Cmsvt/MipsReportTest.php`.

## Menu

The module adjusts the standard menu through `MenuEvent`: Payment, Posting
Payments, EDI History and Electronic Reports open in their own tabs (`pay`,
`edi`, `edih`, `lab`), and it adds the Press Ganey export and, where enabled,
the MIPS quality measures report.

## Patient statements

`table.sql` creates `patient_statements`, a log of statements sent (one row
per statement, with the delivery method and the saved document). Module
Manager runs it on install; it is a no-op where the table already exists.

- `PatientStatementSavedEvent` (dispatched by the statements screen after a
  statement is saved to the patient's documents) adds a row: `mail` for
  print, download and PDF runs, `email` only when the email went out.
- `StatementPrintSuggestionFilterEvent` (dispatched when listing "Due Pt")
  flags invoices (patient and encounter) emailed a statement on two or more
  days, each 21+ days ago with no payment posted on the encounter since. Their
  Due Pt box starts unchecked and the row shows a PRINT badge, so a biller can email the
  checked set, then use Invert Selection to print the rest.

## Lab results

`Labs/LabOptions` listens to `Hl7ResultsImportFilterEvent` and
`LabResultsListFilterEvent`:

- results that arrive without an order are filed under the lab's visit
  number (PID-18), without creating an encounter or notifying the provider
  (all sites);
- `cmsvt_hl7_match_patient_by_mrn` matches results on the MRN the lab echoes
  back (PID-3) alone;
- `cmsvt_lab_results_per_lab` and `cmsvt_lab_list_default_reviewed` set the
  Electronic Reports defaults.

## Claims (837P)

`Billing/ClaimOptions` supplies `Billing/CmsvtClaimRules` through
`Claim837PRulesEvent`: Vermont Medicaid carrier codes for other payers,
other-payer skips (two Medicare policies, VA Community Care), Medicare copay
as coinsurance for Medicaid, tertiary OA-23, CLIA for waived tests, EPSDT as
a note, and podiatry routine foot care rules. Per-site settings:
`cmsvt_claim_routine_foot_care_npis`, `cmsvt_claim_ndc_skip_payer_ids`,
`cmsvt_claim_closed_facility_npis` and `cmsvt_claim_rendering_provider_id`
(through `ClaimProviderFilterEvent`). Uploads to BCBS Vermont's MOVEit server
are renamed `007111NN.x12` through `X12RemoteFilenameFilterEvent`.
