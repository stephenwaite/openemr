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

- Reading the charge file (`ChargeFile\ChargeFileReader`) into accounts,
  their four insurances and their charges, as RRMC sent them. Whitespace
  between records is skipped; anything else out of place stops the read with
  the byte position (never field contents). Every field is uppercased except
  the patient's email. Dates are MMDDYY with the year in this century unless
  that is after today; a date that doesn't exist is read as none.

- Step 1's rules, with the 7.0.2 wording and layout for the error files:
  - `Rules\ChargeRules` (rrmc008, errfile008): drops charges not in the charge
    master (`cmsvt_procfile`) or with a $0 fee (except AUC lines, GL 0),
    bills a few HCPCS codes as their CPT, removes RVOC's modifier 26, clears a
    repeated modifier, and turns bilateral studies on certain CDMs into RT/LT
    pairs (the pairing runs across the whole file).
  - `Rules\VisitInsuranceCheck` (errfile008): one patient with different
    primary payers on different visits in the same file. 7.0.2 meant to report
    this but never did.
  - `Rules\PayerCheck` (rrmc007, errfile007): payer codes not in the hospital
    file (`cmsvt_hospfile`) or naming no insurance company (`XXX`), and
    companies without a payer id (paper claims, self pay).

- Step 3's insurance rules (`Account\InsuranceRules`, rrmc006 P2-2/P2-3): the
  old billing system's rewrites of payer codes, policy numbers, groups and
  relationships for Medicare, railroad Medicare, Medicaid of Vermont, BCBS of
  Vermont and out of state, NYS Empire, Medigap, Medicomp and CNA work comp,
  on a typed account record (`Account\AccountRecord`, the account file's
  columns). Rule for rule as in 7.0.2.

- Step 3's account building (`Account\AccountBuilder`, the per-patient part
  of rrmc006): address, relationship, referring doctor (found by NPI, chosen
  or added), the primary and secondary insurance from RRMC's codes, RRMC's
  miscellaneous payers, then the insurance rules. The biller's questions go
  through `StepThreePrompts`; `--yes` answers them with the defaults.

- Step 3's storage: the account file (`Lookup\DatabaseAccountFile`, now
  saving the real secondary policy and the ZIP), referring doctors
  (`Lookup\DatabaseReferringDoctors`, with RRMC keys counting 0-9 then A-Z),
  the ordfile lines (`Ordfile\OrdfileBuilder`, 7.0.2's text layout), work
  comp addresses and email/authorization rows (`Ordfile\DatabaseStepThreeFiles`).
  Setting "Reading Doctor Code" (blank: 01) is the ordfile's docp.

- Step 3, `cmsvt:rri-248` (rrmc006): accounts, ordfile, work comp and
  email/authorization rows, and the patient and insurance update
  (`Patient\PatientUpdater`: every policy's subscriber is the patient, a new
  policy starts January 1). Writes `ordfiles/ordfile-<time>` and, when there
  is anything to report, `errors/errfile248-<time>` (smart edits) and
  `errors/err248-<time>` (bad insurance information from RRMC).

  ```sh
  php bin/console cmsvt:rri-248 --site=1600 --dry-run   # nothing saved; files named ...-dry-run
  php bin/console cmsvt:rri-248 --site=1600             # newest charge file, report date today
  php bin/console cmsvt:rri-248 --site=1600 --file=radb_eisemannbilling261020.txt --date=10212026 --yes
  ```

  `--yes` answers the questions with their defaults (insurance 95 for
  RRMC's miscellaneous payers unless `--y-primary` / `--y-2ndary` say
  otherwise; both are checked before the run). `--resume=<MRN>` starts at a
  patient.

- Step 4, `cmsvt:rri-245` (rri222, 224, 245): checks the ordfile and changes
  nothing. Writes, when there is anything in them, `errors/file222-<time>`
  (each charge with the cancel dated the same day or later, and cancels
  with no charge),
  `errors/file224-<time>` (the CDMs that used to be deleted) and
  `errors/file245-<time>` (bad MRN, bad referring key, no NPI, not read,
  undefined procedure, the same charge twice). An account missing from the
  account file asks whether to go on.

- Step 5, `cmsvt:rri-247` (rri247, 247x): shows each account's insurances and
  relate codes, twenty to a page, sets a blank primary or secondary relate
  to K, writes `errors/w2-<time>`, then shows the accounts whose subscriber
  name or relate code doesn't match the guarantor.

  ```sh
  php bin/console cmsvt:rri-245 --site=1600                         # newest ordfile
  php bin/console cmsvt:rri-245 --site=1600 --date=2026-10-21       # newest ordfile made that day
  php bin/console cmsvt:rri-247 --site=1600 --file=ordfile-10-21-26-09-15
  ```

- Step 6, `cmsvt:rri-250` (rri249, 242, 240): a Medicare (003) primary with
  a Medigap plan as secondary gets secondary 062 and the plan's Medigap id in
  the account file (`errors/file242-<time>`; OpenEMR's insurance is not
  changed). Then each charge gets an encounter of its own (place of service
  from iopat, the patient's referring doctor) with a CPT4 line and, when RRMC
  sent one in the accession field, the ICD10 diagnosis, ready for the coding
  page. A charge paired with its cancel in step 4 is not billed, nor is any
  cancel; charges it can't bill go to `errors/errfile240-<time>`. Running it
  again on the same ordfile bills only what wasn't.
  Settings "Reading Doctor User ID" (blank: 5000) is the provider of the
  reading doctor code's charges; "Outpatient Facility ID" (blank: 4, also
  emergency room) and "Inpatient Facility ID" (blank: 5) the facilities.

  ```sh
  php bin/console cmsvt:rri-250 --site=1600
  ```

Still to come: step 1 as a command (`cmsvt:qqq`), the lookup-file install and
the coding page.
