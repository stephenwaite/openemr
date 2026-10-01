# cms-rel-840 deployment checklist

Everything a site needs to behave like production (cms-rel-701) after it
moves to cms-rel-840. The details and reasons for each item are in
PORTING.md, by cluster. Work through this on a **staging copy of a
production database first**, then repeat it for each production site.

## 0. Docker

cms-rel-840 runs in OpenEMR's release image built from the fork (PHP 8.3+
and MariaDB 10.11+ are required, so the existing server can't run it as
is). Everything for that is in `contrib/cms/docker/`; its README has the
image build, moving the 7.0.1 install in (database, site directories, the
`docker-version` marker of `5`), and the vendor hooks.

The hooks automate most of sections 2–6 below:
- **prelaunch** copies the site files (sections 4–5) on every start;
- **postupgrade** applies `config/sql/all-sites.sql` (module, claim
  balancing, drug units) and each `config/sql/<site>.sql` (section 3) once
  per site.

Fill in `config/` from the sections below, then use them as the checklist
to verify each site.

## 1. Before upgrading the database

- [ ] **`x12_partners.x12_submitter_id` type.** Production may have it as
      `tinyint(1)`; upstream uses `smallint(6)`, and the upgrade scripts
      won't alter an existing column. Check, and if it's `tinyint(1)`,
      alter it before upgrading:
      ```sql
      SHOW COLUMNS FROM x12_partners LIKE 'x12_submitter_id';
      -- if tinyint(1):
      ALTER TABLE x12_partners MODIFY x12_submitter_id smallint(6) DEFAULT NULL;
      ```
- [ ] Back up the database and the site directory (`sites/<site>/`).
- [ ] Run the normal OpenEMR upgrade (7.0.1 → 8.4), then log in as an admin.

## 2. Every site

- [ ] **Enable oe-module-cmsvt** (Administration → Modules → Manage Modules:
      register, install, enable). Installing runs its `table.sql`, which
      creates `patient_statements` if it doesn't exist; it's a no-op where it
      does.
- [ ] **Force claim balancing: OFF** (Administration → Globals → Billing,
      `force_claim_balancing`). The upstream default is ON; production never
      balanced claims (cluster 9).
- [ ] **PHP limits.** Statement runs now allow 5 minutes
      (`set_time_limit(300)`), but `memory_limit` still comes from php.ini.
      Large statement or PDF runs need enough memory, and the web server or
      proxy timeout must not be shorter than 5 minutes.
- [ ] The CMS menu changes (Payment, Posting Payments, EDI History and
      Electronic Reports in their own tabs, Reports → Visits → Press Ganey
      Export) come from the module automatically; nothing to configure.

## 3. Per-site settings (Administration → Globals → CMS Vermont)

All default to off/0/blank, which behaves like stock OpenEMR. Set only
what the site used in production.

| Site | Setting | Value |
|---|---|---|
| 200 | Eligibility (270) Provider Override (`cmsvt_elig_provider_id`) | user ID of the provider hardcoded in production commit f5de47125c (`src/Billing/EDI270.php`); confirm that user's NPI matches |
| 200 | Eligibility (270) Receiver Name Override (`cmsvt_elig_receiver_name`) | the receiver name from the same commit |
| 200 | Billing Manager Default Date-of-Service Months (`cmsvt_billing_manager_dos_months`) | 2 |
| 1400 | Hide Export to Collections (`cmsvt_collections_hide_agency_export`) | on |
| default | Hide Visit Details in Encounter Reports (`cmsvt_encounter_report_hide_visit_details`) | on |
| podiatry sites (primary business entity taxonomy 213E00000X) | Label Onset Date as Date Last Seen (`cmsvt_encounter_date_last_seen`) | on |
| podiatry site billing as NPI 1134268188 | Claims: Routine Foot Care Billing NPIs (`cmsvt_claim_routine_foot_care_npis`) | 1134268188 |
| 2400 | Match Lab Results by MRN Only (`cmsvt_hl7_match_patient_by_mrn`) | on |
| 2400 | Electronic Reports Default to Reviewed (`cmsvt_lab_list_default_reviewed`) | on |
| 4800 | Default Lab Results Processed Per Lab (`cmsvt_lab_results_per_lab`) | 50 |
| 1500 | Claims: Payers Without NDCs (`cmsvt_claim_ndc_skip_payer_ids`) | 87726, 39026, TREST, PAMCD (not 25169) |
| 1500 | Claims: Bill Under Provider (`cmsvt_claim_rendering_provider_id`) | 6 |
| 1300 | Claims: Closed Service Facility NPIs (`cmsvt_claim_closed_facility_npis`) | the closed facility's NPI |
| Press Ganey sites | Press Ganey Client ID / Survey Designator (`pg_client_id`, `pg_survey_designator`) | carried over: same keys as 7.0.1; check they show their old values |

## 4. Statements (sites using the CMS statement layout)

- [ ] **Update the site's own `sites/<site>/statement.inc.php`.** Upgrades
      don't replace it. If the site's copy is stock, replace it with the one
      from cms-rel-840 (`sites/default/statement.inc.php`); if it has its own
      changes, copy in the `statement_appearance == "2"` branch of
      `make_statement()`.
- [ ] **Statement Appearance = PDF Custom** (Administration → Globals →
      Billing, `statement_appearance` = 2). A wrong value here gives the stock
      plain-text layout.
- [ ] **Statement Logo** = the letterhead **PNG** (612×792 pt) in
      `sites/<site>/images/`. The default `practice_logo.gif` can't be drawn.

## 5. Records-review user (sites that have one)

- [ ] Put the user in an ACL group **without `patients/appt`** and with
      **`patients/demo` view only** (no write). Assign the `chart_review`
      menu.
- [ ] PatientFilter: move the reviewer's pids from `blacklist` to
      **`whitelist`** in
      `interface/modules/zend_modules/module/PatientFilter/config/blacklist.php`
      (e.g. the output of `contrib/util/chart_review_pids.php`). With
      `blacklist` they would be hidden instead of allowed.
- [ ] Known gap until 8.5.0: the Immunizations card still shows Edit to that
      user (upstream fix 78bd686104 arrives with 8.5.0).

## 6. Drug codes: default units

Administration → Codes can't set Units yet (upstream PR pending; see
`prompts/codes-default-units-pr.md`). Until it lands, set them with SQL on
each site that uses these codes:

```sql
UPDATE codes SET units = 5  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code IN ('C9257', 'Q5124');
UPDATE codes SET units = 2  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J0178';
UPDATE codes SET units = 8  WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J0177';
UPDATE codes SET units = 60 WHERE code_type = (SELECT ct_id FROM code_types WHERE ct_key = 'HCPCS') AND code = 'J2777';
```

These are defaults the biller can change on the line (production forced
them). Optional, for Depo-Medrol: deactivate J1020/J1030/J1040, price J1010
at $1.00 per unit, and leave its units for the biller (1 unit = 1 mg).

## 7. API callers

- [ ] Lab Observations can be searched by the lab's visit number:
      `GET /fhir/Observation?external_id=<visit no.>`.
- [ ] Lab Observation `effectiveDateTime` is the specimen collection time
      (OBR-7), falling back to the report date. For results-only orders it's
      the same value production sent; callers reading `effectiveDateTime`
      need no change. It's local time with offset; a caller that converts to
      UTC moves late-evening results to the next day.

## 8. Smoke tests (staging)

Test data or a de-identified copy only.

- [ ] **Eligibility (site 200):** a real-time 270 sends the override provider
      and receiver name.
- [ ] **Billing Manager:** default date range (site 200: last 2 months),
      re-open and MBO options; Collections report shows/hides the agency
      export (site 1400).
- [ ] **Fee sheet:** review shows today's prices; a drug code arrives with its
      default units; fee = price × units.
- [ ] **Encounter form / report:** "Date Last Seen" label on podiatry sites;
      visit details hidden on `default`.
- [ ] **Reports:** Appointments report (DOB, phone, patient due, primary
      insurer, reminder CSV); Press Ganey export; records-review user sees
      only allowed patients.
- [ ] **ERA/EOB posting:** post a test 835 (claims don't balance; MOA silent;
      N4/modifier 51 handled; posting moves to the next unposted level); the
      invoice screen shows Ins2/Ins3 only when that payer exists and Save
      Current works.
- [ ] **Statements:** Print, Download, PDF and Email runs:
      - the PDF Custom layout prints on the letterhead;
      - Without Update writes only the file (no emails, no documents);
      - Email Selected asks for a count, and refuses when Without Update is
        checked;
      - a second click asks before starting another run;
      - the Due Pt list renders fully and shows the PRINT badge for patients
        with 2+ unpaid emailed statements.
- [ ] **Labs:** import a results-only ORU (site 2400: matched by MRN; visit
      number stored; no encounter or provider notice); Electronic Reports
      defaults (2400 reviewed, 4800: 50 per lab); signing a result returns
      to the list; the patient link opens the latest encounter.
- [ ] **Claims (837P):** generate primary and secondary claims and compare
      with what cms-rel-701 produces for the same encounters (see the Phase 3
      837P comparison):
      - Vermont Medicaid carrier codes in 2330B/SVD;
      - secondary claims carry only patient responsibility (no CO/OA), as
        production;
      - podiatry DTP*304 and supervisor;
      - pay-to address; Medicare IDs without dashes;
      - site 1500: NDC skip and billing provider.
- [ ] **SFTP:** an upload to BCBS VT's MOVEit server goes out as
      `007111NN.x12`.
- [ ] **Console:** `php bin/console cmsvt:fees-increase --site=<site>
      --percent=1 --dry-run` lists the new prices without changing anything;
      `cmsvt:x12-sftp-password` is available.
