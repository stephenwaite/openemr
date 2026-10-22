<?php

/**
 * Reads RRMC's charge file into accounts and their charges.
 *
 * The file is fixed width and has no line structure of its own: each account is
 * a ## record (980 characters after the marker), a ++ record (1038) and any
 * number of $$ charge records (249). Whitespace between records (RRMC sometimes
 * puts line breaks between charges) is skipped. Anything else is an error: the
 * 7.0.2 module stopped on it too.
 *
 * Reading only: the charge master lookups and RRMC's charge rules (rrmc008) are
 * applied to the result separately.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

use Psr\Clock\ClockInterface;

final readonly class ChargeFileReader
{
    public const ACCOUNT_MARKER = '##';
    public const CONTINUATION_MARKER = '++';
    public const CHARGE_MARKER = '$$';
    public const ACCOUNT_LENGTH = 980;
    public const CONTINUATION_LENGTH = 1038;
    public const CHARGE_LENGTH = 249;

    private const MARKER_LENGTH = 2;
    private const WHITESPACE = " \t\r\n";

    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * @return list<ChargeFileAccount> in file order; a visit number can appear more than once
     * @throws ChargeFileException
     */
    public function readFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ChargeFileException('The charge file does not exist or cannot be read.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new ChargeFileException('The charge file could not be read.');
        }
        return $this->read($contents);
    }

    /**
     * @return list<ChargeFileAccount> in file order; a visit number can appear more than once
     * @throws ChargeFileException
     */
    public function read(string $contents): array
    {
        $today = $this->clock->now();
        $accounts = [];
        $position = self::skipWhitespace($contents, 0);
        while ($position < strlen($contents)) {
            $line1 = self::record($contents, $position, self::ACCOUNT_MARKER, self::ACCOUNT_LENGTH);
            $position = self::skipWhitespace($contents, $position + self::MARKER_LENGTH + self::ACCOUNT_LENGTH);
            $line2 = self::record($contents, $position, self::CONTINUATION_MARKER, self::CONTINUATION_LENGTH);
            $position = self::skipWhitespace($contents, $position + self::MARKER_LENGTH + self::CONTINUATION_LENGTH);
            $charges = [];
            while (substr($contents, $position, self::MARKER_LENGTH) === self::CHARGE_MARKER) {
                $charges[] = $this->charge(
                    self::record($contents, $position, self::CHARGE_MARKER, self::CHARGE_LENGTH),
                    $today
                );
                $position = self::skipWhitespace($contents, $position + self::MARKER_LENGTH + self::CHARGE_LENGTH);
            }
            $accounts[] = $this->account($line1, $line2, $charges, $today);
        }
        return $accounts;
    }

    /**
     * The record body after its marker.
     *
     * @throws ChargeFileException
     */
    private static function record(string $contents, int $position, string $marker, int $length): string
    {
        $found = substr($contents, $position, self::MARKER_LENGTH);
        if ($found !== $marker) {
            throw new ChargeFileException(sprintf(
                'Expected a %s record at byte %d of the charge file but found %s.',
                $marker,
                $position,
                in_array($found, [self::ACCOUNT_MARKER, self::CONTINUATION_MARKER, self::CHARGE_MARKER], true)
                    ? 'a ' . $found . ' record'
                    : 'something else'
            ));
        }
        $body = substr($contents, $position + self::MARKER_LENGTH, $length);
        if (strlen($body) < $length) {
            throw new ChargeFileException(sprintf(
                'The %s record at byte %d of the charge file is cut short (%d of %d characters).',
                $marker,
                $position,
                strlen($body),
                $length
            ));
        }
        return $body;
    }

    private static function skipWhitespace(string $contents, int $position): int
    {
        return $position + strspn($contents, self::WHITESPACE, $position);
    }

    /**
     * @param list<ChargeLine> $charges
     */
    private function account(string $line1, string $line2, array $charges, \DateTimeImmutable $today): ChargeFileAccount
    {
        $r1 = new FixedWidthCursor($line1);
        $visitNumber = $r1->text(8);
        $patientName = PersonName::parse($r1->upper(31));
        $patientAddress = self::address($r1, 25, 25, 25, 2, 10);
        $admitDate = $r1->text(10);
        $admitTime = $r1->text(5);
        $workComp = $r1->text(1);
        $guarantorName = PersonName::parse($r1->upper(35));
        $guarantorAddress = self::address($r1, 25, 25, 25, 2, 10);
        $email = $r1->asSent(30);
        $primary = self::insurance($r1, true);
        $secondary = self::insurance($r1, false);
        $authorization = $r1->text(20);

        $r2 = new FixedWidthCursor($line2);
        $accidentDateRaw = $r2->text(6);
        $accidentTime = $r2->text(5);
        $referringName = PersonName::parse($r2->upper(22));
        $rrmcDiagnosis = $r2->text(130);
        $tertiary = self::insurance($r2, false);
        $r2->skip(20);
        $quaternary = self::insurance($r2, false);
        $r2->skip(20);
        $mrnRaw = $r2->text(8);
        $employer = new ChargeFileEmployer($r2->text(30), self::address($r2, 25, 25, 25, 2, 10));
        $guarantorSsn = $r2->text(9);
        $phone = PhoneNumber::parse($r2->upper(12));
        $doctorCode = $r2->text(8);
        $referringIndicator = $r2->text(1);
        $referringNpi = $r2->text(10);
        $admitDateDuplicate = $r2->text(6);
        $gender = $r2->text(1);
        $dob = $r2->text(6);
        $discharge = $r2->text(6);

        return new ChargeFileAccount(
            $visitNumber,
            trim(str_replace('-', '', $mrnRaw)),
            $mrnRaw,
            new ChargeFilePatient($patientName, $patientAddress, $email, $gender, $dob, $phone),
            new ChargeFileGuarantor($guarantorName, $guarantorAddress, $guarantorSsn),
            $employer,
            new ChargeFileVisit(
                $admitDate,
                $admitTime,
                $workComp,
                ShortDate::parse($accidentDateRaw, $today),
                $accidentDateRaw,
                $accidentTime,
                $referringName,
                $referringNpi,
                $referringIndicator,
                $doctorCode,
                $rrmcDiagnosis,
                $authorization,
                $admitDateDuplicate,
                $discharge,
            ),
            $primary,
            $secondary,
            $tertiary,
            $quaternary,
            $charges,
        );
    }

    /**
     * One insurance block (322 characters), plus the primary's authorization (20)
     * when asked. After the secondary those 20 characters are the account's
     * authorization, and after the tertiary and quaternary filler; the caller
     * reads or skips them.
     */
    private static function insurance(FixedWidthCursor $record, bool $readsAuthorization): ChargeFileInsurance
    {
        $payerCode = $record->text(5);
        $policy = $record->text(30);
        $certificate = $record->text(20);
        $group = $record->text(20);
        $groupName = $record->text(30);
        $subscriber = PersonName::parse($record->upper(35));
        $employer = $record->text(30);
        $gender = $record->text(1);
        $record->skip(1);
        $dob = $record->text(10);
        $ssn = $record->text(9);
        $relate = $record->text(2);
        $payerName = $record->text(25);
        $contact = $record->text(25);
        $payerAddress = self::address($record, 20, 15, 20, 2, 10);
        $phone = $record->text(12);
        $authorization = $readsAuthorization ? $record->text(20) : '';
        return new ChargeFileInsurance(
            $payerCode,
            $policy,
            $certificate,
            $group,
            $groupName,
            $subscriber,
            $employer,
            $gender,
            $dob,
            $ssn,
            $relate,
            $payerName,
            $contact,
            $payerAddress,
            $phone,
            $authorization,
        );
    }

    private static function address(
        FixedWidthCursor $record,
        int $street,
        int $suite,
        int $city,
        int $state,
        int $zip,
    ): Address {
        return new Address(
            $record->text($street),
            $record->text($suite),
            $record->text($city),
            $record->text($state),
            $record->text($zip),
        );
    }

    private function charge(string $line, \DateTimeImmutable $today): ChargeLine
    {
        $r3 = new FixedWidthCursor($line);
        $indicator = $r3->text(3);
        $department = $r3->text(2);
        $glCode = $r3->text(1);
        $cdm = $r3->text(4);
        $dateOfServiceRaw = $r3->text(6);
        $units = $r3->text(3);
        $clinical = $r3->text(64);
        $placeOfService = $r3->text(4);
        $readingDoctor = $r3->text(22);
        $cpt = $r3->text(5);
        $r3->skip(3);
        $hcpcs = $r3->text(5);
        $r3->skip(3);
        $modifier1 = $r3->text(2);
        $r3->skip(1);
        $modifier2 = $r3->text(2);
        $r3->skip(1);
        $modifier3 = $r3->text(2);
        $r3->skip(5);
        $observation = $r3->text(25);
        $r3->skip(15);
        $location = $r3->text(4);
        $r3->skip(36);
        $accession = $r3->text(8);
        return new ChargeLine(
            $indicator,
            $department,
            $glCode,
            $cdm,
            ShortDate::parse($dateOfServiceRaw, $today),
            $dateOfServiceRaw,
            $units,
            $clinical,
            $placeOfService,
            $readingDoctor,
            $cpt,
            $hcpcs,
            $modifier1,
            $modifier2,
            $modifier3,
            $observation,
            $location,
            $accession,
        );
    }
}
