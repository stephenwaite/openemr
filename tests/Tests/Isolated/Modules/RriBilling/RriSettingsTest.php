<?php

/**
 * Isolated tests for the RRI billing module's settings reader: unsaved settings,
 * the SFTP connection and the site's document folders.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Modules\RriBilling;

require_once __DIR__ . '/autoload.php';

use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriGlobals;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use OpenEMR\Common\Crypto\CryptoGenException;
use OpenEMR\Common\Crypto\CryptoInterface;
use OpenEMR\Core\OEGlobalsBag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RriSettingsTest extends TestCase
{
    /**
     * @param array<string, string> $values
     */
    private function settings(array $values, bool $decrypts = true): RriSettings
    {
        $crypto = $this->createStub(CryptoInterface::class);
        if ($decrypts) {
            $crypto->method('decryptFromDatabase')->willReturn('secret');
        } else {
            $crypto->method('decryptFromDatabase')->willThrowException(new CryptoGenException('Decryption failed'));
        }
        return new RriSettings(new OEGlobalsBag($values), $crypto);
    }

    /**
     * @return array<string, string>
     */
    private static function sftpValues(): array
    {
        return [
            RriGlobals::SFTP_URL => ' sftp.example.org ',
            RriGlobals::SFTP_USERNAME => 'cms',
            RriGlobals::SFTP_PASSWORD => 'encrypted',
        ];
    }

    #[Test]
    public function unsavedSettingsUseTheDefaults(): void
    {
        $settings = $this->settings([]);

        $this->assertNull($settings->sftp());
        $this->assertFalse($settings->debug());
        $this->assertSame(15000, $settings->autosaveMilliseconds());
        $this->assertSame('01', $settings->readingDoctorCode());
    }

    #[Test]
    public function sftpNeedsHostUsernameAndPassword(): void
    {
        foreach (array_keys(self::sftpValues()) as $missing) {
            $values = self::sftpValues();
            $values[$missing] = '';
            $this->assertNull($this->settings($values)->sftp(), $missing);
        }
    }

    #[Test]
    public function sftpDefaultsToPort22AndTheLoginFolder(): void
    {
        $sftp = $this->settings(self::sftpValues())->sftp();

        $this->assertNotNull($sftp);
        $this->assertSame('sftp.example.org', $sftp->host);
        $this->assertSame(22, $sftp->port);
        $this->assertSame('cms', $sftp->username);
        $this->assertSame('secret', $sftp->password);
        $this->assertSame('radb_eisemannbilling990101.txt', $sftp->remotePath('radb_eisemannbilling990101.txt'));
    }

    #[Test]
    public function sftpFolderGetsATrailingSlash(): void
    {
        $values = self::sftpValues() + [
            RriGlobals::SFTP_PORT => '2222',
            RriGlobals::SFTP_REMOTE_FOLDER => 'prod',
        ];
        $sftp = $this->settings($values)->sftp();

        $this->assertNotNull($sftp);
        $this->assertSame(2222, $sftp->port);
        $this->assertSame('prod/x.txt', $sftp->remotePath('x.txt'));
    }

    #[Test]
    public function anUndecryptablePasswordIsAnError(): void
    {
        $this->expectException(CryptoGenException::class);
        $this->settings(self::sftpValues(), false)->sftp();
    }

    #[Test]
    public function theReadingDoctorCodeIs01UnlessSet(): void
    {
        $this->assertSame('01', $this->settings([])->readingDoctorCode());
        $this->assertSame('06', $this->settings([RriGlobals::READING_DOCTOR_CODE => ' 06 '])->readingDoctorCode());
    }

    #[Test]
    public function autosaveIsAtLeastFiveSeconds(): void
    {
        $this->assertSame(5000, $this->settings([RriGlobals::AUTOSAVE_INTERVAL => '2'])->autosaveMilliseconds());
        $this->assertSame(30000, $this->settings([RriGlobals::AUTOSAVE_INTERVAL => '30'])->autosaveMilliseconds());
    }

    #[Test]
    public function documentFoldersAreUnderTheSite(): void
    {
        $settings = $this->settings(['OE_SITE_DIR' => '/var/www/openemr/sites/1600/']);

        $this->assertSame('/var/www/openemr/sites/1600/documents/oe-module-cmsvt-rri-billing/', $settings->documentDirectory());
        $this->assertSame('/var/www/openemr/sites/1600/documents/oe-module-cmsvt-rri-billing/charges/', $settings->chargeFileDirectory());
        $this->assertSame('/var/www/openemr/sites/1600/documents/oe-module-cmsvt-rri-billing/ordfiles/', $settings->ordFileDirectory());
        $this->assertSame('/var/www/openemr/sites/1600/documents/oe-module-cmsvt-rri-billing/errors/', $settings->errorFileDirectory());
    }
}
