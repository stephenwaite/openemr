<?php

/**
 * Keeps a Gifford patient's address, phone, email, guarantor and employer as the newest
 * charge file has them.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PartyRecord;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\PatientRecord;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;

/**
 * Statements go to patient_data's guardian (guarantor) when one is set, else to the
 * patient, so both have to follow the file. Blank values in the file never erase what the
 * site has. A replaced address is kept as an inactive Additional Address (contact_address,
 * use Old/Incorrect, reason Moved), written directly like the screens' rows because this
 * runs with no logged-in user.
 */
final class PatientDetailsWriter
{
    public function update(int $pid, PatientRecord $patient, ?PartyRecord $guarantor, ?PartyRecord $employer): void
    {
        $current = QueryUtils::querySingleRow(
            'SELECT `street`, `street_line_2`, `city`, `state`, `postal_code`, `country_code`, `phone_home`, `email`'
            . ' FROM `patient_data` WHERE `pid` = ?',
            [$pid]
        );
        if (!is_array($current)) {
            throw new \RuntimeException('No patient to update');
        }
        $set = [];

        $a = $patient->address;
        $new = ['street' => $a->street, 'street_line_2' => $a->street2, 'city' => $a->city, 'state' => $a->state, 'postal_code' => $a->postalCode];
        if ($a->street !== '' || $a->city !== '') {
            $changed = array_filter($new, static fn (string $value, string $column): bool => $value !== self::text($current[$column] ?? null), ARRAY_FILTER_USE_BOTH);
            if ($changed !== []) {
                if (self::text($current['street'] ?? null) !== '' || self::text($current['city'] ?? null) !== '') {
                    $this->archiveAddress($pid, $current);
                }
                $set += $new;
            }
        }
        if ($patient->phone !== '' && $patient->phone !== self::text($current['phone_home'] ?? null)) {
            $set['phone_home'] = $patient->phone;
        }
        if ($patient->email !== '' && $patient->email !== self::text($current['email'] ?? null)) {
            $set['email'] = $patient->email;
        }

        if ($guarantor !== null) {
            $set += self::isPatient($guarantor, $patient)
                ? ['guardiansname' => '', 'guardianaddress' => '', 'guardiancity' => '', 'guardianstate' => '', 'guardianpostalcode' => '']
                : [
                    'guardiansname' => trim($guarantor->firstName . ' ' . $guarantor->lastName),
                    'guardianaddress' => trim($guarantor->address->street . ' ' . $guarantor->address->street2),
                    'guardiancity' => $guarantor->address->city,
                    'guardianstate' => $guarantor->address->state,
                    'guardianpostalcode' => $guarantor->address->postalCode,
                ];
        }

        if ($set !== []) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `patient_data` SET ' . implode(', ', array_map(static fn (string $c): string => '`' . $c . '` = ?', array_keys($set)))
                . ' WHERE `pid` = ?',
                [...array_values($set), $pid]
            );
        }
        if ($employer !== null) {
            $this->updateEmployer($pid, $employer);
        }
    }

    /**
     * The guarantor is the patient: same last and first name, in either order (Gifford's
     * sample has them swapped on some rows).
     */
    private static function isPatient(PartyRecord $guarantor, PatientRecord $patient): bool
    {
        $norm = static fn (string $s): string => strtoupper(trim($s));
        $guar = [$norm($guarantor->lastName), $norm($guarantor->firstName)];
        $pat = [$norm($patient->lastName), $norm($patient->firstName)];

        return $guar === $pat || $guar === array_reverse($pat);
    }

    /**
     * @param array<mixed> $current the patient's address columns before the update
     */
    private function archiveAddress(int $pid, array $current): void
    {
        $contactId = QueryUtils::fetchSingleValue(
            "SELECT `id` FROM `contact` WHERE `foreign_table_name` = 'patient_data' AND `foreign_id` = ? ORDER BY `id` LIMIT 1",
            'id',
            [$pid]
        );
        if (!is_numeric($contactId)) {
            $contactId = QueryUtils::generateId();
            QueryUtils::sqlStatementThrowException(
                "INSERT INTO `contact` (`id`, `foreign_table_name`, `foreign_id`) VALUES (?, 'patient_data', ?)",
                [$contactId, $pid]
            );
        }

        $addressId = QueryUtils::generateId();
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `addresses` (`id`, `line1`, `line2`, `city`, `state`, `zip`, `country`) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $addressId,
                self::text($current['street'] ?? null),
                self::text($current['street_line_2'] ?? null),
                self::text($current['city'] ?? null),
                self::text($current['state'] ?? null),
                self::text($current['postal_code'] ?? null),
                self::text($current['country_code'] ?? null),
            ]
        );
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `contact_address` (`id`, `contact_id`, `address_id`, `priority`, `type`, `use`, `notes`, `status`,'
            . " `is_primary`, `period_end`, `inactivated_reason`) VALUES (?, ?, ?, 0, 'both', 'old', ?, 'I', 'N', NOW(), 'Moved')",
            [QueryUtils::generateId(), (int) $contactId, $addressId, 'Replaced by a newer Gifford charge file']
        );
    }

    private function updateEmployer(int $pid, PartyRecord $employer): void
    {
        $e = $employer->address;
        $latest = QueryUtils::querySingleRow(
            'SELECT `name`, `street`, `city`, `state`, `postal_code` FROM `employer_data` WHERE `pid` = ? ORDER BY `date` DESC, `id` DESC LIMIT 1',
            [$pid]
        );
        $new = ['name' => $employer->lastName, 'street' => $e->street, 'city' => $e->city, 'state' => $e->state, 'postal_code' => $e->postalCode];
        if (is_array($latest) && array_map(self::text(...), $latest) === $new) {
            return;
        }
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `employer_data` (`uuid`, `name`, `street`, `street_line_2`, `postal_code`, `city`, `state`, `date`, `pid`)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                UuidRegistry::getRegistryForTable('employer_data')->createUuid(),
                $new['name'],
                $e->street,
                $e->street2,
                $e->postalCode,
                $e->city,
                $e->state,
                $pid,
            ]
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
