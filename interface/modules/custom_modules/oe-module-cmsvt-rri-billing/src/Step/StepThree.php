<?php

/**
 * Step 3 (cmsvt:rri-248, the COBOL rrmc006) over a charge file: for each
 * account, the account file record, the ordfile lines for its charges, work
 * comp and email/authorization rows, and the patient and insurance update.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\EmailAuthRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileBuilder;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StepThreeFiles;
use Cmsvt\OpenEMR\Modules\RriBilling\Patient\PatientUpdates;
use Psr\Clock\ClockInterface;

final readonly class StepThree
{
    /**
     * @param bool $dryRun make the lines and messages but save nothing
     */
    public function __construct(
        private AccountBuilder $builder,
        private AccountFile $accountFile,
        private OrdfileBuilder $ordfile,
        private StepThreeFiles $files,
        private PatientUpdates $patients,
        private ClockInterface $clock,
        private bool $dryRun,
    ) {
    }

    /**
     * @param list<ChargeFileAccount> $accounts the charge file after the charge rules
     * @param ?string $resumeMrn start at this patient's first account (skips those before)
     * @param ?callable(): void $advance called after each account
     */
    public function run(array $accounts, \DateTimeImmutable $reportDate, ?string $resumeMrn = null, ?callable $advance = null): StepThreeResult
    {
        $lines = [];
        $errors = [];
        $insuranceErrors = [];
        $patients = 0;
        $today = $this->clock->now();
        foreach ($accounts as $file) {
            if ($resumeMrn !== null) {
                if (ltrim($file->mrn, '0') !== ltrim($resumeMrn, '0')) {
                    if ($advance !== null) {
                        $advance();
                    }
                    continue;
                }
                $resumeMrn = null;
            }
            $built = $this->builder->build($file);
            array_push($errors, ...$built->errors);
            array_push($insuranceErrors, ...$built->insuranceErrors);
            $account = $built->account;
            $doctor = $built->referringDoctor;
            if ($account === null || $doctor === null) {
                if ($advance !== null) {
                    $advance();
                }
                continue;
            }
            $patients++;
            $account->garno = $built->existing?->garno;
            if (!$this->dryRun) {
                $this->accountFile->save($account);
            }

            $accidentDate = OrdfileBuilder::accidentDate($file);
            $lastLine = null;
            foreach ($file->charges as $charge) {
                $made = $this->ordfile->charge($file, $account, $charge, $doctor->refkey, $accidentDate, $reportDate, $today);
                array_push($errors, ...$made->errors);
                $lastLine = $made->line;
                if ($made->line !== null) {
                    $lines[] = $made->line;
                }
                if (!$this->dryRun) {
                    foreach ($made->workComp as $workComp) {
                        $this->files->saveWorkComp($workComp);
                    }
                }
            }
            $emailAuth = EmailAuthRecord::forAccount($file, $lastLine);
            if (!$this->dryRun) {
                if ($emailAuth !== null) {
                    $this->files->saveEmailAuth($emailAuth);
                }
                array_push($errors, ...$this->patients->update($file, $account, $built->mrn, $built->patientDob, $built->patientSex, $doctor));
            }
            if ($advance !== null) {
                $advance();
            }
        }
        return new StepThreeResult($lines, $errors, $insuranceErrors, $patients);
    }
}
