<?php

/**
 * A partner's SFTP Port is optional: a claim file is sent to port 22 when the
 * setting is empty, and held back only when the setting is not a port number.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing;

use OpenEMR\Billing\BillingProcessor\X12RemoteTracker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class X12RemoteTrackerSftpSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        // BaseService pulls in custom/code_types.inc.php at load, which queries
        // unless this constant is set. Define it before X12RemoteTracker is loaded.
        if (!defined('OPENEMR_STATIC_ANALYSIS')) {
            define('OPENEMR_STATIC_ANALYSIS', true);
        }
    }

    /**
     * @return array<string, array{mixed, ?int}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function portProvider(): array
    {
        return [
            'not set' => [null, 22],
            'empty' => ['', 22],
            'blank' => ['  ', 22],
            'the default, entered' => ['22', 22],
            'another port' => ['2222', 2222],
            'spaces around it' => [' 10022 ', 10022],
            'an integer' => [8022, 8022],
            'highest port' => ['65535', 65535],
            'zero' => ['0', null],
            'past the highest port' => ['65536', null],
            'too many digits' => ['000022', null],
            'negative' => ['-22', null],
            'not a number' => ['sftp', null],
            'a decimal' => ['22.5', null],
            'not text' => [['22'], null],
        ];
    }

    #[DataProvider('portProvider')]
    public function testSftpPort(mixed $setting, ?int $expected): void
    {
        $this->assertSame($expected, X12RemoteTracker::sftpPort($setting));
    }

    /**
     * @return array<string, string>
     */
    private function partner(): array
    {
        return [
            'x12_sftp_host' => 'sftp.example.org',
            'x12_sftp_port' => '',
            'x12_sftp_login' => 'biller',
            'x12_sftp_pass' => 'encrypted',
            'x12_sftp_remote_dir' => 'inbound',
            'x12_sftp_local_dir' => '/tmp/edi/',
        ];
    }

    public function testAPartnerWithNoPortIsUsable(): void
    {
        $this->assertSame([], X12RemoteTracker::sftpSettingProblems($this->partner()));
    }

    public function testAPartnerRowWithoutThePortColumnIsUsable(): void
    {
        $partner = $this->partner();
        unset($partner['x12_sftp_port']);

        $this->assertSame([], X12RemoteTracker::sftpSettingProblems($partner));
    }

    public function testAPortThatIsNotAPortNumberIsReported(): void
    {
        $partner = $this->partner();
        $partner['x12_sftp_port'] = 'sftp';

        $this->assertSame(
            ['`X12 SFTP Port` must be a port number (1 to 65535), or empty for port 22'],
            X12RemoteTracker::sftpSettingProblems($partner)
        );
    }

    public function testTheOtherSettingsAreStillRequired(): void
    {
        $this->assertSame(
            [
                '`X12 SFTP Host` is required',
                '`X12 SFTP Login` is required',
                '`X12 SFTP Password` is required',
                '`X12 SFTP Remote Dir` is required',
                '`X12 SFTP Local Dir` is required',
            ],
            X12RemoteTracker::sftpSettingProblems(['x12_sftp_port' => '22'])
        );
    }
}
