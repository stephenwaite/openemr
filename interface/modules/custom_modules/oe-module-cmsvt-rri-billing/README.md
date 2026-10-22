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

Still to come: the rest of step 3 (choosing the insurances, the account file
and patient updates, the ordfile), the lookup-file
install, the console steps (`cmsvt:qqq`, `cmsvt:rri-248`, `-245`, `-247`,
`-250`) and the coding page.
