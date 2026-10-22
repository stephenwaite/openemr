<?php

/**
 * Reads the RRI billing module's settings for the current site, typed.
 *
 * Everything the module needs to know about a site comes through here, so the
 * oncology (eis) and radiology (2400) differences stay in one place.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Globals;

use Cmsvt\OpenEMR\Modules\RriBilling\Billing\PlaceOfService;
use OpenEMR\Common\Crypto\CryptoGenException;
use OpenEMR\Common\Crypto\CryptoInterface;
use OpenEMR\Core\OEGlobalsBag;

final readonly class RriSettings
{
    private const DEFAULT_SFTP_PORT = 22;
    private const DEFAULT_AUTOSAVE_SECONDS = 15;
    private const MINIMUM_AUTOSAVE_SECONDS = 5;
    private const DEFAULT_READING_DOCTOR_CODE = '01';
    private const DEFAULT_READING_DOCTOR_USER = 5000;
    private const DEFAULT_OUTPATIENT_FACILITY = 4;
    private const DEFAULT_INPATIENT_FACILITY = 5;

    /** Under the site directory; the 7.0.2 module used the same folders. */
    private const DOCUMENT_FOLDER = 'documents/oe-module-cmsvt-rri-billing/';

    public function __construct(
        private OEGlobalsBag $globals,
        private CryptoInterface $crypto,
    ) {
    }

    /**
     * The SFTP connection, or null when host, username or password is blank.
     *
     * The password is read as the settings page stores it: encrypted, or plain
     * when the site doesn't encrypt database values. A value saved by the 7.0.2
     * module is in the same encrypted format.
     *
     * @throws CryptoGenException when the stored password cannot be decrypted
     */
    public function sftp(): ?SftpSettings
    {
        $host = trim($this->globals->getString(RriGlobals::SFTP_URL));
        $username = trim($this->globals->getString(RriGlobals::SFTP_USERNAME));
        $stored = $this->globals->getString(RriGlobals::SFTP_PASSWORD);
        if ($host === '' || $username === '' || $stored === '') {
            return null;
        }
        $password = $this->crypto->decryptFromDatabase($stored);
        $port = $this->globals->getInt(RriGlobals::SFTP_PORT);
        $folder = trim($this->globals->getString(RriGlobals::SFTP_REMOTE_FOLDER));
        if ($folder !== '' && !str_ends_with($folder, '/')) {
            $folder .= '/';
        }
        return new SftpSettings(
            $host,
            $port > 0 ? $port : self::DEFAULT_SFTP_PORT,
            $folder,
            $username,
            $password,
        );
    }

    public function debug(): bool
    {
        return $this->globals->getBoolean(RriGlobals::DEBUG);
    }

    /** The ordfile's doctor code for every charge (blank: 01). */
    public function readingDoctorCode(): string
    {
        $code = trim($this->globals->getString(RriGlobals::READING_DOCTOR_CODE));
        return $code === '' ? self::DEFAULT_READING_DOCTOR_CODE : substr($code, 0, 2);
    }

    /** The user the reading doctor code's charges are billed under (0: 5000). */
    public function readingDoctorUser(): int
    {
        $user = $this->globals->getInt(RriGlobals::READING_DOCTOR_USER);
        return $user > 0 ? $user : self::DEFAULT_READING_DOCTOR_USER;
    }

    /** Step 6's facility for a place of service (0: 4 outpatient and emergency, 5 inpatient). */
    public function facility(PlaceOfService $place): int
    {
        [$key, $default] = match ($place) {
            PlaceOfService::InpatientHospital => [RriGlobals::INPATIENT_FACILITY, self::DEFAULT_INPATIENT_FACILITY],
            PlaceOfService::OutpatientHospital, PlaceOfService::EmergencyRoom => [RriGlobals::OUTPATIENT_FACILITY, self::DEFAULT_OUTPATIENT_FACILITY],
        };
        $facility = $this->globals->getInt($key);
        return $facility > 0 ? $facility : $default;
    }

    public function autosaveMilliseconds(): int
    {
        $seconds = $this->globals->getInt(RriGlobals::AUTOSAVE_INTERVAL);
        if ($seconds <= 0) {
            $seconds = self::DEFAULT_AUTOSAVE_SECONDS;
        }
        return max($seconds, self::MINIMUM_AUTOSAVE_SECONDS) * 1000;
    }

    /** The module's folder in the site's documents, ending in '/'. */
    public function documentDirectory(): string
    {
        return rtrim($this->globals->getString('OE_SITE_DIR'), '/') . '/' . self::DOCUMENT_FOLDER;
    }

    /** Charge files fetched by step 1. */
    public function chargeFileDirectory(): string
    {
        return $this->documentDirectory() . 'charges/';
    }

    /** Ordfiles written by step 3. */
    public function ordFileDirectory(): string
    {
        return $this->documentDirectory() . 'ordfiles/';
    }

    /** Error reports written by the steps. */
    public function errorFileDirectory(): string
    {
        return $this->documentDirectory() . 'errors/';
    }
}
