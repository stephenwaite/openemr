<?php

/**
 * Isolated tests for the Press Ganey export row layout.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace {
    $cmsvtModuleSrc = dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/';
    if (!is_dir($cmsvtModuleSrc)) {
        throw new RuntimeException('oe-module-cmsvt source not found at ' . $cmsvtModuleSrc);
    }
    spl_autoload_register(static function (string $class) use ($cmsvtModuleSrc): void {
        $prefix = 'Cmsvt\\OpenEMR\\Modules\\Customizations\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $cmsvtModuleSrc . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

namespace OpenEMR\Tests\Isolated\Modules\Cmsvt {

    use Cmsvt\OpenEMR\Modules\Customizations\PressGaney\PressGaneyRecordFormatter;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class PressGaneyRecordFormatterTest extends TestCase
    {
        /**
         * @return array<string, mixed>
         */
        private static function record(): array
        {
            return [
                'encounter' => '5012', 'visit_date' => '2026-03-14 09:30:00',
                'lname' => 'Alvarez', 'fname' => 'Renata', 'mname' => 'kay', 'pubpid' => 'MR1088',
                'DOB' => '1971-06-02', 'sex' => 'Female', 'street' => '12 Main St', 'street_line_2' => 'Apt 4',
                'city' => 'Rutland', 'state' => 'vermont', 'postal_code' => '05701',
                'phone_home' => '(802) 555-0147', 'phone_cell' => '802555019', 'email' => 'r@example.com',
                'facility_id' => '3', 'facility_name' => 'Main Clinic', 'facility_street' => '1 Clinic Rd',
                'facility_city' => 'Rutland', 'facility_state' => 'vt', 'facility_postal_code' => '057011234',
                'provider_lname' => 'Stone', 'provider_fname' => 'Ada', 'provider_npi' => '1234567893',
                'physician_type' => 'PODIATRIST_DPM', 'specialty' => '213E00000X',
            ];
        }

        #[Test]
        public function rowFollowsThePressGaneyLayout(): void
        {
            $row = (new PressGaneyRecordFormatter('DESIG', 'CLIENT'))->format(self::record());

            $this->assertCount(count(PressGaneyRecordFormatter::HEADERS), $row);
            $this->assertSame(
                ['DESIG', 'CLIENT', 'Alvarez', 'K', 'Renata', '12 Main St', 'Apt 4', 'Rutland', 'VE', '05701',
                 '802-555-0147', '802555019', '2', '06021971', '', 'MR1088', '5012', '3', 'Main Clinic',
                 '1234567893', 'Dr. Ada Stone', 'Podiatrist Dpm', '213E00000X', '1 Clinic Rd', '', 'Rutland',
                 'VT', '05701-1234', '03142026', 'r@example.com', '$'],
                $row
            );
        }

        /**
         * @return array<string, array{string, string}>
         *
         * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
         */
        public static function genderProvider(): array
        {
            return [
                'male word' => ['Male', '1'],
                'male letter' => ['m', '1'],
                'female letter' => ['F', '2'],
                'unknown' => ['Unknown', 'M'],
                'blank' => ['', 'M'],
            ];
        }

        #[Test]
        #[DataProvider('genderProvider')]
        public function genderUsesPressGaneyCodes(string $sex, string $expected): void
        {
            $record = ['sex' => $sex] + self::record();
            $this->assertSame($expected, (new PressGaneyRecordFormatter('D', 'C'))->format($record)[12]);
        }

        #[Test]
        public function missingValuesBecomeEmptyStringsAndLongValuesAreTruncated(): void
        {
            $row = (new PressGaneyRecordFormatter('D', 'C'))->format([
                'lname' => str_repeat('x', 40),
                'DOB' => null,
                'visit_date' => 'not a date',
                'provider_lname' => '',
                'provider_fname' => '',
            ]);

            $this->assertSame(25, strlen($row[2]), 'last name is capped at 25 characters');
            $this->assertSame('', $row[13], 'missing DOB');
            $this->assertSame('', $row[28], 'unparsable visit date');
            $this->assertSame('', $row[20], 'no provider name without a provider');
            $this->assertSame('M', $row[12], 'missing gender');
            $this->assertSame('$', $row[30], 'end-of-record marker');
        }
    }
}
