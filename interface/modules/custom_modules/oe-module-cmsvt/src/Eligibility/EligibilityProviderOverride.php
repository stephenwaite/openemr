<?php

/**
 * Sends a fixed provider (and optionally a fixed receiver name) on real-time eligibility requests.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Eligibility;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Billing\EligibilityRequestFilterEvent;

final readonly class EligibilityProviderOverride
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(EligibilityRequestFilterEvent $event): void
    {
        $providerId = $this->globals->getInt(CmsvtGlobals::ELIG_PROVIDER_ID);
        if ($providerId <= 0) {
            return;
        }

        $npi = QueryUtils::fetchSingleValue('SELECT `npi` FROM `users` WHERE `id` = ?', 'npi', [$providerId]);
        if (!is_string($npi) || $npi === '') {
            // Leave the row alone so EDI270's own validation reports the missing NPI.
            return;
        }

        $row = $event->getRow();
        $row['providerID'] = $providerId;
        $row['provider_npi'] = $npi;

        $receiverName = $this->globals->getString(CmsvtGlobals::ELIG_RECEIVER_NAME);
        if ($receiverName !== '') {
            $row['facility_name'] = $receiverName;
        }

        $event->setRow($row);
    }
}
