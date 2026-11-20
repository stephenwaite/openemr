<?php

/**
 * Formats encounter rows into Press Ganey's survey upload layout.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\PressGaney;

final readonly class PressGaneyRecordFormatter
{
    public const HEADERS = [
        'Survey Designator', 'Client ID', 'Last Name', 'Middle Initial', 'First Name',
        'Address 1', 'Address 2', 'City', 'State', 'Zip Code', 'Telephone Number',
        'Mobile Number', 'Gender', 'Date of Birth', 'Language', 'Medical Record Number',
        'Unique ID', 'Location Code', 'Location Name', 'Attending Physician NPI',
        'Attending Physician Name', 'Provider Type', 'Provider Specialty', 'Site address 1',
        'Site address 2', 'Site city', 'Site state', 'Site zip', 'Visit or Admin Date',
        'Email', 'E.O.R. Indicator',
    ];

    public function __construct(private string $surveyDesignator, private string $clientId)
    {
    }

    /**
     * @param array<mixed> $record one row of PressGaneyRepository::fetchEncounters()
     * @return list<string> the fields in HEADERS order
     */
    public function format(array $record): array
    {
        $field = static fn(string $key): string => is_scalar($record[$key] ?? null) ? trim((string) $record[$key]) : '';

        $providerName = trim($field('provider_fname') . ' ' . $field('provider_lname'));
        $providerType = ucwords(strtolower(str_replace('_', ' ', $field('physician_type'))));

        return [
            $this->surveyDesignator,
            $this->clientId,
            substr($field('lname'), 0, 25),
            strtoupper(substr($field('mname'), 0, 1)),
            substr($field('fname'), 0, 20),
            substr($field('street'), 0, 40),
            substr($field('street_line_2'), 0, 40),
            substr($field('city'), 0, 25),
            strtoupper(substr($field('state'), 0, 2)),
            substr($field('postal_code'), 0, 10),
            self::phone($field('phone_home')),
            self::phone($field('phone_cell')),
            self::gender($field('sex')),
            self::date($field('DOB')),
            '',
            substr($field('pubpid'), 0, 20),
            substr($field('encounter'), 0, 20),
            substr($field('facility_id'), 0, 20),
            substr($field('facility_name'), 0, 50),
            substr($field('provider_npi'), 0, 50),
            $providerName === '' ? '' : substr('Dr. ' . $providerName, 0, 50),
            substr($providerType, 0, 50),
            substr($field('specialty'), 0, 50),
            substr($field('facility_street'), 0, 40),
            '',
            substr($field('facility_city'), 0, 25),
            strtoupper(substr($field('facility_state'), 0, 2)),
            self::siteZip($field('facility_postal_code')),
            self::date($field('visit_date')),
            substr($field('email'), 0, 60),
            '$',
        ];
    }

    /**
     * Digits only; 10-digit numbers as NNN-NNN-NNNN.
     */
    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        return strlen($digits) === 10
            ? substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4)
            : $digits;
    }

    /**
     * Press Ganey codes: 1 = male, 2 = female, M = missing/unknown.
     */
    private static function gender(string $sex): string
    {
        return match (strtoupper($sex)) {
            'MALE', 'M' => '1',
            'FEMALE', 'F' => '2',
            default => 'M',
        };
    }

    /**
     * mmddyyyy, or empty when absent or unparsable.
     */
    private static function date(string $value): string
    {
        $timestamp = $value === '' ? false : strtotime($value);
        return $timestamp === false ? '' : date('mdY', $timestamp);
    }

    /**
     * Up to 10 characters; a bare 9-digit zip becomes 5+4.
     */
    private static function siteZip(string $value): string
    {
        $zip = substr($value, 0, 10);
        return strlen($zip) === 9 && ctype_digit($zip) ? substr($zip, 0, 5) . '-' . substr($zip, 5) : $zip;
    }
}
