# Gifford Charge Import

Imports Gifford's radiology charge files (one a day) into site 2400.

## What it does now (staging)

An hourly background service, `Gifford_Charge_Import`, reads each new `.xlsx`, `.csv`
or `.txt` file in the charge file directory and:

- skips a file whose bytes it has already seen, moving it to `processed/`;
- saves a file it cannot read (wrong type, missing columns) as a failed batch in
  `gifford_charge_batch` and moves it to `failed/`;
- otherwise saves the file as a batch and every row as a line in
  `gifford_charge_line` (`staged`, or `held` with its problems), then moves it to
  `processed/`.

Moved files are renamed `<batch id>-<name>`. A file modified in the last two minutes
is left for the next run, so one still arriving is not read half-written.

A file's first row is its header (column names in any order). Gifford's export
(`GIFFORD_CMSVT_RadiologyBilling_<from>_<to>.txt`) has no header: a file whose first
row names none of the required columns is read in the sample's 113-column order, and
is rejected unless every row has exactly 113 fields. A `.txt` file is tab-separated,
UTF-8 or UTF-16 with a byte-order mark. A text file sent under an `.xlsx` name
(Gifford's SFTP script only sends `.xlsx`) is read as text.

The billing-system feed (November 2026) adds three columns after the sample's 113, so
a headerless file may also have 116 fields on every row: `report_signed` (mm/dd/yyyy
hh:mm), `report_status` and `report_text`. The report text is a JSON string body: `\n`
for a line break, `\t`, `\"`, `\\`, `\uXXXX`. When a file has these columns every line
needs a report that reads: status `Signed` or `ISigned` (final) or `Addendum` (added to
or corrected), a signed date and time, and text that decodes. A `Draft` or `Cancelled`
report, an unknown status, a missing signed time or text that does not decode holds
the line with that reason. A file without the report columns is read as before. The
report is saved with the line and shown, folded, beside it on the review page and on
Fees > Quality Measures, where the coder answers the measure questions from it.

## Posting

After staging, the same run posts every line that is not finished (staged, or held),
reading its saved row again, so a held line posts by itself once its cause is fixed.
Lines are posted per patient, visit (`encounter_num`) and date of service, each group
in one transaction:

- **Patient:** found by `pubpid` = `pat_mrn`, else created. Never matched by name or
  date of birth. When the line's date of service is the newest posted for the patient, the
  file's address, phone and email replace the patient's (blank values never erase); a
  replaced address is kept as an inactive Additional Address (Old/Incorrect, reason Moved).
- **Guarantor and employer:** a guarantor (`guar_*`) other than the patient goes in the
  guardian fields, so statements are addressed to them; the patient as guarantor (first and
  last name in either order) clears them. An employer (`emp_*`) different from the latest on
  file is added as a new employer row. Both also only from the newest file.
- **Policies:** each `ins*` policy goes to the insurance company its mnemonic is mapped
  to (`gifford_payer_map`, see Payers). When the policy in effect on the date of service
  has another payer or member id, a new one starts that day and an open-ended older one
  ends the day before. `SP` (self pay) adds none.
- **Encounter:** found by patient, `external_id` = `encounter_num` and date, else created
  on the date of service with the settings' facility and billing facility, the file's
  place of service (`pos_code`), the rendering provider (the active, authorized user
  with `prov_rend_npi`) and the referring provider (any user with `prov_ref_npi`, else a
  new address book entry, External Provider, with the file's name and NPI).
- **Billing:** the encounter's diagnoses (`enc_dx_codes`) it does not have yet, then the
  charge, unbilled, so it is in the Billing Manager. A code priced on the site with
  modifier 26 (standard price level) is billed as code-26, Gifford's modifiers after it
  (`26:RT`); a code priced only without a modifier is billed bare. Fee = price x units;
  the line points to the visit's first four diagnoses.

A line is **held**, with the reason in `gifford_charge_line.problems_json`, when the row
has problems, a payer mnemonic is not mapped, the rendering NPI is not a provider on the
site, the visit's lines (or its posted encounter) disagree on place of service, the code
has no price, Gifford sent `TC`, or `GZ` (expected denial, no ABN; held until a person releases or skips it), the line's accession was posted from an earlier file
as a different charge (a corrected re-send, held until a person releases or skips it), or
the patient already has the same code that day (posted from the same file, or from another
file on another accession, or posting in the same run) and the line has no `76`/`77` (a
possible repeat procedure, held until a person posts it with 76 or 77 or skips it), or the
line is an add-on code whose primary procedure isn't billed for the patient that day
(`77063` needs `77067`; `G0279` needs `77065` or `77066`; the obstetric ultrasound add-ons
need their first-gestation code; held until the parent posts, from this file or a later
one, or a person skips it). The same code on different body sites (`RT` and `LT`, two
fingers or toes, two eyelids or coronary branches) is two procedures, not a repeat; `50`
counts as both sides, and a line without a site modifier can't be told apart, so it is
still a possible repeat.
A line identical to a charge posted from an earlier file on the same accession (the file sent
again) is marked `duplicate`.

Posting needs Admin > Config > Gifford Charge Import > **Facility ID** (the service
facility where the studies are done, Gifford: the claim's service location, loop 2310C)
and **Billing Facility ID** (the reading practice); while either is 0, lines are staged
only. The rendering providers keep their own default facility (where they read).

**Service location on the 837P.** Commercial claims for a Gifford encounter send the
Facility ID facility (Gifford, where the image was done) as the service location, loop
2310C (BCBSVT's guidance). Medicare (claim filing code MA/MB) and Medicaid (MC) claims
leave 2310C out, so their service location is the billing provider, where the
radiologist reads. The check is on the payer the claim goes to, so a secondary claim
follows its own payer. Medicare Advantage plans are commercial here (their insurance
type is commercial). The module wraps the site's claim rules (e.g. the CMS ones) and
changes only this decision. Paper claims (box 32) are not affected.

## NCCI edits and modifier 59

`bin/console gifford:cci-import --site=<site> --file=<path>` loads the billing system's
unloaded CCIFILE into `gifford_cci_edit`, replacing what was there (`--dry-run` reads
and counts only). Each line is the column 1 code, the column 2 code and the modifier
indicator, 11 characters with nothing between them. Until the table is loaded nothing
is checked.

When a line posts, its code is looked up against the patient's other codes for that day
(pending in any visit, or posted and still billed), as the billing system's `cci005`
did:

- **Indicator 1:** the column 2 code posts with `59` after its other modifiers
  (`26:RT:59`), unless it already has `59`, `XE`, `XS`, `XP` or `XU` (Gifford's own are
  kept). If the column 2 code was posted earlier without one, the new line is held
  until `59` is added to that charge in the fee sheet, or a person posts or skips it.
- **Indicator 0:** the column 2 line is held (the pair is not billed together); the
  column 1 line posts, unless the column 2 code is already posted, which holds it too.
  **Post it** posts a held line as it is.
- **Indicator 9:** the edit was deleted; nothing happens.

## Diagnoses from the report (Fees > Gifford Diagnoses)

Off by default: charges post with the diagnoses Gifford sent for the visit. With **Code
Diagnoses From the Report** on (Admin > Config > Gifford Charge Import), each charge posts
with its own accession's diagnoses, coded from the radiologist's report, finding first, and
is held until they are settled. A line Gifford sent with no diagnosis is then no longer a
problem.

- A suggestion read from the report is loaded per accession with
  `bin/console gifford:dx-import --site=2400 --run=<run>.json --lists=gifford-dx-lists.json`
  (`--dry-run` to count without writing). The run is the pair of files `dx_compare.php`
  writes (cms repo); they hold patient data and stay on the server. Stage the charge file
  first. The command prints counts only.
- The suggestion posts without a coder only when its first code is high confidence, every
  code is valid for coding on the site, it passes the coders' workbook checks (first code
  not on the cannot-be-primary list; not unspecified when Medicaid is primary; a code on
  the Medicare list for the exam when Medicare or Medicare Advantage is primary), and its
  first code is among the codes Gifford sent (`Dx\DxRule`).
- Every other accession waits on Fees > Gifford Diagnoses, where the coder sees the report,
  Gifford's indication and codes, the suggestion and why it waits, and saves up to four
  codes in claim order. An accession with no suggestion loaded can be coded there too.
  Accessions nothing else holds are listed first. The page counts how often the coders kept
  the suggestion, kept its first code, or changed it.

Go-live needs the module's `table.sql` run again (table `gifford_dx`) and the site's
ICD-10 code set loaded: a code is valid when it is active and valid for coding there.

## Review page (Fees > Gifford Charges)

For users with Accounting > Billing (write):

- **Unmapped payers:** each payer mnemonic on held lines with no insurance company,
  with Gifford's name, how many lines it holds and when it was first seen. Map it to an
  existing company, or create it as a paper payer (Gifford's name and an insurance
  type; billers add the address and payer ID later). Its lines post on the next run.
- **Held lines:** file, line, patient, MRN, visit, date, code, accession and why each is
  held. **Skip** marks a line never to be posted; **Post it** releases a line held as a
  corrected re-send so it posts on the next run (fix or void the earlier charge in the
  encounter's fee sheet as needed); **Post with 76** / **Post with 77** releases a possible
  repeat procedure, which then posts with that modifier after the others (`26:76`);
  **Post it** also releases a line an NCCI edit holds;
  **Post with GZ** releases a line Gifford sent with `GZ` (it expected a denial as not
  medically necessary, with no ABN), which then posts with the `GZ` kept (`26:GZ`).
- **Post ready lines now** runs posting without waiting for the hour.

## Setting it up on a site

1. Modules > Manage Modules: register, install and enable **Gifford Charge Import**.
   Installing creates the two tables and the background service; enabling switches
   the service on.
2. Admin > Config > Gifford Charge Import > **Charge File Directory**: the directory
   as the server (the container) sees it; **Facility ID** and **Billing Facility ID**
   (Posting). The import must be able to read it and
   create `processed/` and `failed/` inside it.
3. The site's background services cron runs the service (contrib/cms/docker
   README, "Background services"). To run it by hand:

       docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console background:services run --name=Gifford_Charge_Import --force --site=2400

A run started by name prints a warning for a file it could not read. In the cron's
"run all due" mode services run in a child process whose output is not kept, so look
for failed files in `gifford_charge_batch` (`status = 'failed'`, `failure`).

## Payers

Each Gifford payer mnemonic (`ins1_mnemonic` ...) is billed to one of the site's
insurance companies; `gifford_payer_map` holds which. The companies are created from
the payer crosswalk CSV (one row per mnemonic; `SP`, self pay, is not a payer):

| Column | Use |
|---|---|
| `gifford_mnemonic`, `gifford_name` | the map key, and the company's name |
| `electronic` | `Y`: payer ID, X12 partner and address are set; `N`: paper, name only |
| `payer_id_to_use` | the company's payer ID (electronic only) |
| `x12_partner` | starts with the partner id, e.g. `111462 Optum iEDI` (electronic only) |
| `ins_type_code` | the insurance type (2 Medicare B, 3 Medicaid, 6 BCBS, 17 commercial, ...) |
| `street`, `city`, `state`, `zip` | the address (electronic only; zip 5 or 9 digits) |

A header is matched up to its first ` (`, so columns may carry a description; other
columns are ignored. Every bad row is listed and nothing is written. The X12 partners
and insurance types the file names must exist on the site.

    docker compose cp payer-crosswalk.csv openemr:/tmp/payer-crosswalk.csv
    docker compose exec -T -u apache openemr php /var/www/localhost/htdocs/openemr/bin/console gifford:payer-import --site=2400 --file=/tmp/payer-crosswalk.csv --dry-run

then again without `--dry-run`. A mnemonic already mapped is left alone, so billers'
changes to a company (a paper payer's address and payer ID, once its first claim comes
up) are kept, and running it again only adds new mnemonics. A mapped company that was
deleted is created again.
