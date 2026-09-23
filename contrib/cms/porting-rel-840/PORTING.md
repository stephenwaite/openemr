# CMS customizations → rel-840 porting notes

Working notes for porting the CMS customizations (production: `cms-rel-701`)
onto upstream `rel-840`. Updated as work proceeds.

## Status

**Phase 1 approved. Phase 2 in progress: cluster 1.** See "Phase 2 log" at
the end.

## Phase 2 decisions (Stephen, 2026-09-23)

1. **837P zip deletions in 2310C/2330A** (ed739f3265, 94bc254805) are rebase
   errors. **Restore production behavior:** output both N4 zips.
2. **The 7 unexplained commits + the dier gating:** list each with its net
   diff; Stephen decides each. Diffs are saved in `decisions/`; see "Pending
   per-item decisions" below.
3. **Site IDs / `<records-review-user>`:** move them to per-site globals where practical;
   list any that aren't. **Define the CMS globals in a custom module via
   `GlobalsInitializedEvent`, not in `library/globals.inc.php`.**
4. **FHIR:** drop the Vitals/SocialHistory/lab-date changes.
5. **Audit logging:** keep the code defaults; set per site in Globals.
6. **Printed-Rx signature** (bc0382d84e, 596ea45d58): drop.
7. **MedEx** (0219cd4943): drop; use upstream code.
8. **CMS code locations:** menu entries go to `Custom.json` (yes); the one-off
   import scripts move **out of the repo** (yes).
9. **Statements:** the source is **`origin/rel-830-sunflower`**: its
   `statement.inc.php` and the `library/globals.inc.php` change that enables a
   custom statement. Port both to rel-840 as their **own cluster**. This
   replaces the cms-rel-701 statement changes in C3; don't port those.
   **Exception to the module rule:** that global stays in
   `library/globals.inc.php` (upstream candidate).
10. Create the worktree **without `--start`**. Stephen starts the env when
    testing begins.
11. Phase 2 one cluster at a time, starting with cluster 1. **Push
    cms-rel-840 to origin after each cluster.** Still stop for Stephen's OK on
    each structural cluster.
12. Commit these notes to `contrib/cms/porting-rel-840/` on cms-rel-840 and
    keep that copy updated. Before the first push, scan for credentials,
    usernames and claim/patient data, and redact.
13. Fast-forward `origin/rel-840` to `upstream/rel-840`, then open a **draft**
    PR `cms-rel-840 → rel-840` on the fork, with a summary of these decisions.

Setup done:
- **Worktree:** `openemr-cmd worktree add cms-rel-840 -b --base upstream/rel-840 --env easy-light`
  created `../openemr-wt-cms-rel-840`, offset 2 (ports 8302/9302/8312/8322),
  not started.
- **Base moved since the inventory:** upstream/rel-840 is now `57e627290e`.
  That's one commit past `818a1e0f9f` (#14223, CI byte-sync), and it touches
  no port files.
- **`origin/rel-840` fast-forwarded** `7eb4f1dc81..57e627290e` (plain push, no
  force).

## Pending per-item decisions (the 7 unexplained + dier gating)

Full diffs are in `decisions/`.

| # | Item | Net change (production vs rebase) | Where it would land on rel-840 |
|---|---|---|---|
| 1 | bb3852f040 | (a) X12RemoteTracker: for SFTP host `moveit.bcbsvt.com`, rename the claim file to `007111NN.x12` (NN random 20–99) before upload. (b) `Claim::payToFacilityStreet()` `mail_street ?? ''`. (c) Site 1500 NDC-skip payer list drops `25169`: production **sends** NDCs for 25169; the rebase skips them. | (a) X12RemoteTracker (rel-840 added SFTP retry, #13854). (b) Claim.php. (c) X125010837P.php; the site-1500 check becomes a per-site global (decision 3). |
| 2 | 0e3c453afa + 907594e8a0 | Encounter form: the onset-date label reads "Date Last Seen" when the primary business entity's facility taxonomy is `213E00000X`, else "Onset/Hosp. Date". | `C_EncounterVisitForm` + `_date-of-onset.html.twig` (the label is hardcoded there). |
| 3 | 3b46580887 | The records-review user gets **no Edit button** on the Demographics and Insurance cards (write auth forced false for that username). | `src/Patient/Cards/DemographicsViewCard.php`, `InsuranceViewCard.php`. The username becomes a per-site global (decision 3). |
| 4 | 250f586c09 | ISA02 / ISA04 input `maxlength` 10 → 20 on the X12 partner form. | templates/x12_partners/general_edit.html. X12 defines ISA02/ISA04 as fixed 10 characters. |
| 5 | 2be1613f56 (part) | Double-clicking the SSN on the demographics card strips the dashes. **Also** shrinks the patient-portal `small_modal` dialog from 550×550 to 380×200. The rest is reformatting. | The `#text_ss` element no longer exists; it needs a new selector in the card template. |
| 6 | bf2848ba3e (part) | Billing manager: the encounter link also clears the patient and loads demographics in the `pat` tab. The patient link also opens the encounter list and activates the `pat` tab. | interface/billing/billing_report.php (upstream now uses `toEncounter(newpid, enc)`). |
| 7 | 773e6dbb82 | Encounter report (`newpatient/report.php`): on site `default`, **hide the Category, Reason and Provider lines**. Earlier notes said it hid the whole report; that was wrong. | Per-site global (decision 3). |

Last updated: 2026-09-23

## Decisions (Stephen, 2026-09-23)

- `rebase-cms-rel-703` has been pushed to origin and is used as the base set.
- The `upstream` remote was added; only `rel-*` branches were fetched. Upstream
  tags came along automatically: refs only, no branch changed.
- Use `openemr-cmd worktree add cms-rel-840 -b --base upstream/rel-840`, not
  raw `git worktree add`. Not created yet; it's first needed in Phase 2.
- The stash is skipped; it wasn't available here anyway.
- Fork release branches are stale copies: **`upstream/rel-*` everywhere**, and
  the port is based on `upstream/rel-840` (`818a1e0f9f`).
- **All net diffs are three-dot from the merge-base; never tree-to-tree.**

## Reference points

| Ref | SHA | Date |
|---|---|---|
| upstream/rel-701 | 45dac77cf3 | 2023-09-09 |
| upstream/rel-703 | 79c1703190 | 2025-08-27 |
| upstream/rel-840 | 818a1e0f9f | 2026-09-23 |
| origin/cms-rel-701 (production) | 53c9d1cb65 | 2026-09-16 |
| origin/rebase-cms-rel-703 | 7925b417b5 | 2025-05-19 |
| origin/cms-rel-800-fresh | 777db18284 | 2026-03-14 |

| Merge-base | SHA | Meaning |
|---|---|---|
| MB(rel-701, cms-rel-701) | 6c199b068a | Base of production's net diff. rel-701 has 33 later commits production lacks. |
| MB(rel-703, rebase) | c014d47b5e | Base of the rebase's net diff. rel-703 had 87 commits since branching at this point, and kept moving afterwards. |
| MB(rel-703, rel-840) | fdd6819384 | 2025-02-04, the point where rel-703 was cut from master. Base of upstream's rel-703→rel-840 changes. |

Counts against `upstream/rel-701`: 284 production commits (276 non-merge +
8 merges; the merges only bring in CMS feature branches; nothing from upstream
master). 254+12 are yours; 10 are cherry-picks by other authors. The rebase has
204 commits and no merges.

## Phase 1, step 1: delta (done)

`git log --since=2025-05-19 upstream/rel-701..origin/cms-rel-701` gives 24
commits, the same set as against the fork's rel-701.

| SHA | Date | Subject | Trial cherry-pick onto rel-840 |
|---|---|---|---|
| ce0f1972a6 | 2025-05-19 | missing $ for globals var | conflict: patient_tracker.php |
| a9a7f908e6 | 2025-05-19 | missing quotes for flow board | conflict: patient_tracker.php |
| cce7d64502 | 2025-05-21 | fix: EOB posting php8 fatal error | conflict: sl_eob_process.php |
| 0219cd4943 | 2026-01-19 | medex stuff | conflict: MedEx.php |
| 0621ffa778 | 2026-03-11 | ngs password fix | conflict: updateNgsPassword.php (CMS-only file) |
| 40d6f4fd9c | 2026-03-11 | feat: other payer claim control number for secondary claims | conflict: X125010837P.php |
| 28fffc3e45 | 2026-03-11 | feat: update fee sched | clean |
| 76100b2afc | 2026-03-31 | pg report by marg | conflict: standard.json |
| a96f4aec83 | 2026-03-31 | claude fixes dates | conflict: press_ganey_export.php (CMS-only file) |
| f7f691ac06 | 2026-04-29 | vitl fixes | clean |
| 96518400f3 | 2026-04-29 | fee sheet review use current fee | conflict: fee_sheet_queries.php |
| 03839dd47a | 2026-05-05 | strip - from medicare type pols | clean |
| b551ae2b82 | 2026-05-06 | fix dupe on code with mod | conflict: fee_sheet_queries.php |
| 6d65ca3bcb | 2026-05-14 | usfhp dropping facility requirement | conflict: X125010837P.php |
| 5885a91892 | 2026-07-20 | fix(era): unreliable COB in CLP 02 | conflict: sl_eob_process.php |
| 06d16568f8 | 2026-07-21 | fix(era): unreliable COB in CLP 02 but post missed ERA from prior payers properly | conflict: sl_eob_process.php |
| b3dc3cb5b1 | 2026-09-02 | fix(billing): bump edicount for other payer claim cntrl no | clean |
| bc0382d84e | 2026-09-02 | fix(rx): enable dea | conflict: C_Prescription.class.php |
| 596ea45d58 | 2026-09-02 | fix(rx): left justified | conflict: C_Prescription.class.php |
| 984ce86650 | 2026-09-15 | use pid enc in receipts report | clean |
| 46d46dca3c | 2026-09-16 | update units for drug codes | conflict: FeeSheet.class.php |
| 6e92bdbc80 | 2026-09-16 | debug eye form locking | conflict: eye_mag/view.php |
| 16daa94f60 | 2026-09-16 | debug eye form function parseDate | clean |
| 53c9d1cb65 | 2026-09-16 | debug eye form locking remove error_log | conflict: eye_mag/view.php |

(Trial = `git merge-tree --merge-base=<sha>^ upstream/rel-840 <sha>`, in memory.
6e92bdbc80 and 5885a91892 are also gone from the production tip; later
commits removed or reworked them.)

## Phase 1, step 2: unmatched commits (done)

`git cherry -v rebase-cms-rel-703 cms-rel-701` lists 124 `+` / 204 `-` as
written. That range includes upstream's own rel-701 history, so I limited it to
the 284 CMS commits with cherry's `<limit>` argument (`upstream/rel-701`):
120 `+` / 156 `-`. Minus the 24 delta commits, **96 unmatched commits** remain.
None of the delta commits appear in the rebase.

How they were classified:
- whole-patch `git apply --cached -R --check`, using throwaway index files
  loaded with each target tree (nothing checked out);
- a per-hunk content check: ≥80% of a hunk's significant added lines,
  whitespace-normalized, present in the target tree (removal-only hunks: the
  removed lines are gone), scored against the rebase, the production tip,
  upstream/rel-703 and upstream/rel-840. Calibrated on commits with known
  answers;
- manual reading of all 38 ambiguous or unexplained commits.

### Result: 96 = 57 present + 13 superseded + 13 upstreamed + 4 noise + 2 rebase regressions + 7 need attention

**Present (adapted) — 57.** 47 score as present outright. Eight partials were
confirmed adapted by reading: 0ed443529d, 2dce3b703a, 3987e8b806, 3cc7f1349d,
604694f3f3, 62e67dfa54, 67cd8ab224, e067a9e14e. Two unexplained ones were
confirmed adapted: 0920344cab (the ERA parts), 6b5a1f7541 (chart_review.json
already moved to `sites/default/documents/custom_menus/`).
Notes that carry into Phase 3:
- `3987e8b806`: the rebase uses rel-703's reworked HL counting
  (`$HLcount += $patSegmentCount` under `gen_x12_based_on_ins_co`) instead of
  701's per-provider `$HLcount++`. **837P HL numbering differs from
  production.**
- `e067a9e14e`: the payer-specific `if` chains (MCDVT, BCBSVT, 14512 …) and
  the 213E00000X/foot-care test were restructured in both trees. Recheck them
  in the 837P diff.
- `2dce3b703a`: the rebase deliberately sets
  `$external_id = $in_external_visit_no` (receive_hl7_results.inc.php).
  rel-840 still has upstream's form, so port this carefully.

**Superseded within production — 13.** Their content isn't in the production
tip either, so nothing to port: 14cb1a4d10, 898575baf9, 9369cafe59,
9722ffb948, 9d4343fa62, aaeeb78aef, b10afedacd, eff9d57ad4, fa6d9ea537,
98d8da4efd, c7e633acb6, ff341ce87d, 1592aacaaf. 1592aacaaf's surviving `?? ''`
guards in front_payment.php are a trivial PHP 8 warning fix; optional.

**Absent because upstream already does it — 13:**

| SHA | What upstream has |
|---|---|
| abe46fb3c1 | #6533 merged as 39149fe763 (in rel-703 and rel-840); insurance display redone via InsuranceService |
| 0f135bca58 | C_EncounterVisitForm defaults POS from facility (rel-840 ~657–669), gated on `set_pos_code_encounter` |
| 3a161378c6 | rel-840 clinical_rules.php has its own divide-by-zero guard |
| 6d70ef95a0 | collection balance moved to src/Patient/Cards/BillingViewCard.php |
| c146ee8199 | GeneratorX12Direct logs `'X12Direct ' . $claim->action` |
| 99b1727054 | rel-703 always outputs payer zip and logs non-5/9-digit zips. (The production version has a paren bug: `strlen($zip == 9)`.) |
| 4722b1da3e | rel-703 uses `floatval($claim->cptCharges())` |
| 42010ce1f1 | C_EncounterVisitForm takes POS from the facility's `selected` flag. Worth a UI check. |
| 316e6ddad0 | insurance.html.twig uses `date_end\|shortDate` |
| 506905641b | InsuranceService::insert no longer upserts by type |
| 60b8cbda28 | PortalCard shows when `portal_onsite_two_enable` or any API global is on |
| 8985d91a80 | addlistitem.php has `AND option_id = ? AND activity = 1` |
| cff7015cf9 | upstreamed as #6528/#6531, now in src/Common/Forms/Types/LocalProviderListType.php |

**Noise — 4:** 7aa93b6a3d (lockfiles), 0b9d78a4fc (2024 ICD-10 zips; rel-840
ships 2026 data), 1ca7e3c42c (dev docker image pin), 59b3db5b93
(`x12_submitter_id` as `tinyint(1)`; upstream uses `smallint(6)`; see DB
notes).

**Rebase regressions — 2.** The rebase branch removed code that upstream and
production both have. Keep upstream's code; do not carry these edits:
- `ed739f3265` deleted `$out .= $claim->facilityZip();` in loop 2310C N4, so
  no service-facility zip is output (production fix: 17959b4625).
- `94bc254805` deleted `$out .= $claim->x12Zip($claim->insuredZip($ins));` in
  loop 2330A N4, so no other-subscriber zip is output (production:
  84e2be79ee).

### Need your attention — 7 (absent, unexplained)

| SHA | Missing from rebase | Where it would go in 8.x |
|---|---|---|
| bb3852f040 | (1) X12RemoteTracker: rename to `007111NN.x12` for moveit.bcbsvt.com. (2) `Claim::payToFacilityStreet` `mail_street ?? ''`. (3) Payer **25169** removed from the site-1500 NDC-skip list: the rebase re-ported an older list, so **it still skips NDCs for 25169 and production doesn't.** | C1 837P cluster |
| 0e3c453afa, 907594e8a0 | "Date Last Seen" label for facility taxonomy 213E00000X in the encounter form | rel-703+ moved the form to C_EncounterVisitForm and Twig; `_date-of-onset.html.twig` hardcodes "Onset/hosp. date:" |
| 3b46580887 | `<records-review-user>` exclusion from demographics/insurance write-auth | now src/Patient/Cards/DemographicsViewCard.php:64 and InsuranceViewCard.php:46 |
| 250f586c09 | `maxlength="20"` on ISA02/ISA04 inputs (the rebase has 10) | templates/x12_partners/general_edit.html. X12 fixes ISA02/04 at 10 characters: still needed? |
| 2be1613f56 (part) | double-click `#text_ss` strips dashes from SSN | demographics |
| bf2848ba3e (part) | billing manager "patient" button (demographics_full plus encounters list) | billing_report.php; upstream replaced topatient/toencounter with `toEncounter(newpid, enc)` |

## Phase 1, step 2b: file-level gap check (done)

A commit-level majority threshold can hide a single missing hunk, so I also
checked every file production changes (three-dot from `upstream/rel-701`)
that the rebase does not change at all: about 70 files. Scoring production's
per-file hunks shows most are **1.00 present in upstream/rel-703**; you
upstreamed them before 7.0.3. That includes the PatientFilter module,
BillingProcessor/*, X12Partner.class.php, X125010837I.php, the eye form
files, EncounterService, the fee_schedule table, the `bill_svc` abook type,
and chart review #6554.

Real gaps found here that step 2 didn't surface:
- **`773e6dbb82` "dier custom report"**: interface/forms/newpatient/report.php
  hides the encounter report when `$_SESSION['site_id'] == 'default'`
  (multisite gating). Not in the rebase and not upstream.
- `sl_eob_process.php`: production's pre-2025 hunks are 0.78 present in
  rel-703 (upstreamed). What remains is the delta ERA/COB fixes.

## Upstream-origin commits carried in the rebase: drop

The rebase branch includes 13 upstream 7.0.x patch-era commits that
production had cherry-picked. None is patch-identical to anything on
upstream/rel-703 or rel-840. rel-840 has none of their files (upstream
reworked them in 8.x).

| SHA | Subject | Why drop |
|---|---|---|
| 53239ca4cf | fix: recent pt dob to DOB in patch.sql (#7043) | 7.0.x patch SQL |
| e196bd7ec7 | fix: set ckeditor to 4.22.1 … (#7163) | 2798 files of public/assets/modified; 8.x differs |
| 4ff40bfa52 | fix: bug fix (#7193) | package-lock and public/assets |
| 16f1165348 | Weno follow up (#7196) (#7245) | Weno reworked in 8.x |
| **36a9d9f3e8** | **Revert "Merge pull request #7268 fixes-cdr-10"** | **Reverts upstream CDR fixes** (clinical_rules.php 418+/888-). Harmful to carry. |
| de159292f1 | Bring into patch 1 2420E #7405 (#7418) | 2420E is in rel-840 MiscBillingOptions |
| a2b4b9748b | feat: throttle down mechanism (#7587) (#7588) | patch SQL |
| bbb1e9d3eb | fix: fix ci (#7614) (#7615) | ci/apache_82_* compose, not in 8.x |
| 8166f40661 | patch 2 cherry picks | faxsms module; 8.x module evolved |
| f06e37ec6e | add necessary public dependencies to support patch 2 … | public/assets and config.yaml |
| dfb85621c6 | fix: clinical rules - fix the optional/required setting (#7154) | rules helper, 7.0.x only |
| bd5dac9230 | #7754 created patch.sql from 7_0_2-to-7_0_3_upgrade.sql | patch SQL |
| 635b726350 | remove mu3 settings since 6.0.0 will not have mu3 | 6.0.0-era globals change |

Also drop: `a515b985c6` "merge for cherrypick a70374a" (470 lines appended to
sql/7_0_1-to-7_0_2_upgrade.sql), and the rebase's sql/patch.sql additions.

## DB notes for the production upgrade

- `x12_partners.x12_submitter_id`: production may be `tinyint(1)` (from
  59b3db5b93); upstream is `smallint(6)`. The upgrade scripts'
  `#IfMissingColumn` won't alter an existing column. Check the production
  schema before upgrading.
- The `fee_schedule` table and the `bill_svc` abook type exist upstream in both
  rel-703 and rel-840, so there's nothing CMS-specific to add.

## Rebase branch integrity problems (found during inventory)

The rebase was never deployed, and it shows:
- **Committed conflict markers** in `src/Services/FHIR/FhirAppointmentService.php`
  (a PHP syntax error) and three places in `package-lock.json` (invalid
  JSON). Neither production nor rel-840 has any.
- `interface/billing/sl_eob_search.php` requires `$srcdir/api.inc` and
  `$srcdir/forms.inc`. rel-840 has only the `.inc.php` versions (bare `.inc`
  removed in #10495), so this would fatal.
- The two 837P N4 zip regressions (see step 2).

The port reimplements against rel-840 rather than trusting any rebase
conflict resolution.

## Phase 1, steps 3–4: clusters and upstream changes

Method: CMS net = `git diff upstream/rel-703...origin/rebase-cms-rel-703`
(three-dot) plus the delta commits. Upstream change =
`git diff -w -M upstream/rel-703...upstream/rel-840` (three-dot, full rename
detection with `diff.renameLimit=0`). Overlap = in-memory trial merge
`git merge-tree --merge-base=c014d47b5e upstream/rel-840 origin/rebase-cms-rel-703`
(38 conflicting files outside public/assets), plus a per-commit trial for the
delta. Upstream renamed **none** of the port files and deleted **one**:
`…/PostCalendar/…/views/day/ajax_template.html`, moved to Twig in 3d57a13495
(#12435). 23 port files are CMS-only (new files).

Two cross-cutting issues show up in nearly every cluster:
- **`$_SESSION` reads are forbidden by rel-840's PHPStan rules.** CMS code
  gates on `$_SESSION['site_id']` (sites 200, 1400, 2400, 4800, 'default') and
  `$_SESSION['authUser'] == '<records-review-user>'`. Every gate needs
  `SessionWrapperFactory`, or better, a global or site config instead of
  hardcoded site IDs.
- Raw `$GLOBALS` and legacy `sql*` calls in CMS-only files have to become
  `OEGlobalsBag` / `QueryUtils` to pass phpstan without new baseline entries.

"Track" means the Phase 2 approach: **batch** = mechanical, ported together
and reported together; **1-by-1** = structural, reimplemented and shown to you
before I move on.

### Ordered easiest → hardest

#### 1. C12 Site utilities — easy — batch (mostly drop)
One-off import/export and admin CLI scripts.

| File | CMS | Upstream 703→840 | Overlap | Proposal |
|---|---|---|---|---|
| contrib/util/car_pos.php, convert_spreadsheet.php, maple_lane_facesheets.php, match-appts-garno.php, newporthc.php | 76 / 170 / 348 / 197 / 607 lines, CMS-only | n/a | n/a | **Drop from repo** (keep in a site-tools dir outside the build) |
| contrib/util/chart_review_pids.php | 2 hunks (comments out `exit`, hardcodes "VERMONT BLUE ADVANTAGE") | mechanical (30/16) | conflict | Drop the hack; use an argument and `InsuranceCompanyService::search()` |
| contrib/multisite/updateNgsPassword.php | 32 lines + delta 0621ffa778 | n/a | n/a | Port, switching to `CryptoInterface` (direct `CryptoGen` use is deprecated) |
| setup.php | 2 hunks | mechanical | none | **Drop. Security:** `$allow_multisite_setup = true`, `$checkPermissions = false` |
| config/config.yaml | 1 hunk (from upstream-origin f06e37ec6e) | mechanical | conflict | Drop |

#### 2. C11 Small UI / misc — easy (two medium items) — batch, except the two policy items

| File | CMS | Upstream | Proposal |
|---|---|---|---|
| interface/main/messages/messages.php | 1 hunk (drop `text-light bg-dark`) | line unchanged | port |
| interface/patient_file/summary/pnotes_full.php | 1 (`?? ''`) | not fixed | port |
| templates/patient/card/appointments.html.twig | 1 (appt-reason tooltip, `pc_hometext`) | unchanged | port |
| interface/patient_tracker/patient_tracker.php | delta ce0f1972a6, a9a7f908e6 | **already fixed** (rel-840:73) | drop |
| interface/forms/eye_mag/view.php + js/eye_base.php | 3 debug delta commits: **net 0 in view.php, 1 line in eye_base.php** (`parseDate()` keeps hh:mm:ss for lock comparisons) | parseDate unchanged | port the one-liner; drop the rest |
| controllers/C_Prescription.class.php | delta bc0382d84e, 596ea45d58: signature image on **printed** Rx (upstream allows it only for fax), keyed to prescriber `users.id`, left-justified | structural (`current_user_has_signature()`, fax gate) | **Policy decision**; reimplement if kept |
| library/MedEx/API.php, MedEx.php | delta 0219cd4943: normalizes `$events`; **removes `$ignoreAuth = true`** from the callback; host from `$GLOBALS['medex_host']` (no such global in rel-840) | old code still present | **Decision**: removing ignoreAuth may break unauthenticated MedEx callbacks |

#### 3. C4 EDI history / X12 partners / eligibility — easy (one medium file) — batch

| File | CMS hunks | Upstream | Overlap | Proposal |
|---|---|---|---|---|
| library/edihistory/edih_835_html.php | 1 (int→float) | **already fixed** (#13125) | conflict | drop |
| library/edihistory/edih_io.php | 2 (whitespace/comment) | mechanical, ACL #13200 | clean | drop |
| library/edihistory/edih_csv_parse.php | 5 (HL-22 claim split when `gen_x12_based_on_ins_co`; `?? ''`) | mechanical (`(string)` casts), #13151 | 7 conflicts, all mechanical | rewrite using `OEGlobalsBag::getBoolean` (medium) |
| interface/themes/misc/edi_history_v2.scss | 1 (DataTables length-select width) | header only | clean | port |
| src/Billing/EDI270.php | 1: hardcodes provider NPI, ID and name when `site_id == '200'` | mechanical, #11583 | clean | **Recommend config** rather than a hardcode |
| templates/x12_partners/general_edit.html | 0 in rebase; production 250f586c09 `maxlength="20"` on ISA02/04 | whitespace | none | Decision: X12 fixes ISA02/04 at 10 characters |
| library/classes/X12Partner.class.php | 0 | Rector | — | nothing to port (upstreamed) |

#### 4. C5 Billing manager & payments — easy–medium — batch

| File | CMS hunks | Upstream | Overlap | Proposal |
|---|---|---|---|---|
| interface/billing/billing_report.php | 4 (site 200 default 60 days; reopen with `can_mark`; `forms.authorized` filter removed "for <person>") + missing "patient" button (bf2848ba3e) | structural UI #13765, QueryUtils #10884 | 1 region | port; replace the site hardcode |
| interface/patient_file/front_payment.php | 1 (check-no field for credit_card) | mechanical | clean | port |
| interface/patient_file/history/edit_billnote.php | 1 (rows 12) | minor | 1 region | port |
| interface/patient_file/deleter.php | 1 | **already fixed** | 1 region | drop |
| interface/billing/sl_receipts_report.php | delta 984ce86650 (1 line, `irnumber`→`invnumber`) | mechanical | clean | port |
| interface/reports/receipts_by_method_report.php + src/Services/InsuranceService.php | 2 + 1 (insurance by effective date) | mechanical | 1 + 1 | port; make `$date` optional; **remove the `pid == '<pid>'` error_log debug** |
| interface/reports/collections_report.php | 3 (default "Due Ins", export forces individual, site 1400) | mechanical | 2 regions | port |
| src/Services/InsuranceCompanyService.php | 1 (`getAllByName`) | mechanical | clean | drop (only the chart_review_pids hack uses it) |

#### 5. C9 Chart review / practice access & custom report — medium — 1-by-1
Restricted `<records-review-user>` records-review user, PatientFilter allow-list, and print-friendly reports.

| File | CMS hunks | Upstream | Overlap | Notes |
|---|---|---|---|---|
| interface/patient_file/summary/demographics.php | 5 (hide the appointment card and links for <records-review-user>) + missing 3b46580887 write-auth exclusion | **structural**: cards moved to src/Patient/Cards | 4 | the exclusion goes in DemographicsViewCard.php and InsuranceViewCard.php |
| interface/patient_file/summary/stats.php | 2 (immunization auth) | mechanical | none | |
| interface/modules/zend_modules/module/PatientFilter/Module.php | 1: **inverts the blacklist into a whitelist** | mechanical | none | the config key says "blacklist" but means allow-list; document it |
| interface/super/edit_globals.php | 1 (<records-review-user> needs admin/super) | mechanical reformat | 1 | |
| library/globals.inc.php | 2: **lowers `audit_events_other` 1→0 and `gbl_print_log_option` 2→0** | heavy churn | 2 | **Recommend: don't change code defaults**; set per site in Admin→Globals (audit/HIPAA) |
| sites/default/documents/custom_menus/chart_review.json | CMS-only | — | — | copy |
| interface/patient_file/report/custom_report.php | 8 (CSS, DOB, dictation grouping, commented-out code, extra `}`) | mechanical + Mpdf fix, security | 2 | reimplement cleanly |
| interface/patient_file/report/patient_report.php | 3 | mechanical, LBF fix | 2 | |
| library/report.inc.php | 1 (active insurance only) | security fix | 1 | |
| interface/forms/dictation/report.php | 1 (h3→h4) | mechanical | 1 | |
| interface/forms/newpatient/report.php | production 773e6dbb82: hide the encounter report on `site_id == 'default'` (missing from rebase) | — | — | port via session wrapper or config |
| interface/main/tabs/templates/patient_data_template.php | 1 (`fas fa-plus`) | mechanical | none | trivial |
| interface/patient_file/summary/demographics_full.php | blank line | reformat | 1 | drop |

#### 6. C10 Encounter form & demographics UI gaps — medium — 1-by-1
| Item | rel-840 location | Class | Notes |
|---|---|---|---|
| "Date Last Seen" label for taxonomy 213E00000X (0e3c453afa, 907594e8a0) | `interface/forms/newpatient/templates/newpatient/partials/common/fields/_date-of-onset.html.twig:5`, data from `C_EncounterVisitForm` | structural (PHP → controller + Twig) | pass `date_label` from the controller |
| Calendar day view patient name in black (295b750c5a) | `templates/calendar/default/views/day/ajax_template.html.twig` | moved (Smarty→Twig) | CSS on the new element; easy |
| SSN dash-strip on double-click (2be1613f56) | `#text_ss` no longer exists | structural | optional; low value |

#### 7. C6 Fee sheet & fee schedule — medium — 1-by-1
| File | CMS | Upstream | Overlap | Proposal |
|---|---|---|---|---|
| interface/forms/fee_sheet/review/fee_sheet_queries.php | delta 96518400f3 (current fee), b551ae2b82 (dupe on code+mod) | mechanical (SQL to heredoc) | conflicts | reimplement the query |
| library/FeeSheet.class.php | delta 46d46dca3c (hardcoded J/Q/C drug units in a `match`) | mechanical | conflict | better as data; the product path near line 658 may need it too |
| contrib/util/billing/load_fee_schedule.php | 3 hunks (delimiter `,`, echo) | mechanical | clean | port as options |
| contrib/util/billing/update_fee_schedule_by_percentage.php | 105 lines, CMS-only (delta) | n/a | n/a | port, modernized |
| interface/patient_file/printed_fee_sheet_{7400,default}.php, printed_intake_{7400,default}.php | ~560 lines each, CMS-only; **four near-copies** (2–8 lines differ) | upstream printed_fee_sheet.php mechanical only | n/a | collapse into one parameterized file |

The `fee_schedule` table is upstream (in rel-840 database.sql); nothing
CMS-specific in the schema.

#### 8. C7 Custom reports — medium — 1-by-1 (each new report individually)
| File | CMS | Upstream | Overlap | Proposal |
|---|---|---|---|---|
| interface/reports/all_payer.php + Documentation/help_files/payer_mix_help.php | 342 + 60 lines, CMS-only | n/a | n/a | port, modernized (`$GLOBALS`, `sql*`, superglobals) |
| interface/reports/press_ganey_export.php | 540 lines, CMS-only (delta) | n/a | n/a | port, modernized |
| interface/reports/rwt_2024_report.php | 90 lines | **superseded** (rwt_2025/2026 upstream) | — | drop |
| interface/reports/appointments_report.php | 9 hunks (DOB and pt-due columns, site print links) | mechanical + session/CSRF | 2 | port |
| src/Services/SpreadSheetService.php | 1: hardcodes appt-CSV columns in the shared service | mechanical + upstream test | 1 | **move the formatting into the report** (keeps the service generic) |
| interface/reports/insurance_allocation_report.php | 2 (pid list excluding Medicare MA/VT) | mechanical | clean | port |
| interface/main/tabs/menu/menus/standard.json | 5 + delta 76100b2afc (targets enc→pay, bil→edi, edi→edih, pat→lab; Payer Mix, Press Ganey) | re-indent (84/69 with -w) | 2 | **move to `sites/<site>/documents/custom_menus/Custom.json`**, no core edit |

#### 9. C2 ERA/EOB posting — medium — 1-by-1
| File | CMS hunks | Upstream | Overlap | Notes |
|---|---|---|---|---|
| src/Billing/ParseERA.php | 4 (balance unconditionally; count only CO-45/253/59, OA-253, PI-253/59/B10) | mechanical + #11868, #13843; balancing now gated on `force_claim_balancing` | 2 | reference: cms-rel-800-fresh 777db18284 |
| interface/billing/sl_eob_process.php | 0 in rebase (earlier work upstreamed); delta cce7d64502, 5885a91892, 06d16568f8 | **structural**: #10246 centralized payment inserts, floats, QueryUtils (#10545), `writeMessageLine`→`getMessageLine` | 1 per delta commit | 06d16568f8's COB-level correction is new logic; reimplement |
| interface/billing/sl_eob_invoice.php | 5 (JS `pfxFlag`; Ins2 radio only if a secondary payer exists) | mechanical + #13841, #13353 | clean | |
| src/Billing/InvoiceSummary.php | 1 (insurance effective as of encounter date) | mechanical | 1 | easy |

#### 10. C3 Patient statements — medium-hard — 1-by-1
| File | CMS | Upstream | Overlap | Notes |
|---|---|---|---|---|
| library/statement.inc.php | 465 lines, CMS-only; same API as upstream (`make_statement`, `create_statement`, `$STMT_TEMP_FILE`) | upstream's statement code is `sites/default/statement.inc.php` (the per-site customization point) | n/a | **Move the layout into `sites/<site>/statement.inc.php`**, with no core edit |
| interface/billing/sl_eob_search.php | 9 hunks incl. a 165-line letterhead PDF writer inside `upload_file_to_client()`; requires bare `.inc` files (would fatal) | **structural**: `upload_file_to_client_pdf()` split out, gated on `statement_appearance`; printing via symfony/process (#11414) | 6 regions | needs a small hook for the letterhead PDF rather than inline CMS code |

#### 11. C8 Labs / HL7 / FHIR — HL7 medium, **FHIR hard** — 1-by-1
Site-specific lab ingest (site 2400 matches by MRN/pubpid, visit no. → `external_id`), lab review list fixes, and FHIR Observation search by `external_id` using the order transmit date.

| File | CMS hunks | Upstream | Overlap | Notes |
|---|---|---|---|---|
| interface/orders/receive_hl7_results.inc.php | 6 + delta f7f691ac06 (2) | mechanical (Rector, OEGlobalsBag, session wrapper, QueryUtils txns) | 4 | keep the `$external_id = $in_external_visit_no` adaptation; the delta's `nlist` guard is already fixed upstream (drop) |
| interface/orders/list_reports.php | 7 (stayHere, site 4800 max 50, site 2400 reviewed=2, `LIMIT 500`, latest-encounter link) | mechanical + local-function dedupe | 1 | |
| interface/orders/single_order_results.php | 2 (stayHere) | mechanical | none | pairs with list_reports |
| interface/orders/single_order_results.inc.php | 1 | **already fixed** | 1 | drop |
| src/Services/FHIR/FhirObservationService.php | 3: **comments out the SocialHistory and Vitals mapped services**; adds `external_id` search | **structural** (US Core 8 / USCDI v5, granular scopes) | 1 | **Changes API output for every client** |
| src/Services/FHIR/Observation/*Laboratory, *SocialHistory, *Vitals; src/Services/FHIR/Traits/MappedServiceCodeTrait.php | 4 / 1 / 1 / 1: `external_id` support; **effectiveDateTime = order date_transmitted instead of report_date** | structural (US Core 8 lab rewrite) | 3 | semantics change for all clients and for US Core conformance |
| src/Services/ProcedureService.php | 2 (select `external_id`, `date_transmitted`) | structural (+1238/−372) | 2 | reimplement |
| swagger/openemr-api.yaml | 1 | regenerated upstream | none | regenerate |
| src/Services/FHIR/FhirAppointmentService.php | 1 | — | — | **drop** (it's the committed conflict markers) |

#### 12. C1 837P claim generation — **hard** — 1-by-1
Payer-specific 837P rules, secondary/tertiary claims with 04 billing, CLIA/NDC/EPSDT, pay-to address.

| File | CMS hunks | Upstream | Overlap | Notes |
|---|---|---|---|---|
| src/Billing/X125010837P.php | 26 in rebase + delta | mostly mechanical (Rector, OEGlobalsBag, null→string) + fixes: #13724 box 17 qualifier, #11075/#11150 REF*F8, #9669, #9377 | 3 conflicts (pay-to N4 zip, diag ternary, REF*F8); **23 hunks merge cleanly, including the two regressions**, which must be excluded by hand | |
| src/Billing/Claim.php | 13 | mechanical (casts, types) + #12525 | 6 conflicts: `$aadj[$ins]`, copay as PR-3 vs upstream's copay regex, `payToFacility*()` | template: cms-rel-800-fresh ed7ab36f83 |
| src/Billing/BillingProcessor/Tasks/GeneratorX12Direct.php | 1 | mechanical | clean | action logging already upstream |
| src/Billing/BillingProcessor/X12RemoteTracker.php | production bb3852f040 (moveit.bcbsvt.com `007111NN.x12` rename) | #13854 SFTP retry | likely none | rewrite without `$GLOBALS['OE_SITE_DIR']` |

What happens to each delta commit:
- **Drop** 40d6f4fd9c and b3dc3cb5b1: rel-840 already outputs REF*F8 with `++$edicount` (lines 1294–1299).
- **Port** 03839dd47a (strip dashes/spaces from Medicare IDs).
- **Fold** 6d65ca3bcb into the CMS payer-list hunk it edits.

Also carry bb3852f040's payer 25169 NDC-list fix. The Phase 3 837P diff is
essential for this cluster.

### Drop list (consolidated)

- The 13 upstream-origin commits (see that section), including the harmful
  CDR revert 36a9d9f3e8.
- a515b985c6 (upgrade SQL merge; it duplicates the `#IfNotTable fee_schedule`
  block) and the rebase's patch.sql additions.
- Rebase regressions ed739f3265 and 94bc254805.
- FhirAppointmentService hunk (conflict markers); package-lock.json.
- Already fixed upstream: edih_835_html int→float, deleter.php,
  single_order_results.inc.php, patient_tracker delta, receive_hl7 `nlist`
  guard, 40d6f4fd9c, b3dc3cb5b1.
- Superseded or noise: rwt_2024_report.php, edih_io whitespace,
  demographics_full blank line, eye_mag view.php debug, InsuranceCompanyService
  `getAllByName`, config.yaml, docker, ICD-10 zips, test.php.
- Security: setup.php.
- Proposed: the five one-off import scripts in contrib/util (keep outside
  the repo).

## Decisions needed before Phase 2

1. **FHIR (C8):** the CMS changes remove Vitals and SocialHistory Observations
   and redefine lab effectiveDateTime for *all* API clients. Keep them global,
   gate them to one site/client, or drop them?
2. **Audit defaults (C9):** drop the `audit_events_other` /
   `gbl_print_log_option` code-default changes and set them per site instead?
   (Recommended.)
3. **Printed-Rx signature (C11, bc0382d84e):** keep printing the signature
   image on printed prescriptions?
4. **MedEx (C11, 0219cd4943):** was removing `$ignoreAuth = true` from the
   callback intentional?
5. **Hardcoded site IDs** (200, 1400, 2400, 4800, 'default') and the
   `<records-review-user>` username: port them as-is through the session wrapper, or
   replace them with globals/site config? (Config recommended; bigger change.)
6. **One-off import scripts (C12):** drop from the repo?
7. **Menus (C7):** move the CMS menu entries to `sites/<site>/documents/custom_menus/Custom.json`
   instead of editing standard.json? (Recommended.)
8. **Statements (C3):** move the layout to `sites/<site>/statement.inc.php`
   plus a small letterhead hook? (Recommended.)
9. **Small gaps:** keep ISA02/04 `maxlength="20"`, the SSN dash-strip, and
   the billing-manager "patient" button?
10. **DB:** check production's `x12_partners.x12_submitter_id` type before
    upgrading (`tinyint(1)` vs upstream `smallint(6)`).

(Items 1–9 are answered in "Phase 2 decisions" at the top. Item 10 is still
open: it's a production schema check, needed before the DB upgrade.)

## Cluster plan after the Phase 2 decisions

| # | Cluster | Change from the inventory |
|---|---|---|
| 1 | C12 Site utilities | import scripts moved out of the repo; setup.php, config.yaml and the chart_review_pids hack dropped; NGS password becomes a console command in the new CMS module |
| 2 | C11 Small UI / misc | **drop** C_Prescription (printed-Rx signature) and MedEx (use upstream) |
| 3 | C4 EDI / X12 partners / eligibility | EDI270 site-200 provider hardcode becomes a CMS-module global; ISA maxlength pending item 4 |
| 4 | C5 Billing manager & payments | site 200/1400 checks become CMS-module globals; "patient" button pending item 6 |
| 5 | C9 Chart review / access / custom report | records-review user and site checks become CMS-module globals; **drop** the globals.inc.php audit-default changes (set per site in Globals); dier gating pending item 7 |
| 6 | C10 Encounter form gaps | pending items 2 and 5 |
| 7 | C6 Fee sheet & fee schedule | unchanged |
| 8 | C7 Custom reports | menu entries go to Custom.json |
| 9 | C2 ERA/EOB posting | unchanged |
| 10 | **Statements (new source)** | **replaces C3.** Port `statement.inc.php` and the custom-statement global in `library/globals.inc.php` from `origin/rel-830-sunflower` (the global stays in core: upstream candidate). The cms-rel-701 statement changes are **not** ported. |
| 11 | C8 Labs / HL7 (FHIR dropped) | **drop** all FHIR changes (Vitals/SocialHistory removal, lab effectiveDateTime); HL7 site-2400/4800 checks become CMS-module globals |
| 12 | C1 837P | restore both N4 zips (2310C, 2330A); the site-1500 NDC list becomes a CMS-module global; bb3852f040 pending item 1 |

## Phase 2 log

### Cluster 1 — C12 Site utilities (2026-09-23)

Sources: 0621ffa778, 8074853564, 0c505aa260 (updateNgsPassword), plus the
dropped/moved items listed below.

**Ported: `contrib/multisite/updateNgsPassword.php` → console command
`cmsvt:x12-sftp-password`** in a new module
`interface/modules/custom_modules/oe-module-cmsvt/` (namespace
`Cmsvt\OpenEMR\Modules\Customizations\`). The command registers through
`CommandRunnerFilterEvent`, so no core file changes. The CMS globals
(decision 3) will go in this same module through `GlobalsInitializedEvent`.

Why a command rather than a contrib script: rel-840 PHPStan analyses
`contrib/`. Writing `$_GET['site']` is a forbidden request global, and
upstream's own contrib scripts only pass through baseline entries, which we
may not add. `bin/console` handles `--site` itself.

Changes from production's behavior:
- **Encryption:** `encryptForDatabase()` via `ServiceContainer::getCrypto()`,
  matching rel-840's `decryptFromDatabase()` in X12RemoteTracker and EDI270.
  Production used `encryptStandard()`, which is deprecated, and
  `new CryptoGen()`, which PHPStan now forbids.
- **The password is no longer a command-line argument:** hidden prompt, or
  `--password-stdin`. Production's argv password was visible in `ps` and
  shell history.
- Fails unless exactly one partner matches the login. Production silently
  took the first match.
- Exit codes: 0 ok / 1 failure / 2 invalid input. No env-var gate needed:
  `src/` and module classes aren't web-reachable.
- **Flag for Stephen:** delta 0621ffa778 changed the require to
  `__DIR__ . "/../../../interface/globals.php"`, which is outside the repo
  root for a file in contrib/multisite/. Production presumably ran a copy
  from somewhere else. The command removes the question.

**Dropped:**
- setup.php: `$allow_multisite_setup = true` and `$checkPermissions = false`
  (security).
- config/config.yaml tiff path (from upstream-origin f06e37ec6e).
- The contrib/util/chart_review_pids.php hack. rel-840's script is env-gated
  (`OPENEMR_ENABLE_CHART_REVIEW_PIDS`) and takes coverage type and payer ID as
  arguments 4–5; pass the records-review payer's ID. The rebase's version was
  also broken: it assigns `$incos_by_payer_id` but loops over
  `$inscos_by_payer_id`.
- `InsuranceCompanyService::getAllByName()`: its only caller was that hack.

**Moved out of the repo:** car_pos.php, convert_spreadsheet.php,
maple_lane_facesheets.php, match-appts-garno.php, newporthc.php. Extracted
unchanged from `origin/cms-rel-701` into `../cms-porting/site-tools/`, not
committed.

Checks, run in the cms-rel-840 container with Stephen's OK to start it:
- `php -l`: clean on all 4 PHP files.
- `phpcs` (repo ruleset): clean.
- `phpstan` (repo config, **full codebase** as the repo requires): `[OK] No
  errors`, nothing reported for the changed files, `.phpstan/` baseline
  unchanged.

Environment incident, 2026-09-23. The first PHPStan run was incomplete: the
Docker data disk (`/var/lib/docker`, 9.8 GB) was 100% full. With Stephen's
OK, I removed the orphaned `fix-dated-reminders-log-bootstrap` stack (its
worktree was already gone; 3 containers, 12 volumes, network), which freed
3.1 GB. The cms-rel-840 openemr container had crashed with ENOSPC during
first boot and was left in an inconsistent state. To recreate it I ran
`openemr-cmd worktree down`. **I had told Stephen that keeps volumes; it
doesn't: it removed all cms-rel-840 volumes.** Only regenerable data was
lost (a ~15-minute-old fresh-install DB, vendor, node_modules, assets). The
worktree and commits were unaffected. The stack was rebuilt with
`worktree up`. The disk is at 87% afterwards, so watch it before starting
more stacks.

Porting notes are committed at `contrib/cms/porting-rel-840/`, produced by
`../cms-porting/sync-to-repo.sh`. It redacts the records-review username,
patient IDs and personal names, and fails if anything remains.
