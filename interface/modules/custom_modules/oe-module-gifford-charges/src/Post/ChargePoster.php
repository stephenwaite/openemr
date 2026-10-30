<?php

/**
 * Posts staged Gifford charge lines: patient, policies, encounter and billing lines,
 * holding every line that can't be posted yet with the reason.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ChargeRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\EncounterRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\ParsedRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PatientRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Reader\RawRow;
use OpenEMR\Common\Database\QueryUtils;
use Psr\Clock\ClockInterface;

/**
 * Every run reads all lines not yet finished, staged or held, and parses their raw rows
 * again, so a hold clears by itself once its cause is fixed (a payer mapped, a provider
 * or price added). Lines are posted in groups of one patient, visit and date of
 * service, each group in one transaction as one encounter.
 *
 * A line is held when:
 * - the file's row has problems (the parser's);
 * - a payer mnemonic isn't mapped to an insurance company, the rendering NPI isn't a
 *   provider on the site, the visit's lines disagree on place of service (or with the
 *   encounter already posted), or the visit number is too long for the encounter;
 * - it has no price, or Gifford sent TC;
 * - an earlier file already posted its accession as a different charge (a corrected
 *   re-send), until a person releases it;
 * - the patient already has the same code that day (posted from this file, or from another
 *   file on another accession, or posting in this run) on the same body site, and the line
 *   has no 76 or 77: a possible repeat procedure, until a person posts it with 76 or 77 or
 *   skips it. The same code RT and LT (or on two fingers) is two procedures, not a repeat;
 * - it is an add-on code (AddOnCodes) and none of its primary codes is billed for the
 *   patient that day, posted or posting with it.
 * A line identical to a charge posted from an earlier file on the same accession (the file
 * sent again) is marked duplicate.
 *
 * The patient's address, phone, email, guarantor and employer follow the file whose date of
 * service is the newest posted for them (PatientDetailsWriter).
 */
final readonly class ChargePoster
{
    /** form_encounter.external_id's width. */
    private const VISIT_NUMBER_MAX = 20;

    /** Modifiers that say where on the body a procedure was done (50, both sides, is RT + LT). */
    private const SITE_MODIFIERS = [
        'RT', 'LT', 'E1', 'E2', 'E3', 'E4', 'LC', 'LD', 'LM', 'RC', 'RI',
        'FA', 'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9',
        'TA', 'T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9',
    ];

    public function __construct(
        private ChargeLineRepository $lines,
        private ChargeRowParser $parser,
        private PayerMap $payers,
        private PriceBook $prices,
        private SiteWriter $site,
        private ClockInterface $clock,
        private PatientDetailsWriter $details = new PatientDetailsWriter(),
        private AddOnCodes $addOns = new AddOnCodes(),
    ) {
    }

    public function run(PostSettings $settings): PostResult
    {
        $result = new PostResult();
        $now = $this->clock->now();

        /** @var array<string, list<array{line: PendingLine, parsed: ParsedRow}>> $groups */
        $groups = [];
        foreach ($this->lines->pendingLines() as $line) {
            $parsed = $this->parser->parse($line->row);
            if (!$parsed->isImportable() || $parsed->charge === null || $parsed->patient === null || $parsed->encounter === null) {
                $this->lines->hold($line->id, $parsed->problems, $now);
                $result->held++;
                continue;
            }
            $key = implode("\x1F", [$parsed->patient->mrn, $parsed->encounter->visitNumber, $parsed->charge->dateOfService->format('Y-m-d')]);
            $groups[$key][] = ['line' => $line, 'parsed' => $parsed];
        }

        foreach ($groups as $group) {
            $this->postGroup($group, $settings, $now, $result);
        }

        return $result;
    }

    /**
     * @param list<array{line: PendingLine, parsed: ParsedRow}> $group
     */
    private function postGroup(array $group, PostSettings $settings, \DateTimeImmutable $now, PostResult $result): void
    {
        if ($group === []) {
            return;
        }
        $first = $group[0]['parsed'];
        $patient = self::patientOf($first);
        $visit = self::encounterOf($first);
        $dateOfService = self::chargeOf($first)->dateOfService;

        // Problems that hold every line of the visit.
        $visitProblems = [];
        $companies = [];
        foreach ($first->policies as $policy) {
            $companyId = $this->payers->insuranceCompanyId($policy->payerMnemonic);
            if ($companyId === null) {
                $visitProblems[] = sprintf('payer %s (%s) is not mapped to an insurance company', $policy->payerMnemonic, $policy->payerName);
                continue;
            }
            $companies[$policy->rank] = $companyId;
        }
        if (strlen($visit->visitNumber) > self::VISIT_NUMBER_MAX) {
            $visitProblems[] = sprintf('encounter_num is longer than %d characters', self::VISIT_NUMBER_MAX);
        }
        $places = array_values(array_unique(array_filter(
            array_map(static fn (array $g): ?string => self::encounterOf($g['parsed'])->placeOfService, $group),
            static fn (?string $pos): bool => $pos !== null
        )));
        if (count($places) > 1) {
            $visitProblems[] = 'the visit\'s lines have different places of service: ' . implode(', ', $places);
        }
        $placeOfService = $places[0] ?? null;

        $pid = $this->site->findPatientByMrn($patient->mrn);
        $existing = $pid === null ? null : $this->site->findEncounter($pid, $visit->visitNumber, $dateOfService);
        if ($existing !== null && $placeOfService !== null && $existing['pos_code'] !== $placeOfService) {
            $visitProblems[] = sprintf('place of service %s differs from the encounter already posted (%s)', $placeOfService, $existing['pos_code'] ?? 'none');
        }

        // Each line's own checks.
        $ready = [];
        foreach ($group as ['line' => $line, 'parsed' => $parsed]) {
            $charge = self::chargeOf($parsed);
            $problems = $visitProblems;
            $providerId = $this->site->findRenderingProvider(self::encounterOf($parsed)->renderingNpi);
            if ($providerId === null) {
                $problems[] = sprintf('rendering NPI %s is not a provider on this site', self::encounterOf($parsed)->renderingNpi);
            }
            // Gifford sending a file again: the same charge on the same accession, posted
            // from an earlier file. Lines of one file are checked as repeats below.
            $duplicateOf = null;
            $postedAs = [];
            foreach ($this->lines->postedEarlier($charge->accession, $line->batchId, $line->row->line) as $posted) {
                if ($posted['same_file']) {
                    continue;
                }
                $earlier = $this->parser->parse(new RawRow(0, $posted['raw']))->charge;
                if ($earlier === null) {
                    continue;
                }
                if (self::sameCharge($earlier, $charge)) {
                    $duplicateOf = $posted['line_id'];
                    break;
                }
                $postedAs[] = sprintf('%s%s x%d', $earlier->code, $earlier->modifiers === [] ? '' : '-' . implode(':', $earlier->modifiers), $earlier->units);
            }
            if ($duplicateOf !== null) {
                $this->lines->markDuplicate($line->id, $duplicateOf, $now);
                $result->duplicates++;
                continue;
            }
            if ($postedAs !== [] && !$line->released) {
                $problems[] = sprintf(
                    'corrected re-send? accession %s was posted from an earlier file as %s; post this line too, or skip it',
                    $charge->accession,
                    implode(', ', $postedAs)
                );
            }

            // The same procedure again for the patient that day, posted or posting now: a
            // repeat needs modifier 76 or 77, which only a person can tell from a duplicate.
            $repeatOf = $this->repeatOf($line, $charge, $ready, self::patientOf($parsed)->mrn);
            if ($repeatOf !== []) {
                $problems[] = sprintf(
                    'repeat procedure? %s %s is also on accession %s for this patient on %s; post it with modifier 76 (same physician) or 77 (another physician), or skip it if it is a duplicate',
                    $charge->codeType,
                    $charge->code,
                    implode(', ', $repeatOf),
                    $charge->dateOfService->format('Y-m-d')
                );
            }
            if ($line->addedModifier !== '') {
                $charge = new ChargeRecord(
                    $charge->dateOfService,
                    $charge->codeType,
                    $charge->code,
                    [...$charge->modifiers, $line->addedModifier],
                    $charge->units,
                    $charge->accession,
                    $charge->department,
                    $charge->clinicalNote,
                    $charge->comment,
                );
            }

            $priced = null;
            try {
                $priced = $this->prices->price($charge);
            } catch (PostProblem $e) {
                $problems[] = $e->getMessage();
            }
            if ($problems !== [] || $priced === null || $providerId === null) {
                $this->lines->hold($line->id, $problems, $now);
                $result->held++;
                continue;
            }
            $ready[] = ['line' => $line, 'charge' => $charge, 'priced' => $priced, 'providerId' => $providerId];
        }

        // An add-on is billed only beside its primary procedure: hold one until a parent is
        // posted for the patient that day, or posts with it now.
        $readyCodes = array_map(static fn (array $r): string => strtoupper($r['charge']->code), $ready);
        foreach ($ready as $i => $r) {
            $parents = $this->addOns->parentsOf($r['charge']->code);
            if ($parents === [] || array_intersect($parents, $readyCodes) !== [] || $this->lines->hasPostedCode($patient->mrn, $dateOfService, $parents)) {
                continue;
            }
            $this->lines->hold($r['line']->id, [sprintf(
                'add-on code %s is billed with %s, which is not billed for this patient on %s; it posts once that is, or skip it',
                $r['charge']->code,
                implode(' or ', $parents),
                $dateOfService->format('Y-m-d')
            )], $now);
            $result->held++;
            unset($ready[$i]);
        }
        $ready = array_values($ready);
        if ($ready === []) {
            return;
        }
        // Only the newest visit's file speaks for the patient's details; an older file
        // arriving late doesn't undo them.
        $latest = $pid === null ? null : $this->lines->latestPostedDate($patient->mrn);
        $newest = $latest === null || $dateOfService >= $latest;

        QueryUtils::inTransaction(function () use ($ready, $first, $patient, $visit, $dateOfService, $companies, $pid, $existing, $placeOfService, $settings, $now, $result, $newest): void {
            if ($pid === null) {
                $pid = $this->site->createPatient($patient);
                $result->patientsCreated++;
            }
            if ($newest) {
                $this->details->update($pid, $patient, $first->guarantor, $first->employer);
            }
            foreach ($first->policies as $policy) {
                $this->site->ensurePolicy($pid, $policy, $companies[$policy->rank], $patient, $dateOfService);
            }
            $encounter = $existing['encounter'] ?? null;
            if ($encounter === null) {
                $referrer = preg_match('/^\d{10}$/', $visit->referringNpi) === 1
                    ? $this->site->referringProvider($visit->referringNpi, $visit->referringName)
                    : 0;
                $encounter = $this->site->createEncounter($pid, $visit, $dateOfService, $ready[0]['providerId'], $referrer, $placeOfService, $settings);
                $result->encountersCreated++;
            }
            $this->site->addDiagnoses($pid, $encounter, $visit->diagnoses, $ready[0]['providerId']);
            foreach ($ready as $r) {
                $billingId = $this->site->addCharge($pid, $encounter, $r['charge'], $r['priced'], $r['providerId'], $visit->diagnoses);
                $this->lines->markPosted($r['line']->id, $pid, $encounter, $billingId, $now);
                $result->posted++;
            }
        });
    }

    /**
     * Accessions of the patient's other lines with this code on this date and body site,
     * posted (from this file, or from another file on another accession) or ready in this
     * run. None when the line already says it is a repeat (76 or 77 from Gifford or a person).
     *
     * @param list<array{line: PendingLine, charge: ChargeRecord, priced: PricedCharge, providerId: int}> $ready
     * @return list<string>
     */
    private function repeatOf(PendingLine $line, ChargeRecord $charge, array $ready, string $mrn): array
    {
        $modifiers = array_map(strtoupper(...), $charge->modifiers);
        if ($line->addedModifier !== '' || array_intersect(['76', '77'], $modifiers) !== []) {
            return [];
        }
        $accessions = [];
        foreach ($this->lines->postedSameCode($mrn, $charge->dateOfService, $charge->code, $line->id) as $posted) {
            if ($posted['batch_id'] !== $line->batchId && $posted['accession'] === $charge->accession) {
                continue;
            }
            $earlier = $this->parser->parse(new RawRow(0, $posted['raw']))->charge;
            if ($earlier !== null && self::otherSite($earlier, $charge)) {
                continue;
            }
            $accessions[] = $posted['accession'];
        }
        foreach ($ready as $r) {
            if ($r['charge']->codeType === $charge->codeType && $r['charge']->code === $charge->code && !self::otherSite($r['charge'], $charge)) {
                $accessions[] = $r['charge']->accession;
            }
        }

        return array_values(array_unique($accessions));
    }

    /**
     * Whether the lines name body sites that don't overlap (RT and LT, two fingers): the
     * same code done on two sites. A line without a site can't be told apart from the
     * other, so it is not another site; 50 overlaps RT and LT.
     */
    private static function otherSite(ChargeRecord $a, ChargeRecord $b): bool
    {
        $sites = static function (ChargeRecord $c): array {
            $found = [];
            foreach ($c->modifiers as $modifier) {
                $modifier = strtoupper($modifier);
                if ($modifier === '50') {
                    array_push($found, 'RT', 'LT');
                } elseif (in_array($modifier, self::SITE_MODIFIERS, true)) {
                    $found[] = $modifier;
                }
            }
            return $found;
        };
        $sitesA = $sites($a);
        $sitesB = $sites($b);

        return $sitesA !== [] && $sitesB !== [] && array_intersect($sitesA, $sitesB) === [];
    }

    private static function sameCharge(ChargeRecord $a, ChargeRecord $b): bool
    {
        $mods = static function (ChargeRecord $c): array {
            $m = array_map(strtoupper(...), $c->modifiers);
            sort($m);
            return $m;
        };

        return $a->codeType === $b->codeType
            && $a->code === $b->code
            && $a->units === $b->units
            && $a->dateOfService->format('Y-m-d') === $b->dateOfService->format('Y-m-d')
            && $mods($a) === $mods($b);
    }

    private static function patientOf(ParsedRow $row): PatientRecord
    {
        return $row->patient ?? throw new \LogicException('An importable row has a patient');
    }

    private static function encounterOf(ParsedRow $row): EncounterRecord
    {
        return $row->encounter ?? throw new \LogicException('An importable row has an encounter');
    }

    private static function chargeOf(ParsedRow $row): ChargeRecord
    {
        return $row->charge ?? throw new \LogicException('An importable row has a charge');
    }
}
