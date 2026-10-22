<?php

/**
 * Builds synthetic RRMC charge file records for tests, from the field widths of
 * RRMC's layout (written out here independently of the reader, so a wrong offset
 * in either shows up). All values used in tests are invented.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\RriBilling;

final class ChargeFileFixture
{
    /** @var list<array{string, int}> */
    private const INSURANCE = [
        ['code', 5], ['policy', 30], ['cert', 20], ['group', 20], ['groupName', 30],
        ['subscriber', 35], ['employer', 30], ['gender', 1], ['', 1], ['dob', 10], ['ssn', 9],
        ['relate', 2], ['name', 25], ['contact', 25], ['street', 20], ['suite', 15],
        ['city', 20], ['state', 2], ['zip', 10], ['phone', 12],
    ];

    /** @var list<array{string, int}> */
    private const CHARGE = [
        ['indicator', 3], ['department', 2], ['glCode', 1], ['cdm', 4], ['dos', 6], ['units', 3],
        ['clinical', 64], ['place', 4], ['doctor', 22], ['cpt', 5], ['', 3], ['hcpcs', 5], ['', 3],
        ['mod1', 2], ['', 1], ['mod2', 2], ['', 1], ['mod3', 2], ['', 5], ['observation', 25],
        ['', 15], ['location', 4], ['', 36], ['accession', 8],
    ];

    /**
     * The ## record, marker included.
     *
     * @param array<string, string> $values account fields; insurance fields are
     *                                      prefixed 'p1.' (primary) and 'p2.'
     */
    public static function accountLine(array $values): string
    {
        $layout = [
            ['visit', 8], ['name', 31], ['street', 25], ['suite', 25], ['city', 25], ['state', 2],
            ['zip', 10], ['admitDate', 10], ['admitTime', 5], ['workComp', 1], ['guarantor', 35],
            ['guarantorStreet', 25], ['guarantorSuite', 25], ['guarantorCity', 25],
            ['guarantorState', 2], ['guarantorZip', 10], ['email', 30],
            ...self::prefixed('p1.', self::INSURANCE), ['p1.auth', 20],
            ...self::prefixed('p2.', self::INSURANCE), ['authorization', 20],
        ];
        return '##' . self::record($layout, $values, 980);
    }

    /**
     * The ++ record, marker included.
     *
     * @param array<string, string> $values insurance fields prefixed 'p3.' and 'p4.'
     */
    public static function continuationLine(array $values): string
    {
        $layout = [
            ['accidentDate', 6], ['accidentTime', 5], ['referring', 22], ['diagnosis', 130],
            ...self::prefixed('p3.', self::INSURANCE), ['', 20],
            ...self::prefixed('p4.', self::INSURANCE), ['', 20],
            ['mrn', 8], ['employer', 30], ['employerStreet', 25], ['employerSuite', 25],
            ['employerCity', 25], ['employerState', 2], ['employerZip', 10], ['guarantorSsn', 9],
            ['phone', 12], ['doctorCode', 8], ['referringIndicator', 1], ['referringNpi', 10],
            ['admitDateDuplicate', 6], ['gender', 1], ['dob', 6], ['discharge', 6],
        ];
        return '++' . self::record($layout, $values, 1038);
    }

    /**
     * The $$ record, marker included.
     *
     * @param array<string, string> $values
     */
    public static function chargeLine(array $values): string
    {
        return '$$' . self::record(self::CHARGE, $values, 249);
    }

    /**
     * @param list<array{string, int}> $layout
     * @return list<array{string, int}>
     */
    private static function prefixed(string $prefix, array $layout): array
    {
        return array_map(
            static fn(array $field): array => [$field[0] === '' ? '' : $prefix . $field[0], $field[1]],
            $layout
        );
    }

    /**
     * @param list<array{string, int}> $layout
     * @param array<string, string> $values
     */
    private static function record(array $layout, array $values, int $length): string
    {
        $unknown = array_diff(array_keys($values), array_column($layout, 0));
        if ($unknown !== []) {
            throw new \LogicException('Unknown fields: ' . implode(', ', $unknown));
        }
        $record = '';
        foreach ($layout as [$name, $width]) {
            $value = $name === '' ? '' : ($values[$name] ?? '');
            if (strlen($value) > $width) {
                throw new \LogicException("$name is longer than $width characters");
            }
            $record .= str_pad($value, $width);
        }
        if (strlen($record) > $length) {
            throw new \LogicException('The layout is longer than the record');
        }
        return str_pad($record, $length);
    }
}
