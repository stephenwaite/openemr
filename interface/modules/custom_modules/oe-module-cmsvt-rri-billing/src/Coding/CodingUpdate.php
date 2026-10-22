<?php

/**
 * What the biller saved for one charge: a provider when the charge had none,
 * and the two diagnosis codes ('' for an empty box).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Coding;

final readonly class CodingUpdate
{
    public function __construct(
        public int $recordId,
        public ?int $providerId,
        public string $dx1,
        public string $dx2,
    ) {
    }

    /**
     * The form's form_records[<ordfile_record_id>][billing-provider|billing-code-1|billing-code-2].
     *
     * @param array<mixed> $records
     * @return list<self>
     */
    public static function fromForm(array $records): array
    {
        $updates = [];
        foreach ($records as $recordId => $fields) {
            if (!is_int($recordId) || $recordId <= 0 || !is_array($fields)) {
                continue;
            }
            $provider = $fields['billing-provider'] ?? null;
            $updates[] = new self(
                $recordId,
                is_string($provider) && ctype_digit($provider) && (int) $provider > 0 ? (int) $provider : null,
                self::code($fields['billing-code-1'] ?? null),
                self::code($fields['billing-code-2'] ?? null),
            );
        }
        return $updates;
    }

    /** The code as typed, without spaces (some codes come back with them) and upper case. */
    private static function code(mixed $value): string
    {
        return is_string($value) ? strtoupper(trim($value)) : '';
    }

    /** The CPT line's justify for these diagnoses, as the fee sheet writes it: ICD10|A:ICD10|B: or ''. */
    public function justify(): string
    {
        $codes = array_filter([$this->dx1, $this->dx2], static fn(string $code): bool => $code !== '');
        return $codes === [] ? '' : implode('', array_map(static fn(string $code): string => 'ICD10|' . $code . ':', $codes));
    }
}
