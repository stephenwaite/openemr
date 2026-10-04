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

**Commit message convention (2026-09-28):** a prek `commit-msg` hook now
enforces Conventional Commits, so `cms:` is rejected. From cluster 2 on:
**`feat(cms): …`** for cluster commits and `docs(cms): …` for notes commits
(Stephen's choice). The three cluster-1 commits already pushed keep `cms:`.

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

### Cluster 2 — C11 Small UI / misc (2026-09-28)

Sources: 5bfe62a97a, 2e7037df28 (messages.php); 14bbe6fa18 (pnotes_full.php);
fd16864294 (appointments card); 2e7037df28, 16daa94f60 (eye_base.php).

**Ported:**
- `interface/main/messages/messages.php`: the message-body box drops
  `text-light bg-dark`.
- `interface/patient_file/summary/pnotes_full.php`: `?? ''` on the updater's
  `fname`/`lname` (PHP 8 warning; not fixed upstream). Also guards with
  `is_array()` instead of upstream's `!is_null()`: `getUser()` returns
  `array|false`, so `false` used to get through. This fixes 3 baselined PHPStan
  errors (4 after the `is_array()` guard), so their **baseline entries are removed**: argument.type 1 block,
  offsetAccess.nonOffsetAccessible 2 blocks, function.impossibleType 1 block. Nothing added.
- `templates/patient/card/appointments.html.twig`: the appointment reason
  (`pc_hometext`) is the comment icon's tooltip. **Differs from production:**
  escaped with `|attr`; production printed the free-text reason raw inside an
  HTML attribute. Covered by a new render case,
  `appointments-with-reason-tooltip`, with quotes, `<b>` and `&` in the reason.
- `interface/forms/eye_mag/js/eye_base.php`:
  - `parseDate()` keeps hh:mm:ss, so the form-lock comparison uses the time
    (16daa94f60, the net effect of the three debug commits);
  - **no auto-fill of modifier 59** when a test checkbox is ticked
    (2e7037df28). The inventory missed this one; it's in the rebase's net
    diff and in production. Billing-relevant.

**Dropped:**
- C_Prescription.class.php (bc0382d84e, 596ea45d58): decision 6.
- MedEx API.php / MedEx.php (0219cd4943): decision 7.
- patient_tracker.php (ce0f1972a6, a9a7f908e6): both fix the same
  `date('w')` line, and rel-840 already has it (line 73).
- eye_mag/view.php (6e92bdbc80, 53c9d1cb65): the debug changes net to zero.

Checks: `php -l` clean; phpcs clean; `TwigTemplateRenderTest` 30/30; full-codebase phpstan `[OK] No errors`. The new
fixture was generated with `openemr-cmd utf`, and no existing fixture
changed.

### Cluster 3 — C4 EDI history / X12 partners / eligibility (2026-09-28)

Sources: e93f13e540, 14bbe6fa18 (edih_csv_parse.php); 1e7747358f
(edi_history_v2.scss); f5de47125c (EDI270.php site-200 override).

**Ported:**
- `library/edihistory/edih_csv_parse.php`: with `gen_x12_based_on_ins_co`
  on, the 837 CSV index walks each ST–SE once (not once per account, which
  repeated claims) and starts a claim row at every HL level-22 loop instead of
  at BHT. Checked `X12File::edih_x12_transaction()`: for an 837 it returns the
  slice from BHT to the next BHT/SE, which is the whole transaction set in
  that mode, so every claim is indexed. Reads the global via `OEGlobalsBag`.
  The production `?? ''` guards aren't needed: rel-840 initializes `$hl`/`$cdx`.
  The new HL-22 branch works on narrowed copies (`is_string()` → `$segStr`,
  `$sep`) instead of repeating the file's `(string) $seg` / `'HL' . $de`
  idioms. Those idioms are baselined by occurrence count, so repeating them
  fails PHPStan with `ignore.count` (it did on the first commit attempt),
  and we don't raise baseline counts. **Pattern for later legacy-file
  clusters:** new code in baselined files must be type-clean on its own.
- `interface/themes/misc/edi_history_v2.scss`: DataTables length-select
  width 50px.
- **Site-200 eligibility provider override → per-site globals** (decision 3),
  the first CMS globals:
  - Core: new generic `OpenEMR\Events\Billing\EligibilityRequestFilterEvent`,
    dispatched by `EDI270::requestRealTimeEligible()` for each request row
    before validation. Core names nothing from the module, and with no
    listener the row is unchanged (upstream behavior).
  - Module: `CmsvtGlobals` adds an Administration → Globals section "CMS
    Vermont" (via `GlobalsInitializedEvent`) with
    `cmsvt_elig_provider_id` (users.id; 0 = off) and
    `cmsvt_elig_receiver_name` (blank = keep facility name).
    `EligibilityProviderOverride` sets that user's `providerID` and NPI, plus
    the receiver name, so EDI270's normal validation passes.
  - **Differs from production:** production hardcoded an NPI, a provider
    name in `facility_name`, and a `provider_ID` key that nothing reads, and
    it skipped validation entirely. Here the NPI comes from the configured
    user's record. **Deployment, site 200:** set the provider ID and receiver
    name to the values hardcoded in production commit f5de47125c
    (`src/Billing/EDI270.php`), after confirming that user's NPI matches.
  - Verified at runtime on the test DB: no listener → unchanged; global 0 →
    unchanged; provider set → providerID/NPI/receiver name applied.

**Dropped:**
- `library/edihistory/edih_835_html.php` int→float (f30779a52a): already
  upstream (#13125).
- `library/edihistory/edih_io.php` (1e7747358f, 5c3445f685): whitespace and
  comments only.
- ISA02/ISA04 `maxlength="20"` (250f586c09): item 4, dropped. Upstream and
  production schema are both `VARCHAR(10)`; the 20 was left over from a
  reverted Office Ally eligibility experiment (fd409ea71b).
- `library/classes/X12Partner.class.php`: nothing to port (upstreamed).

### Cluster 4 — C5 Billing manager & payments (2026-09-28)

Sources: 306882ba47 98eae0fbc9 dae9bd9adc 81a590151e (billing_report.php);
dfebace7c1 (front_payment.php, edit_billnote.php); 2f968af572
(receipts_by_method_report.php, InsuranceService.php); 64b621d9a0 c8b41a47ba
a65ad6037c cebfcf1be4 (collections_report.php); 984ce86650 (sl_receipts_report.php).

**Site-gate mechanism (Stephen, 2026-09-28): one small typed filter event
per hook in core**, the same shape as cluster 3. Core dispatches the event,
the oe-module-cmsvt listener applies its per-site global, and with no
listener core behaves as upstream. Applies to all later site gates.

**Ported:**
- billing_report.php: **Reopen** is also enabled when claims can be marked
  (not only when they can be billed); the **MBO** button no longer requires
  the misc-billing-options form to be authorized.
- **Billing Manager default search → per-site global.** Core: new
  `BillingManagerDefaultsFilterEvent` (`dosMonths`: null = stock "today",
  0 = no date filter, N = last N months). The default criteria are built in
  local arrays and assigned to `$_REQUEST` once, which lowers the baselined
  `$_REQUEST` write count instead of raising it. Module:
  `cmsvt_billing_manager_dos_months` (default 0 = all unbilled, which is what
  every CMS site except 200 had; site 200 = 2; -1 = stock).
- front_payment.php: the check-number field is also enabled for credit card.
- edit_billnote.php: billing-note textarea 12 rows (rel-840 has 4).
- receipts_by_method_report.php + InsuranceService: when a payment has no
  payer_id, name the insurer that covered the patient **on the payment date**,
  not whichever row happens to come first. `getOneByPid()` gets an
  **optional** `$date` (production made it required); its only caller is
  this report. Also resets `$rowreference` per row (rel-840 could carry a
  stale reference into the next row).
- collections_report.php: "Due Ins" is the default category; exporting to
  collections always sends each encounter as its own invoice (read via
  `CurrentRequest`, not a new `$_POST` access).
- **Collections "Export Selected to Collections" → per-site global.** Core:
  new `CollectionsReportFilterEvent` (`showExportToCollections`). Module:
  `cmsvt_collections_hide_agency_export` (site 1400 = on).
- sl_receipts_report.php: the invoice column always shows pid.encounter
  (`invnumber`), not the invoice reference number.

**Dropped:**
- The billing-manager "patient" button behavior (bf2848ba3e): item 6,
  Stephen.
- The pid-keyed `error_log` debug in receipts_by_method_report.php and the
  commented `error_log` in billing_report.php.
- deleter.php: already fixed upstream.
- `InsuranceCompanyService::getAllByName()`: dropped in cluster 1.

Baseline: **reductions only.** Counts lowered: sl_receipts_report
`empty()` 18→17 and `text()` 8→7; receipts_by_method `$memo` 2→1;
billing_report `$_REQUEST` access 10→8. Two billing_report "offset 0/1 on
mixed" blocks were removed, because building the default criteria in local
arrays fixed them. One redundant `$rowreference ?? ''` was removed at the
source. Nothing added.

Checks: `php -l` and phpcs clean on all 13 files. Runtime-checked on the
test DB: both events unchanged with no listener; the listeners honor the
globals (-1/0/2; hide 0/1); the dated insurance query executes.

### Cluster 5 — C9 Chart review / records-review user / reports (2026-09-28)

Sources: e18d197757 f424bbf754 3b46580887 e46331b860 (demographics.php, stats.php);
373fd020fe (edit_globals.php, PatientFilter); 4694550afd (chart_review.json);
c5e2d397c3 116e470946 333d69ba7d caecf138cd 7925b417b5 8513e439ee (custom/patient
report, dictation); 1b1fc1aff6 (report.inc.php); 773e6dbb82 (encounter report
gating); bcfa780603 86f2daa393 (patient_data_template.php).

**Records-review user → ACL permissions, not username checks** (Stephen,
2026-09-28). No CMS code and no username global. rel-840 already covers
3 of production's 4 username checks through permissions:

| Production username check | rel-840 permission that covers it |
|---|---|
| no Edit on the Demographics card (3b46580887, item 3) | `DemographicsViewCard` shows Edit only with `patients/demo` write |
| no Edit on the Insurance card (item 3) | `InsuranceViewCard`: `patients/demo` write |
| hide the Appointments card and appointment data | card and data render only with `patients/appt` |
| no Edit on the Immunizations card | **not covered in rel-840.** The card hardcodes `'auth' => true`. Upstream master gates the page (78bd686104, #14231); it arrives with the 8.5.0 upgrade (Stephen: don't port). Until then the reviewer sees the Edit button. |
| reviewer must be admin/super to open their own user settings | dropped: user settings can't grant access |

**Deployment:** put the records-review user in an ACL group **without
`patients/appt`** and with **`patients/demo` view only** (no write).
Assign it the `chart_review` menu.

**Ported:**
- **PatientFilter allow-list** (Stephen: `whitelist`): production silently
  inverted the `blacklist` key (the user saw only the listed pids). Now a
  config entry with **`whitelist`** allows only those pids, and `blacklist`
  keeps upstream's meaning. The full pid list is loaded at most once per
  request (production re-queried it on every filter call). Entries are
  narrowed with `is_array()`. **Deployment:** move the reviewer's pids from
  `blacklist` to `whitelist` in `PatientFilter/config/blacklist.php`
  (e.g. the output of `contrib/util/chart_review_pids.php`).
- `sites/default/documents/custom_menus/chart_review.json`: copied from
  production (Finder and patient report only). Reformatted to the repo's
  4-space JSON style with a final newline (the pretty-format-json and
  end-of-file hooks require it); content identical.
- **Encounter report "dier" gating → per-site global** (item 7). Core: new
  `OpenEMR\Events\Encounter\EncounterReportFilterEvent`
  (`showVisitDetails`), dispatched once in `newpatient_report()`. When it's
  off, category, reason, provider, referring provider and POS are blanked
  (reusing upstream's no-access path) and the facility stays, as in
  production. Module: `cmsvt_encounter_report_hide_visit_details`
  (no site sets it: production's check was for site `default`, which isn't
  a real site on any production server; see 2026-10-03).
- custom_report.php, **reimplemented rather than copied**. Encounter and
  dictation forms have no headings; the encounter shows "Date of Service (…)
  Provider: …"; other forms keep their heading but no date; no procedure
  lines; no signature line; `.text` 1rem; 3em padding; DOB in the title
  (`oeFormatShortDate`; production used a date-time formatter on a date).
  **Production had an accidental bug:** a dropped closing brace (patched
  with an extra `}` at end of file) nested all rendering inside
  `if (!empty($dateres['date']))`, so any form whose encounter had no date
  didn't render at all. Not ported.
- patient_report.php: Demographics and Billing unchecked by default; the
  encounter form list is in reverse registry priority.
- report.inc.php `getRecInsuranceData()`: only policies that haven't ended.
- dictation report: h4 headings; `patient_data_template.php`: `fas fa-plus`;
  demographics: the new-appointment dialog is 875px tall (was 500).

Baseline: **reductions only**, 9 in all. 4 PatientFilter/Module.php
blocks removed (narrowing the config entries fixed them); custom_report.php
counts lowered (sqlStatement 5→4, sqlFetchArray 5→4, `text()` 27→24,
`xlt()` 3→2, `form_name` offset 2→1) because the procedure block, signature
and heading were removed. Two new errors from my own code (array_diff over
`list<mixed>`; an untyped DOB join) were fixed in the code. Applied with
`../cms-porting/baseline-reduce.py`, which reads a
`phpstan --error-format=json` run, removes unmatched blocks, lowers
decreased counts, and **refuses** any increase or real error. A final run
reports 0 errors.

Checks: `php -l` and phpcs clean on all touched files; full-codebase
phpstan 0 errors.

**Dropped:**
- library/globals.inc.php audit/print-log default changes: decision 5,
  set per site in Globals.
- demographics_full.php blank line.
- The immunizations change: see the table above (8.5.0).

### Cluster 6 — C10 Encounter form & calendar gaps (2026-09-28)

Sources: 0e3c453afa, 907594e8a0 ("Date Last Seen" label); 295b750c5a
(calendar day view); 2be1613f56 (SSN dash-strip and portal dialog: dropped).

**Ported:**
- **"Date Last Seen" onset label → per-site global + event** (Stephen:
  "use dls with an event/global", instead of production's check of the
  primary business entity's taxonomy `213E00000X`). No new core event: the
  encounter form already dispatches `TemplatePageEvent` (page
  `newpatient/common.php`) on the kernel dispatcher. Core change: the
  `_date-of-onset.html.twig` partial reads an optional `onsetDateLastSeen`
  flag (default false = stock "Onset/hosp. date:") and picks between two
  literal translatable strings. Module: `EncounterFormLabels` sets the flag
  when `cmsvt_encounter_date_last_seen` is on. **Deployment:** turn it on for
  the podiatry site(s) (those whose primary business entity taxonomy is
  213E00000X).
- Calendar day view: patient-name links (`.link_title`) in black. The class
  still exists in rel-840 (`CalendarViewModel`); the rule is added to the
  Twig day template, as production had it in the Smarty one. The
  `calendar-day-screen-empty` render fixture was regenerated (`openemr-cmd
  utf`); only the 3-line style block was added.

**Dropped (Stephen):** the SSN dash-strip on double-click and the smaller
patient-portal dialog (item 5, both from 2be1613f56).

Checks: `php -l` and phpcs clean; Twig compile+render tests 327/327; full-codebase phpstan 0 errors (no baseline changes).
Runtime: the listener sets the flag only for `newpatient/common.php` with the
global on; the partial renders "Onset/hosp. date:" / "Date Last Seen:"
accordingly.

### Cluster 7 — C6 Fee sheet & fee schedule (2026-09-28)

Sources: 96518400f3, b551ae2b82 (fee_sheet_queries.php); 46d46dca3c (FeeSheet
drug units); c510e6a841 (load_fee_schedule.php); 28fffc3e45
(update_fee_schedule_by_percentage.php); 37eb408468, ca5740e029, 3a7952ffb7
(printed forms: dropped).

**Ported:**
- **Fee-sheet review prices at today's fee** (fee_sheet_queries.php
  `fee_sheet_items()`): each procedure's fee is the current price at the
  patient's price level × units, falling back to the stored billing fee.
  The code is matched on its first modifier. **Differs from production:**
  the price comes from a correlated subquery that picks the code like
  `FeeSheet` does (active first, then lowest id; `pr_selector = ''`).
  Production's LEFT JOINs still repeated the billing line when a code had
  more than one codes/prices row. Runtime-tested: an active code plus an
  inactive duplicate gives one line at 100 × 2 = 200.
- **Drug-code default units → data, no code** (Stephen: data). rel-840's
  `FeeSheet::addServiceLineItem()` already defaults a newly added service's
  units from `codes.units`, and the fee sheet adds picked codes without
  units. Production's hardcoded `match` also forced those units over
  whatever was entered; with `codes.units` it is a default the user can
  change. **Deployment (corrected 2026-10-01):** Administration → Codes has
  no Units field, so this can't be done there; see "Upstream #14330
  cherry-picked" and DEPLOYMENT.md section 6 (SQL or Inventory, plus the
  per-unit price conversion).
- **load_fee_schedule.php** (upstream contrib script): production's
  comma delimiter and live price updates become **opt-in env vars**, and
  upstream defaults are unchanged: `OPENEMR_LOAD_FEE_SCHEDULE_DELIMITER=comma`,
  `OPENEMR_LOAD_FEE_SCHEDULE_UPDATE_PRICES=1`. The update uses
  `QueryUtils::sqlStatementThrowException`. **As in production it sets
  every price level of the code** (`WHERE pr_id = ?`); flagging it in case
  that isn't intended.
- **update_fee_schedule_by_percentage.php → console command
  `cmsvt:fees-increase`** in oe-module-cmsvt (`--percent`, `--dry-run`).
  **Fixes versus production:**
  - each price level is updated on its own row (production's
    `WHERE pr_id = ?` set every level to whichever row was processed last);
  - `codes.fee` follows the standard level only;
  - the whole update runs in one transaction (production caught exceptions
    and continued, which could leave prices half raised).
  Runtime-tested on temporary rows: a dry run changes nothing; the real run
  gives 80→88, 100→110, 999→1099; codes.fee = 110.

**Dropped (Stephen):** the four custom printed fee sheet and intake forms
(printed_fee_sheet_{7400,default}.php, printed_intake_{7400,default}.php).
The appointments report's site-specific links to them are dropped in
cluster 8.

Checks: `php -l` and phpcs clean; full-codebase phpstan 0 errors, no baseline
changes. (One redundant `is_array()` in the new command was fixed after the
baseline script refused it.)

### Cluster 8 — C7 Custom reports & menu (2026-09-28)

Sources: 3a7952ffb7 edb92d6d0c a7fd7aecb1 3611fc453e ef1132d37f
(appointments_report.php); 76ac48c916 (SpreadSheetService.php); 76100b2afc
a96f4aec83 (press_ganey_export.php, menu); bc3b45eb35 bb48ec494a 80b73aa6cc
de87a3eac8 62d5ba1f86 (menu targets); 5bd7f597c9 (insurance allocation:
dropped).

**Decisions (Stephen, 2026-09-28):** Payer Mix dropped; Press Ganey goes in
the module; **menu changes via the module's `MenuEvent`**, not Custom.json.
rel-840's Custom.json is a full 2,311-line copy of the standard menu, only
used by users with the Custom menu role, and would drift. This supersedes
decision 8's Custom.json part. Insurance-allocation pid listing dropped.

**Ported:**
- **appointments_report.php** (all sites, as in production): Provider
  column removed; Home/Cell replaced by **DOB**, **Phone** (cell, else home,
  12 chars) and **Pt Due** (patient balance); the second row shows **Primary
  Ins** (or "Unassigned"). CSV export is the reminder-call format: Contact
  (first name + last initial), Phone (home), Start Time (mm/dd/yyyy + time).
  **Differs from production:**
  - DOB comes from the appointment row (`p.DOB` is already selected) rather
    than a `getPatientData()` call per row;
  - DOB is a plain header, because production's DOB sort link was a no-op
    (`sortAppointments` has no DOB order);
  - the insurer name is escaped (production echoed it raw);
  - the balance and insurer lookups are skipped for open slots;
  - the CSV format lives in the report, where rel-840 already builds its CSV
    rows. Production hardcoded it inside the shared `SpreadSheetService`,
    which is left untouched here.
  - The per-site printed fee sheet / intake links are gone with those forms;
    upstream's Superbills link stays.
- **Press Ganey export → oe-module-cmsvt** (`public/press_ganey_export.php` +
  `templates/press_ganey_export.html.twig`), rewritten type-clean:
  `PressGaneyExportFilter` (parsed from the Request), `PressGaneyRepository`
  (QueryUtils), `PressGaneyRecordFormatter` (pure; same 31-field layout,
  lengths, gender codes, phone/date formats, `$` end marker). ACL
  `encounters/coding_a` and CSRF as before. **Settings:** `pg_client_id` and
  `pg_survey_designator` are declared in the module's Globals section **under
  their 7.0.1 key names**. Production never declared them; they were raw
  `globals` rows, so existing values carry over. **Fix:** Address 2 was always
  empty (production read an unselected `street2`); it's now
  `patient_data.street_line_2`. Isolated test
  `tests/Tests/Isolated/Modules/Cmsvt/PressGaneyRecordFormatterTest.php` (7
  tests).
- **Menu (`CmsvtMenu`, `MenuEvent::MENU_UPDATE`):** Fees → Payment opens in
  tab `pay`, Posting Payments `edi`, EDI History `edih`, Procedures →
  Electronic Reports `lab`, each matched on URL + stock target (the popup
  Payment entry is untouched). Adds **Reports → Visits → Press Ganey Export**
  after Encounters (`encounters/coding_a`).

**Dropped:**
- Payer Mix: all_payer.php, payer_mix_help.php and its menu item (Stephen).
- rwt_2024_report.php: superseded upstream.
- insurance_allocation_report.php pid listing (Stephen): raw pids with
  hardcoded plan names.
- The SpreadSheetService change, which moved into the report.

The page renders through `ServiceContainer::getTwig()` with the module's
templates added as the `@cmsvt` namespace (PHPStan forbids `new
TwigContainer`). OpenEMR runs Twig with autoescape off, so every value in the
template is escaped explicitly (`|text` / `|attr`).

Baseline: **reductions only**. appointments_report.php: 3 blocks removed and
5 counts lowered; a follow-up check confirmed 0 entries added and 0 raised.
Nine new-code findings (redundant `array_values`/`is_array`/`is_numeric`, a
`(string)` cast of the phone, the TwigContainer instantiation) were fixed in
code after the baseline script refused them.

Checks: `php -l` and phpcs clean; formatter tests 7/7; full-codebase phpstan
0 errors. Runtime: the menu listener on the real standard.json retargets the
4 items and inserts Press Ganey after Encounters; the template renders through
`@cmsvt` and escapes; the repository queries execute.

### Cluster 9 — C2 ERA/EOB posting (2026-09-28)

Sources: 3139ecf53f 8ff52e17b0 1d49b426be d5d0e13370 e7ddfe4a33 dfebace7c1
db1b8f67be 7298f967d5 ead236a826 bb48ec494a 64e57f0e59 (ParseERA.php);
06d16568f8 5885a91892 cce7d64502 (sl_eob_process.php, delta after the
rebase); 09533320e5 9ef4b7795b ca5740e029 (sl_eob_invoice.php); 0ac52119cb
(InvoiceSummary.php).

**Ported:**
- **ParseERA.php:**
  - The MOA (claim-level outpatient adjudication) warning is removed; MOA is
    now ignored silently, as in production.
  - **SVC06 restatements:** an N4 (NDC) restatement warns and keeps the
    procedure code from SVC01. A modifier `51` the payer added to SVC01 is
    removed, so the line matches our billed code. Otherwise upstream's "Payer
    is restating…" warning stays. **Fix vs production:** production dropped
    the *last* SVC01 element whenever `51` appeared anywhere, and it ran
    that branch for every non-empty SVC01, so the restating warning never
    fired. Now only a `51` in a modifier position (index ≥ 2) is removed.
  - **Any SVC qualifier is accepted** (HC, N4 and others). This keeps
    production's behavior: its check `!($q != 'HC' || $q != 'N4')` was
    always false. Upstream rejects everything but HC with "SVC segment has
    unexpected qualifier". ⚠ **Flagged for Stephen:** if only HC and N4
    should pass, it's a one-line check.
  - **Negative CO adjustments are posted as reported**, not inverted with
    a warning (production commented the inversion out).
- **sl_eob_process.php, reimplemented from 06d16568f8/5885a91892:** CLP02
  (claim status) is unreliable for COB. If the reported level already has
  postings for the encounter in `ar_activity`, the ERA is posted to the next
  level that has none (only moving up), with an info line saying so.
  cce7d64502's `$mods[$v] ?? []` guard is already in rel-840, so it is not
  ported.
- **sl_eob_invoice.php:**
  - Ins2/Ins3 radios are shown only when the patient has that payer on the
    service date (`SLEOB::arGetPayerID`).
  - An invoice with no line items no longer trips "Nothing to Post" (this is
    production's `pfxFlag`, renamed `hasLineItems`).
  - The **Save Current** button is restored. The server already handles
    `form_save == 1`.
- **InvoiceSummary.php:** payer names in adjustment reasons ("Ins1" →
  insurer) use the policy in effect on the **encounter date**, not the
  latest row (joins `form_encounter`, filters `date`/`date_end`).

**Claim balancing: no code change, deployment step instead.** Production
still computed the balancing totals but commented out everything that acted
on them (the artificial 'Claim' line and the CR/Balancing adjustment), and
restricted the counted adjustments to CO-45/253/59, OA-253 and
PI-253/59/B10. Net effect: no balancing at all. rel-840 gates balancing on
`force_claim_balancing`. **Deployment, every CMS site: set Administration →
Globals → Billing → "Force claim balancing" OFF** (the upstream default is
ON). The adjustment-code list only fed the dead totals, so it is dropped.

Baseline: **reductions only.** ParseERA.php loses 3 entries (the MOA and
Negative-CO warnings, and `0 - mixed`). Two new-code findings (the
`intval(...)` callback and a `.=` on mixed warnings) were fixed in code after
the baseline script refused them.

Checks: `php -l` and phpcs clean; full-codebase phpstan 0 errors. Runtime:
an encounter dated 2025-03-01, with an old policy ending 2025-06-30 and a
new one starting 2025-07-01, shows the **old** payer in the adjustment
reason (test rows removed afterwards).

### Cluster 10 — Statements (from rel-830-sunflower) (2026-09-28)

Sources: origin/rel-830-sunflower 36c0016ea6 (statement.inc.php), 3d20327ee4
(global), 61b414b422 1d4ac7fcf3 8ca3ecf8e7 (sl_eob_search.php); the
`patient_statements` writer and table from origin/rel-800-sunflower
28104198ad and 1baf63348c (it never made it into rel-830-sunflower).

**Decisions (Stephen, 2026-09-28):**
- The Modern/images layout (appearance 1) stays as upstream ships it.
  Sunflower's changes to it are **not** ported: logo, "Account Number",
  one line per adjustment, the hardcoded payments.sunflowerpediatriceyecare.com
  Pay Online button, and the QR code.
- `patient_statements` comes into the port. Stephen supplied the production
  schema; the writer was in rel-800-sunflower.

**Ported:**
- **"PDF Custom" appearance (statement_appearance = 2):** the option is added
  in `library/globals.inc.php` (core, upstream candidate). `make_statement()`
  in `sites/default/statement.inc.php` routes to it with the same
  print-exclusion rule production used ("All" prints everything). The layout
  is rewritten as two typed core classes instead of new global functions
  (PHPStan forbids new global-namespace functions):
  - `OpenEMR\Billing\Statement\CustomPdfStatementText`: the fixed-width
    text (address block, detail lines, 34-line page split, aging line);
  - `OpenEMR\Billing\Statement\CustomPdfStatementPdf`: lays that text over a
    full-page letterhead PNG with Cezpdf.

  **Checked against production:** on the same statement (5 detail kinds plus
  45 extra payments, so it splits), the new text output is **byte-for-byte
  identical** to rel-830-sunflower's `create_cms_statement()`. The PDF has the
  same text; it has **one page fewer**, because production emitted a blank
  first page when the first statement was a long one (its `$page_count`
  global; the `$page_count = -1` reset in sl_eob_search.php never took
  effect). Isolated test `tests/Tests/Isolated/Billing/CustomPdfStatementTextTest.php`
  (5 tests).

  **Differs from production:**
  - Descriptions come from the billed line's `code_text` (InvoiceSummary),
    not a `codes` lookup per line.
  - The address block takes the street lines present, then city/state/zip
    last. Production forced four `to[]` entries, which would have dropped
    city/state/zip from the upstream layouts.
  - The letterhead is only drawn when Statement Logo names a PNG (Cezpdf can't
    place a GIF, and the global defaults to practice_logo.gif).
  - Dead code dropped: the unused facility, contact, dunning and label
    lookups; `if ($continued = true)`; and a `to[4]` branch that could never
    run.
- **sl_eob_search.php:**
  - `street_line_2` is selected and added to the address.
  - Email bodies are always HTML (`create_HTML_statement`), whatever the
    print layout is.
  - Emailing needs portal access and an acknowledged HIPAA notice as well as
    email consent, the same consents the list's Email column already shows.
  - A failed email no longer overwrites the alert: every failed patient is
    listed. Sends are 0.1 s apart (SMTP rate limit).
  - An email run no longer falls through and prints everything it just
    emailed.
  - Email eligibility comes from the main list query, not one
    `SELECT * FROM patient_data` per row. The cell carries
    `data-email-eligible`.
  - New buttons: **Select All Not Email**, **Invert Selection**, and a
    confirm dialog with the count before **Email Selected**.
  - The typed `$postedForm` request bag replaces the new `$_REQUEST` reads;
    `form_pdf`/`form_portalnotify` no longer warn when unset.
- **Upstream bug fixed: the statement list dies partway through the first
  row on stock rel-840.** Reproduced on an untouched copy of rel-840's
  sl_eob_search.php (80 test patients, Due Pt): the output stops at the first
  Email cell with "An error has occurred". The cause: sessions are
  read-and-close, so each read reopens the session, and that throws once
  headers are sent. `xl()` and the query audit log both read the session,
  and PHP's 4 KB buffer flushes early in the table. **Fix:** one `ob_start()`
  at the top of the page (sunflower's fix). Sunflower's other workaround,
  `temp_skip_translations`, is **not** ported: it turns translation off for
  the page. This is worth an upstream issue; the real fix belongs in the
  session layer.
- **patient_statements, via two small core events and the module:**
  - `table.sql` in oe-module-cmsvt creates the table. The definition matches
    rel-800-sunflower's `sql/sunfower_migrations/001_patient_statements.sql`
    (indexes `idx_pid_date`, `idx_pid_encounter`), so installing the module
    on the production site is a no-op.
  - `PatientStatementSavedEvent` (core, dispatched after the statement is
    saved to documents) → module `PatientStatementLog::record()` inserts the
    row. Method is `mail` for print/download/PDF runs and `email` **only when
    the email was sent**. Production logged `email` for every statement in an
    email run, even undeliverable ones, which fed false "unpaid emails" into
    the badge. Nothing is recorded when "without updating invoices" is
    checked.
  - `StatementPrintSuggestionFilterEvent` (core, dispatched when listing
    Due Pt) → module `suggestPrint()`: 2+ emailed statements 21+ days old
    with no payment since → the box starts unchecked and a PRINT badge shows.
    Without the module, core never touches the table.
  - New enum `OpenEMR\Billing\StatementDeliveryMethod` (mail, email,
    portal, other), matching the column's enum.

**Not ported:**
- Sunflower's Modern layout changes (decision above) and its per-page
  `temp_skip_translations`.
- rel-800-sunflower's "save text to documents on PDF download": rel-840
  already saves text for this layout. (`set_time_limit(300)` was added back
  on 2026-09-29; see the rel-830-sunflower patches.)
- rel-830-sunflower's `sl_eob_process.php` (ea2c9ceffa: KanCare/March Vision
  Medicaid-secondary write-offs, CO-97 on 92015) and `sl_eob_invoice.php`
  (d96e076974: readable adjustment memo). These are Sunflower (Kansas)
  posting rules, not statements. **Stephen, 2026-09-28: not wanted for
  CMS.**
- rel-800-sunflower 75fa2a171c ("remove require portal email statements"):
  both sunflower branches still require portal access for email, so the
  port follows them. **Stephen, 2026-09-28: confirmed, keep the portal
  requirement.**

**Deployment:**
- **Each site's own `sites/<site>/statement.inc.php` needs this cluster's
  change.** The file in git only seeds new sites; the dev container's volume
  still had the stock copy. For sites that will use PDF Custom: copy the
  `make_statement()` branch (or the whole file if the site's copy is stock),
  set Statement Appearance = PDF Custom, and set Statement Logo to the
  letterhead **PNG** (612×792).
- Install/enable oe-module-cmsvt so `table.sql` runs. The production
  sunflower site already has the table.

Baseline: **reductions only**. sl_eob_search.php: `empty()` 36→35,
`$_REQUEST` 57→54, and `hipaa_allowemail` on `array|false` 2→1. The first
pass surfaced about 40 new-code findings in the legacy page (mixed row data,
`$_REQUEST`, `empty()`, `ez` offsets); all were fixed with typed locals and
narrowing, not baseline entries.

**Tooling gotcha:** `sites/` in the dev container is a Docker volume, not the
worktree, so the PHPStan run via `worktree exec` analyzed the volume's stock
statement.inc.php and reported 0 errors. The commit hook analyzes the staged
file and caught 8 findings in the new make_statement() branch; they were
fixed in code (typed narrowing, `CurrentRequest` for form_category). None of
clusters 1–9 touched `sites/`.

Checks: `php -l` and phpcs clean; isolated tests 5/5; full-codebase phpstan
0 errors (the commit hook's run, which sees the real file); make_statement()
exercised directly for no exclusion, below and above the minimum, and no pid.
Runtime on the cms-rel-840 stack (module registered, table created,
80 test patients, all removed afterwards; the volume's statement.inc.php
restored to stock):
- **Due Pt list:** all 80 rows render to `</html>`; 27 marked
  email-eligible, as seeded; exactly 1 PRINT badge, on the patient with 2
  unpaid emails, and that box is unchecked; the patient with 1 unpaid email
  is not flagged.
- **Download (appearance 2):** the custom text layout.
- **PDF download:** a valid PDF.
- Both runs saved documents and wrote `mail` rows with their document ids.
- **Email run** (no SMTP configured): one alert listing each patient, no
  `email` rows, no print.

### Cluster 10 follow-up — "without updating invoices" (2026-09-29)

Stephen asked whether rel-830-sunflower's "without updating invoices"
checkbox stops statements from being saved to documents. **It doesn't.** In
rel-830-sunflower the checkbox guards only the `form_encounter` statement
count (line 588); the email send and `createDocument()` run regardless.
Upstream rel-840 behaves the same. **rel-800-sunflower** wraps both the email
send and the document save in `if (empty($_REQUEST['form_without']))`;
rel-830-sunflower lost that guard. The cms-rel-840 port had followed
rel-830 (only the `patient_statements` row was skipped).

**Fixed (Stephen: yes), in sl_eob_search.php:** a typed `$withoutUpdate`
flag. With the box checked, a run produces only the print/download file:
- no emails;
- no Invoice documents;
- no `patient_statements` rows;
- no statement counts.

An email run with the box checked says "Nothing was emailed because
'without updating invoices' is checked" instead of falling through. The two
existing `$_REQUEST['form_without']` reads use the flag too.

Checks: phpcs clean; phpstan 0 errors, with reductions only (`empty()`
35→34, `$_REQUEST` 54→52). Runtime over HTTP (module registered, 3 test
patients, all removed afterwards; statement counts are the sum of
`form_encounter.stmt_count`):
- download **with** the box checked → documents 0, statement rows 0,
  counts 0;
- email **with** the box checked → nothing sent, the new message, all zero;
- download **without** the box → documents 3, rows 3, counts 3.

**rel-830-sunflower patches (2026-09-29, for Stephen to apply there):**
rel-830-sunflower also never writes `patient_statements`. It has only the
badge query; the insert stayed on rel-800-sunflower. Two `git am` patches
are in `../cms-porting/patches/`, built on rel-830-sunflower `a95025b472`
and checked with `git apply --check` in order:
1. `rel-830-sunflower-without-update.patch`: "without updating invoices"
   skips email and documents;
2. `rel-830-sunflower-patient-statements-writer.patch`: after each document
   save, insert the row, with `email` only when the send succeeded.

3. `rel-830-sunflower-email-confirm-without-update.patch` (added the same
   day, on top of 1 and 2, which Stephen had put in production): Email
   Selected no longer asks "Email N statement(s)?" when Without Update is
   checked. It says to uncheck it and doesn't submit. Both messages name
   the checkbox by its label, because `xl()` turns quotes into backticks.
   The same change is on cms-rel-840. There, the JavaScript was tested from
   the rendered page with node: checked → the alert and no submit;
   unchecked → the count confirm; none selected → "No statements selected."

4. `rel-830-sunflower-time-limit-double-click.patch` (on top of 1–3):
   - `set_time_limit(300)` at the start of every statement run.
     rel-800-sunflower's 5-minute limit (`83cc92e97e`) had been lost, and a
     large PDF run crashed production.
   - Guard against a second click while a run is under way: it asks first.
     A double submit double-counts, duplicates documents and
     `patient_statements` rows, and sends emails twice. The guard expires
     after 5 minutes, since downloads don't reload the page.
   - The same change is on cms-rel-840 (`8a8f8446dd`); cluster 10 had noted
     the time limit as "not ported". The guard's JavaScript was tested from
     the rendered page with node: first click submits; a second within 5
     minutes asks (Cancel blocks, OK submits); Search isn't guarded; after 5
     minutes it submits.

(Stephen found the layout change he saw was a globals setting, not code:
Statement Appearance wasn't set to PDF Custom.)

All four lint clean. Not run on a rel-830 stack.

### Cluster 11 — C8 Labs / HL7 (FHIR dropped) (2026-09-28)

Sources: cms-rel-701 / rebase-cms-rel-703 receive_hl7_results.inc.php
(90a0e34214 5fb849e0f5 e76dbdcf45 644076aed4 82d202a86c, and the site-2400
match), list_reports.php and single_order_results.php (stayHere, site 4800
per-lab maximum, site 2400 review filter, `LIMIT 500`, latest-encounter link).
f7f691ac06 (`nlist` guard) is already fixed upstream; dropped.
single_order_results.inc.php: already fixed upstream; dropped. All FHIR,
ProcedureService and swagger changes: dropped (decision 2026-09-23).

**Rebase-branch defect found:** rebase-cms-rel-703's order INSERT lost its
`external_id = ?` line (8 placeholders, 9 binds), so the visit number was
never stored. cms-rel-701, the production code, stores it. The port follows
701.

**Ported (two small core events plus module listeners):**
- `OpenEMR\Events\Orders\Hl7ResultsImportFilterEvent`, dispatched from
  receive_hl7_results.inc.php. Without a listener, import is exactly
  upstream. Module `Labs\LabOptions::applyToImport()`:
  - **Match on MRN only** (PID-3 = `patient_data.pubpid`) when
    `cmsvt_hl7_match_patient_by_mrn` is on (was `site_id == 2400`). No
    name/DOB/SSN ambiguity search: one pid matches, several ask the user,
    none returns 0 (as production).
  - **All CMS sites**, as production: results-only orders store the **visit
    number (PID-18)** as `external_id` instead of OBR-3; **no auto-created
    encounter**; **no provider notice** (labNotice). Differs from
    production: when PID-18 is empty, OBR-3 is kept rather than storing an
    empty value.
- `OpenEMR\Events\Orders\LabResultsListFilterEvent`, dispatched from
  list_reports.php. Module `applyToList()`:
  - `cmsvt_lab_results_per_lab`: default "Results Per Lab" (was site 4800 =
    50);
  - `cmsvt_lab_list_default_reviewed`: default filter = Reviewed (was site
    2400).
  - **Differs from production:** these are now *defaults*. Production forced
    them and ignored what the user picked.
- **Core, all sites, as production:**
  - **After signing in the results window**, the opener list refreshes and
    shows the list instead of dropping back to the start form, and the window
    closes. Upstream's refresh never ran the list query. The fix is keyed on
    the existing `form_external_refresh` field rather than production's new
    `stayHere` field.
  - The patient name opens the patient **in their latest encounter**, with
    one `LIMIT 1` query per patient. Production loaded every encounter per
    patient and used the old `top.RTop` frame.
  - The list query is capped at **500 rows**.
- **Upstream bug fixed:** the two poll-log lines had an operator-precedence
  bug (`"text" . $x ? a : b`), which dropped the "Lab matched account…" text
  and always printed the messages. Production's attempt kept the same
  precedence bug.

**Not ported:** `pd.dob` in the list query (unused); `poll_hl7_results(&$info
= [])` (no caller without arguments).

**Deployment:**
- site 2400: `cmsvt_hl7_match_patient_by_mrn` on and
  `cmsvt_lab_list_default_reviewed` on;
- site 4800: `cmsvt_lab_results_per_lab` = 50.

Baseline: **reductions only**. receive_hl7_results.inc.php: "ternary always
true" removed, `non-falsy-string . mixed` 28→26. single_order_results.php:
`js_escape` int removed. Four new-code findings (`$_POST`, `empty()`,
`attr_js` int, `pubpid` on mixed) were fixed in code.

Tooling: `/var/lib/docker` hit 99%, and PHPStan failed with "No space left".
PHPStan's own caches in the `openemr-cms-rel-840_phpstan` volume (1.4 GB:
result cache, nette container cache) were cleared; they regenerate. Free
space is back to ~1 GB. Unused images (~2.2 GB reclaimable) were left alone.

Checks: `php -l` and phpcs clean; full-codebase phpstan 0 errors. Runtime:
- **HL7:** one results-only ORU for a test patient, imported twice.
  - *Without the module:* matched by name/DOB, `external_id` = OBR-3
    (`ACC777`), 1 encounter created, 1 notice.
  - *With the module bootstrap* and MRN matching on (the message carries a
    different name): matched the existing patient by MRN, with no new
    patient; `external_id` = `V12345` (PID-18); `encounter_id` 0; no
    encounter; no notice.
- **Electronic Reports over HTTP** (module registered, test data):
  - results per lab defaults to 50;
  - a results-window refresh ran the list query and showed the test order,
    with the filter defaulting to Reviewed;
  - the patient link carries the latest encounter (990302 of 2).
- All test rows removed.

### Cluster 11b follow-up — lab effective date = specimen collection (2026-09-29)

Stephen asked whether the charge date could be exposed some other way than
overriding `effectiveDateTime` with the order's transmit date. Tracing the
HL7 import with a real radiology OBR showed where the date comes from:
- OBR-7 (observation/collection time) is stored in **both**
  `procedure_order.date_transmitted` (results-only orders) and
  `procedure_report.date_collected` (every imported report);
- OBR-22 is the report time.

For lab billing, the collection date is the date of service.

**Decision (Stephen: option 1):** lab Observation `effectiveDateTime` =
the report's **specimen collection time** (`procedure_report.date_collected`),
falling back to the report date. This conforms to US Core (the
clinically relevant time), and callers get the same value they get from
production's transmit date for imported results. For orders placed in
OpenEMR it is the real collection time, not the send time.
- `ProcedureService` returns the report's `date_collected`. The
  order-level `date_transmitted` plumbing from cluster 11b is removed; the
  `external_id` search stays.
- Not done: the separate transmit-date extension and `Observation.issued`
  (options 2 and 3).
- `effectiveDateTime` carries the server's local time with its offset (e.g.
  `2026-09-29T01:46:00-04:00`); `getLocalDateAsUTC()` does not convert. A
  caller that converts to UTC would move late-evening exams to the next day.
  Production behaves the same.

Checks: phpcs clean; phpstan 0 errors (no baseline changes). Runtime:
- a results-only ORU with Stephen's OBR timing (OBR-7 01:46, OBR-22
  05:17:56; fake patient and providers) imported through
  `receive_hl7_results()` with the module;
  - stored: `external_id` = V55501 (PID-18), `date_transmitted` and report
    `date_collected` = 01:46, `date_report` = 05:17:56;
  - `Observation?external_id=V55501` → 1 result, effective 01:46;
- a report with no collection date falls back to its report date.

Test rows removed.

### Cluster 12 — C1 837P claim generation (2026-09-28)

Sources: rebase-cms-rel-703 X125010837P.php / Claim.php /
GeneratorX12Direct.php, cross-checked against cms-rel-701; cms-rel-701
deltas 03839dd47a (Medicare ID), 6d65ca3bcb (53275 dropped from 2310C),
bb3852f040 (MOVEit rename, pay-to null guard, NDC list without 25169).
Dropped: 40d6f4fd9c and b3dc3cb5b1 (REF*F8 is already upstream), and the
rebase regressions ed739f3265 / 94bc254805 (the 2310C and 2330A N4 zips stay
as upstream sends them).

**Decisions (Stephen, 2026-09-28):**
- CMS payer rules live in the module behind a rules interface. General fixes
  go into core.
- Item 1 (bb3852f040): port all three parts.

**Design.** New core interface `OpenEMR\Billing\X12\Claim837PRules`. It
has 13 decision points: submitter ID, last seen date, referring provider,
CLIA, EPSDT form, 2310C, other payer included, other payer ID, 2420A, NDC,
CAS reason, extra CAS, warnings. `DefaultClaim837PRules` reproduces stock
rel-840. `Claim837PRulesEvent` is dispatched once per claim and
oe-module-cmsvt answers it with `Billing\CmsvtClaimRules`. Without the
module, the generator's output is stock plus the general fixes below (checked
by diff).

**General fixes (core, every site):**
- **Pay-to address (2010AB)** from the billing facility's mailing address,
  when one is set: `NM1*87*2`, then N3 and N4. The stock loop was never
  emitted. **Differs from production:** street 2 goes in N302 rather than
  being appended to N301 with a space.
- **Medicare IDs** have dashes and spaces stripped (03839dd47a), with a log
  line.
- **Inpatient (POS 21):** the onset date is no longer sent twice (as DTP*431
  and DTP*435).
- **Missing EPSDT code** is logged.
- **Secondary claim adjustments (Claim::payerAdjustments):**
  - only adjustments posted at the prior payer's level are reported;
  - an 835 `copay:` is reported as **PR-3**, not mislabeled as PR-2
    coinsurance;
  - patient responsibility the payer didn't break down goes out as PR-3,
    instead of being guessed into deductible or coinsurance.
- **X12 amounts** in SVD02 and CAS are sent without leading zeros, as
  production does.
- An upstream precedence bug (`chg + adj ?? ''`) raised "Undefined array key
  adj" on every secondary claim; fixed.
- The GeneratorX12Direct log line includes the claim id.
- New events `ClaimProviderFilterEvent` (in the Claim constructor) and
  `X12RemoteFilenameFilterEvent` (before the SFTP upload).

**CMS rules (module `CmsvtClaimRules`):**
- **Vermont Medicaid carrier codes** for the other payer, in 2330B NM109 and
  SVD01: MB → MDB; BCBS VT → H6 / MDB / BV / EE by payer name or policy
  prefix; 12 mapped payer IDs; 60054 → group or 92; otherwise the first
  three characters of the group number, else a warning.
- **Other payers left out** when both policies are Medicare, or when billing
  VA Community Care.
- **Medicare copay sent as coinsurance** to Vermont Medicaid (CAS PR-3 →
  PR-2).
- **Tertiary claims:** the primary's paid + adjusted amount is reported as
  CAS OA-23 on the secondary's lines.
- **CLIA** sent for Medicare and for the waived tests 81002, 81025, 87804,
  87880 and 87428.
- **EPSDT** sent as NTE*ADD instead of CRC.
- **2310C** sent whenever the service facility NPI differs from the billing
  NPI (no POS 12 exception).
- **2420A** always sent for payer 14165.
- **1000A NM109** = the X12 partner's sender ID (third-party submitter).
- **Routine foot care** for billing NPIs in `cmsvt_claim_routine_foot_care_npis`
  (was hardcoded 1134268188):
  - the onset date goes out as DTP*304;
  - the referring provider is sent as supervisor when none is chosen;
  - Medicare requires the 2310A referrer for foot care and foot x-rays;
  - lines without Q7/Q8/Q9 get a warning.
- **Warnings:** POS 01, payer ID 99999, VA CCN without prior auth, and a
  closed service facility (`cmsvt_claim_closed_facility_npis`, was site 1300
  with an NPI).
- **Per-site settings:**
  - `cmsvt_claim_ndc_skip_payer_ids` (was site 1500: 87726, 39026, TREST,
    PAMCD; **25169 is removed**, per bb3852f040);
  - `cmsvt_claim_rendering_provider_id` (was site 1500 = user 6, "incident
    to").
- **MOVEit:** uploads to moveit.bcbsvt.com are named `007111NN.x12`.
  **Differs from production:** only the remote name changes. Production
  renamed the local file too.

**Differs from production (bugs not carried over):**
- ⚠ **Secondary-claim adjustments: production sends no CO/OA adjustments.**
  Production's filter was `$value['plv'] === $ins`, and in 7.0.1 `plv` comes
  back from the database as a string, so the strict comparison never matches.
  Production's secondary claims therefore carry only PR lines, plus the
  tertiary OA-23. The port keeps what the filter was meant to do
  (adjustments posted at that payer level), so claims now include CO-45 and
  similar lines. **Stephen to confirm** (see the Phase 3 837P diff).
  **Decided 2026-09-29: match production** (see the follow-up below).
- **Skipping an NDC no longer skips the rest of the line.** Production's
  site-1500 NDC skip used `continue`, which also dropped 2420A and 2430 for
  that line.
- **A payer left out of 2330 is also left out of 2430 (SVD).** Production
  still sent SVD for it.
- **The 14165 rule tests the payer being billed.** Production tested
  `payerID($ins - 1)`, a leftover loop index.
- **A parsed `copay:` amount is kept.** Production then overwrote it with
  the total patient responsibility.
- **EPSDT as NTE*ADD can duplicate a box 19 NTE*ADD.** Production had the
  same behavior.

**Checks:**
- `php -l` and phpcs clean; full-codebase phpstan 0 errors.
- New isolated test `CmsvtClaimRulesTest`: 18 tests covering carrier codes,
  skips, PR-3→PR-2, OA-23, foot care, CLIA, NDC, 2310C, submitter and
  warnings.
- **837P runtime diff** on a test encounter (podiatry, Medicare primary,
  Vermont Medicaid secondary), generated three ways: stock rel-840 (a copy
  of its generator and Claim), the port without the module, and the port
  with the module.
  - **Primary claim:**
    - *port vs stock:* only 2010AB and the stripped Medicare ID;
    - *module:* DTP*304 instead of 431, NM1*DQ (the referrer), and the
      closed-facility and Q-modifier warnings.
  - **Secondary claim:**
    - *port vs stock:* only 2010AB;
    - *module:* 2330B and SVD `MDB` instead of 14512, REF*X4 for 81002, and
      DTP*304 with NM1*DQ; CO-45 and PR lines appear in all three.
  - **Copay variant:** stock sends PR-2 (mislabeled), the port PR-3, the
    module PR-2 (Vermont Medicaid).
  - **Bill-under-provider:** 2310B is the chosen provider, and the lines
    keep 2420A.
  - **MOVEit:** a random `007111NN.x12` name for that host; other hosts
    unchanged.
- The harness lives outside the repo in `../cms-porting/c12-harness/`, as a
  starting point for Phase 3. All test rows are removed.

**Deployment:**
- site 1500: `cmsvt_claim_ndc_skip_payer_ids` = 87726, 39026, TREST, PAMCD,
  and `cmsvt_claim_rendering_provider_id` = 6;
- site 1300: `cmsvt_claim_closed_facility_npis` = the closed facility's NPI;
- the podiatry site: `cmsvt_claim_routine_foot_care_npis` = 1134268188.

### Cluster 11b — FHIR lab Observation search by external ID (2026-09-28)

Sources: cms-rel-701 FhirObservationService.php,
FhirObservationLaboratoryService.php, MappedServiceCodeTrait.php,
ProcedureService.php and swagger/openemr-api.yaml (the `external_id` parts).

**Decision (Stephen, 2026-09-28):** this partly reverses the 2026-09-23
"drop all FHIR" decision. Custom API callers search lab Observations by
the order's external ID and need the order date. **Port the `external_id`
search, and set `effectiveDateTime` from the order's transmit date (option
1: production behavior).** Still dropped: removing the SocialHistory and
Vitals Observation services.

**Ported (core; no module hook exists for FHIR search parameters):**
- `ProcedureService` selects `procedure_order.external_id` and
  `date_transmitted` (as `order_external_id` / `order_date_transmitted`) and
  returns them on each procedure as `external_id` / `date_transmitted`.
- `FhirObservationLaboratoryService` has a new `external_id` token search
  parameter mapped to `order_external_id`.
- **Lab Observation `effectiveDateTime` is the order's transmit date**
  (production behavior), falling back to the report date when the order has
  none. Production used the transmit date unconditionally, so an order
  without one would have produced an invalid date. **Superseded
  2026-09-29:** the specimen collection time is used instead (see the
  follow-up below).
- `FhirObservationService` accepts `external_id` and narrows the search to
  the laboratory service. That replaces production's
  `getServiceForExternalId()` trait method and `supportsExternalId()`.
- The OpenAPI attribute on `FhirObservationRestController` is updated and
  `swagger/openemr-api.yaml` regenerated (`openemr-cmd build-api-docs`); only
  the new parameter changed. The CapabilityStatement picks it up from the
  search parameter definitions.
- The value comes from cluster 11: results-only orders store the lab's visit
  number (PID-18) as `external_id`.

**Effect on other API clients:** the new parameter is opt-in. Lab
Observation `effectiveDateTime` changes from the report date to the order's
transmit date for **all** clients, as production has it.

Baseline: **reductions only**. FhirObservationLaboratoryService.php: 7
entries removed and 4 lowered, after narrowing the per-result record to an
array.

Checks: `php -l` and phpcs clean; full-codebase phpstan 0 errors. Runtime,
through `FhirObservationService::getAll()` with three test lab orders:
- `external_id=V12345` → 1 Observation (value 7.5), effective 2026-06-01T09:00
  (transmit date, not the 06-03 report date);
- `external_id=NOPE` → 0;
- patient + category=laboratory → all 3; the order without a transmit date
  falls back to its report date.

Test rows removed. Not exercised over HTTP with an OAuth token; the REST
controller passes query parameters straight to this service.

### Cluster 12 follow-up — secondary claim adjustments match production (2026-09-29)

**Decision (Stephen):** match production on secondary claims.

**What production sends.** Its `plv === $ins` filter never matches (string
vs int), so on secondary claims:
- no posted CO/OA adjustments go out;
- no itemized PR (deductible/coinsurance/copay parsed from the 835);
- each line's whole remaining patient responsibility goes out as PR-3 (PR-2
  when Vermont Medicaid is billed after Medicare);
- the prior payer's adjustment total is 0, which also makes the tertiary
  OA-23 the primary's paid amount only.

**Ported as:**
- a new `Claim837PRules::reportsPriorPayerAdjustments()`, default true;
- `Claim::setReportsPriorPayerAdjustments()`, set by the generator, which
  skips posted adjustments in `payerAdjustments()` (the per-line date is
  still taken);
- `CmsvtClaimRules` returns false.

The stock path is unchanged: it still reports posted adjustments, filtered
to the prior payer's level.

Checks: phpcs clean; phpstan 0 errors (no baseline change);
`CmsvtClaimRulesTest` 18/18 (new assertion). **837P runtime** (cluster 12
harness, secondary and copay variants):
- *without the module:* CAS CO*45*15.00 plus PR*2 / PR*3 (the posted
  adjustments);
- *with the module:* only CAS PR*2*9.00 and PR*2*7.00 per line (remaining
  patient responsibility, PR-3 sent as PR-2 for Medicaid after Medicare);
  no CO-45; AMT*D unchanged.

## Pre-deployment checks (2026-10-01)

- **Full isolated suite:** 5,422 tests, 0 failures (2 warnings and 1 notice
  from untouched upstream code: the immunization validator and a test key's
  file permissions). The first run had 1 failure from the port:
  TwigTemplateCompilationTest didn't list oe-module-cmsvt's templates
  folder, so the cluster 8 Press Ganey template failed to compile. Fixed by
  adding the folder. Cluster 8 had only run the formatter test.
- **Deployment checklist:** `DEPLOYMENT.md`, synced alongside this file.
- **Server:** rel-840 needs PHP 8.3+ and MariaDB 10.11+ (or MySQL 8.4+).
  Stephen chose Docker.
- **Merged upstream rel-840** (#14283, #14299: acceptance-test syncs).
- **Release image from the fork:** `docker/release/Dockerfile` gets an
  `OPENEMR_GIT` build argument (default unchanged), so the stock release
  image builds from `stephenwaite/openemr` `cms-rel-840`. It matches
  upstream #13709 (open, by luissantosHCIT): first named `OPENEMR_REPO`
  here, then renamed and moved inside the `openemr-source` stage to match
  it. On 2026-10-01 #13709 was brought up to date with master, with its
  binary-README docs replaced by Dockerfile comments, on the fork branch
  `pr-13709-openemr-git` (`cbbf483898`), for Stephen to push to the PR as a
  maintainer edit. Its isolated tests then failed on a master breakage
  (#14240 left the `variable.undefined` baseline cap one too high), fixed by
  #14339; the branch was brought up to date again (`6d90e9a574`).
  **#13709 merged 2026-10-02.** cms-rel-840's Dockerfile already matches it
  (only the default branch differs). Its startup script already runs
  vendor hooks (`postconfig`, `postupgrade`, `prelaunch`, `tooearly`; see
  upstream #13953, docs on master), which will carry the per-site
  configuration.

## Docker deployment kit (2026-10-01)

`contrib/cms/docker/` (README, compose file, hooks, sample config):
- **How the release image upgrades.** `docker/release/openemr.sh` runs
  `fsupgrade-N.sh` scripts while `sites/default/docker-version` is behind
  the image's marker (15 for rel-840). Each one runs `sql_upgrade.php` for
  every site from one release: 6 is from 7.0.1, through 15, which is from
  8.4.0. A migrated 7.0.1 install therefore gets marker `5`. The
  `postupgrade` hook fires only at the end of that path, not on a
  schema-only migration (`check_schema_upgrade`) or a restart.
- **`prelaunch/10-cms-site-files`:** copies `config/sites/<site>/…`
  (existing sites only, owned by apache), `config/code/…` and
  `config/php/*.ini` on every start.
- **`postupgrade/10-cms-site-settings`:** applies the module's `table.sql`,
  `config/sql/all-sites.sql` and `config/sql/<site>.sql` to each configured
  site, once each. A checksum in `sites/<site>/cms-applied/` records it, so
  later upgrades don't overwrite settings changed in OpenEMR. It can be run
  by hand with `docker compose exec`. `cms-apply-sql.php` connects with the
  site's `sqlconf.php` (no TLS).
- **Sample SQL:** per-site files from DEPLOYMENT.md section 3.
  `all-sites.sql` registers the module, turns claim balancing off and sets
  the drug units.
- **Tested in the cms-rel-840 dev container** (bash, run-parts, PHP 8.5):
  - run-parts picks up both scripts and skips the helper;
  - prelaunch copied a site file, a code file and a PHP override, and
    skipped a missing site;
  - postupgrade applied all three files, skipped them on a second run, and
    re-applied only the edited file: `force_claim_balancing` 0, site
    setting 1, module registered and enabled, J2777 units 60.
  - **Bug found and fixed:** the `modules` insert failed in strict mode
    (`sql_version`/`acl_version` have no default). The hook stopped and
    reported it, as intended.
  - Everything restored afterwards.
- shellcheck (enable=all), phpcs and phpstan clean. The helper reads its
  paths from the environment, since `$_SERVER` is forbidden.
- **Not tested:** a full image build and a real migration; both need the
  server.

## Upstream #14330 cherry-picked (2026-10-01)

Stephen's upstream PR #14330 (merged to master as bc733b3dbf, "default units
and NDC for injection codes from inventory") is cherry-picked as
`8f4e2cf0fc`. It replaces the "expose Units on Administration → Codes" idea:
units and NDC now come from Inventory.
- **Schema:** three `drugs` columns (`billing_units`, `ndc_uom`,
  `ndc_quantity`). On cms-rel-840 they're added by
  `8_4_0-to-8_4_1_upgrade.sql` (master: `8_4_1-to-8_5_0`), and
  `v_database` stays 543 so a future 8.4.x bump can't be mistaken for
  applied. Master's identical `#IfMissingColumn` blocks skip at 8.5.0.
  Verified: OpenEMR's upgrade parser added the three columns on the dev DB.
- **Pricing change:** a newly picked code's fee is now price × units.
  Production used the price as the whole line. Codes with forced units
  (C9257, Q5124, J0178, J0177, J2777) need per-unit prices before go-live:
  DEPLOYMENT.md section 6 has the review query and the conversion.
- **Checks:** phpcs and full phpstan clean; `HcpcsDrugDefaultsTest` 8/8;
  `FeeSheetUnitPriceTest` + `HcpcsDrugDefaultsServiceTest` 10/10;
  `FeeSheetClassesTest` 13/13.
- **Cluster 7's deployment step** ("set Units in Administration → Codes")
  was wrong; that screen has no Units field. It's superseded by
  DEPLOYMENT.md section 6.

## Docker kit correction (2026-10-02)

Reading `openemr.sh`'s main flow more closely, while Stephen hit "no
sqlconf.php" for `default` on the site-1100 server:
- **The marker-`5` step was wrong.** `run_upgrade` (the `fsupgrade-N` path)
  needs `${OE_ROOT}/docker-version`, a code marker that lives in the
  container's code tree, isn't in the image, and is written only after
  first-time setup. A fresh container never takes that path. Database
  upgrades go through `check_schema_upgrade` instead: per site, from that
  database's own `version` row (`sql_upgrade.php --from=7.0.1`), skipping
  unconfigured sites.
- **So `postupgrade` effectively never fires on a new image.** The
  settings script moved to `prelaunch/20-cms-site-settings`; its per-file
  checksums make running it on every start safe. Re-tested via run-parts
  in the dev container: first start applied, second skipped.
- **A missing `sites/default` is only restored in swarm mode**, and
  `sites/default/sqlconf.php` is how the image decides OpenEMR is
  installed. On a server where `default` isn't a real site (site 1100's
  server), the container gets a stock `default` copied from the image. It's
  configured as a new empty site, using `MYSQL_DATABASE`/`MYSQL_USER`,
  which the compose file now sets to `openemr_default` so they can't
  collide with a migrated site's database or user.
- README, compose file, SQL headers and DEPLOYMENT.md section 0 updated.
- Worth raising upstream: docker/HOOKS.md says `postupgrade` "is run after
  an upgrade process completes", but a schema upgrade on a new image never
  triggers it.

## Statements match production's library layout (2026-10-02)

Correction from Stephen: production didn't print from the sites'
`statement.inc.php`. cms-rel-701's `sl_eob_search.php` requires
`library/statement.inc.php`, whose `create_statement()` is the CMS layout
for every site (any `statement_appearance` other than 1). Cluster 10 had
ported the rel-830-sunflower wording, which differed in four places.
`CustomPdfStatementText` now matches `create_statement()`:
- insurance payment: `<source, 40 chars> <method>`, with no "Paid" prefix;
- adjustment: `Adj <reason> <method>`, where "Adjust code NN" (ERA posting)
  becomes the first 41 characters of the CARC description
  (`BillingUtilities::CLAIM_ADJUSTMENT_REASON_CODES`). An unknown code keeps
  the raw text; production would have printed an empty reason;
- zero-amount reason: `Note <reason, 40 chars> <method>`;
- adjustment and note lines are `%-54s %8s`, and descriptions keep
  production's trailing space (visible only when the text overflows);
- the name is trimmed before it's upper-cased.

Checked byte for byte against cms-rel-701's `create_statement()` with
`scripts/statement-compare-701.php` (it needs `git show
origin/cms-rel-701:library/statement.inc.php > tmp/stmt701.php` next to
it; run it in the container). Six cases (payment, CARC adjustment, note,
patient payments, long payer name, 40 lines split across pages), each at
dun count 0 and 1: all 12 identical. 10 isolated tests.

Deployment: `all-sites.sql` now sets `statement_appearance = 2` on every
site. Every site needs cms-rel-840's `sites/default/statement.inc.php`,
because rel-840 loads the site copy and the old copies lack the PDF Custom
branch. Sites keep production's `statement_logo` and letterhead PNG
(production drew it the same way, full page at 612×792). Kit READMEs,
DRY-RUN.md and DEPLOYMENT.md section 4 are updated.

## Dry run, site 1100 (2026-10-03)

- `x12_partners.x12_submitter_id` was `tinyint(1)` on 1100; altered to
  `smallint(6)` before the first start (DEPLOYMENT.md section 1 confirmed).
- Schema upgrade 7.0.1 → 8.4 (revision 487 → 543): about 61 s, with no
  failed statements.
- A comment-only `config/sql/1100.sql` stopped the start ("Query was
  empty"). Fixed in the helper (bd7c7e8f77): files with nothing to apply
  are skipped.
- Patient page: "SQL Statement failed on preparation" on the
  `contact_relation` query. Cause: the database was created without a
  collation, so MariaDB 12 gave the upgrade's new tables
  `utf8mb4_uca1400_ai_ci` against the dump's `utf8mb4_general_ci`. 1100
  had 28 such tables, next to production's 216 `utf8mb3_general_ci`, 27
  `utf8mb4_general_ci` and 16 `latin1_swedish_ci` (those compare fine across
  character sets and were left alone). Fix: convert the 28 to
  `utf8mb4_general_ci`. Step 5 now creates the database with
  `COLLATE utf8mb4_general_ci` (DEPLOYMENT.md section 1).
- Statement Appearance came in as plain text: the kit copy predated
  0ab443a0a2. Once it was set to PDF Custom, statements printed correctly on
  the letterhead (image 2db651390f, so still the rel-830-sunflower wording).
- Documents, fee sheet and claims checked by Stephen: good.
- The top-bar patient search doesn't match the internal pid (it searches the
  DEM layout fields only); upstream behavior, unchanged from 7.0.1.
- `x12_submitter_id` `tinyint(1)` traced: upstream #6456 (2023-05-13)
  added it as tinyint with a syntax error; #6459 (2023-05-17) fixed both
  and changed it to `smallint(6)` before any release. cms-rel-701 took #6456
  and fixed the syntax itself, but kept tinyint. Not an upstream bug in any
  release. `all-sites.sql` now widens it on every site. 1400: tinyint, all
  values 11 or NULL.
- 1400 started without `config/sites/1400/statement.inc.php`, so it kept
  its 7.0.1 copy. Every site should have the same file (Stephen), so the
  site-files hook now copies the image's (`/swarm-pieces/sites/default/`)
  into every configured site at each start; a `config/sites/<site>/` copy
  still wins.
- **Lab polling ignores `procedure_providers.active`**: `poll_hl7_results()`
  loops over every provider, and its SFTP fetch deletes each processed file
  from the lab's server. A dry run clicking Process Results would have taken
  production's results. The switch-off SQL now sets every provider to
  protocol `FS` with local paths. (Upstream behavior; worth raising: an
  inactive provider probably shouldn't be polled.)
- Production's root crontab runs `execute_background_services.php <site>`
  for 2400 (every 15 min), 1100, 1400, 1500, 4800 and 5200 (6×/day).
  rel-840's CLI refuses root and the container has no OpenEMR cron:
  DEPLOYMENT.md section 7 moves them to host cron calling `bin/console
  background:services run --site=<site>` as apache.
- **Upstream multisite bug, fixed (325cabacf7):** since 8.2.0 (#11801),
  run-all-due spawns `bin/console background:services run --name=<svc>`
  without `--site`, so every non-default site's services ran against
  `default`'s database (browser polling, the legacy CLI script and the
  console command alike). And `background:services` rejected `--site`. The
  runner now passes its site (basename of OE_SITE_DIR) to the spawner,
  which adds `--site`; the command declares `--site` and its crontab lines
  include it. Tests: spawner forwards `--site` only when set; command
  accepts it and prints it in crontab lines. Checked end to end in the dev
  container with a copied second site. Upstream PR: branch
  `fix/background-services-multisite`, body in
  `~/git/pr-fix-background-services-multisite.md`.
- **Multisite's future upstream:** RFC #11387 proposes deprecating and
  eventually removing multi-site, and asks production multi-site users to
  comment (telemetry in #11370). It's an idea, not a commitment yet.
  CMS runs several sites per server. If multisite is deprecated, the
  alternative is one container (or stack) per site, which the Docker kit
  could grow into: each site already has its own database, and the kit's
  hooks work per site.
- No production server has a real `default` site (Stephen), so the
  `site_id != 'default'` visit-details check never applied. Removed
  `config/sql/default.sql` and its DEPLOYMENT.md row; the global stays,
  off. Docker still needs a configured `sites/default` (the stock one).
- DRY-RUN.md gained the database `--wait`, the switch-off SQL (Stephen's
  usual list, keeping oe-module-cmsvt active), and the note that sites keep
  their production letterhead.

## Site-ID and user-name checks → per-site globals (running list)

| Production check | Where | Replacement | Cluster |
|---|---|---|---|
| `site_id == '200'`: fixed eligibility provider | EDI270.php | `cmsvt_elig_provider_id`, `cmsvt_elig_receiver_name` | 3 |
| `site_id == '200'`: Billing Manager default = last 2 months (others: all unbilled) | billing_report.php | `cmsvt_billing_manager_dos_months` (200 → 2, others → 0) | 4 |
| `site_id != '1400'`: show Export to Collections | collections_report.php | `cmsvt_collections_hide_agency_export` (1400 → on) | 4 |
| `site_id != 'default'`: show visit details in the encounter report | newpatient/report.php | `cmsvt_encounter_report_hide_visit_details` (no site: `default` isn't real anywhere) | 5 |
| `authUser == '<records-review-user>'` (records-review user): 4 checks | demographics.php, stats.php, edit_globals.php | **not a global:** the reviewer's ACL group (see cluster 5) | 5 |
| primary business entity taxonomy `213E00000X`: "Date Last Seen" label | newpatient encounter form | `cmsvt_encounter_date_last_seen` (podiatry sites → on) | 6 |
| `site_id == '2400'`: match lab results by MRN only | receive_hl7_results.inc.php | `cmsvt_hl7_match_patient_by_mrn` (2400 → on) | 11 |
| `site_id == '2400'`: Electronic Reports filter forced to Reviewed | list_reports.php | `cmsvt_lab_list_default_reviewed` (2400 → on; now a default) | 11 |
| `site_id == '4800'`: 50 results per lab | list_reports.php | `cmsvt_lab_results_per_lab` (4800 → 50; now a default) | 11 |
| `site_id == '1500'`: payers without NDCs | X125010837P.php | `cmsvt_claim_ndc_skip_payer_ids` (1500 → 87726, 39026, TREST, PAMCD) | 12 |
| `site_id == '1500'`: bill under user 6 | Claim.php | `cmsvt_claim_rendering_provider_id` (1500 → 6) | 12 |
| `site_id == '1300'` + facility NPI: closed facility warning | X125010837P.php | `cmsvt_claim_closed_facility_npis` (1300 → that NPI) | 12 |
| billing NPI `1134268188`: podiatry claim rules | X125010837P.php | `cmsvt_claim_routine_foot_care_npis` (podiatry site → 1134268188) | 12 |
