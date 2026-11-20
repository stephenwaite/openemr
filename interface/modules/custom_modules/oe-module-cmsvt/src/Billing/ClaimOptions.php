<?php

/**
 * Wires the CMS claim rules and per-site claim settings into claim generation
 * and SFTP upload.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Billing;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Billing\Claim837PRulesEvent;
use OpenEMR\Events\Billing\ClaimProviderFilterEvent;
use OpenEMR\Events\Billing\X12RemoteFilenameFilterEvent;
use OpenEMR\Events\Billing\X12RemoteTransportEvent;

final readonly class ClaimOptions
{
    /** BCBS Vermont's MOVEit server only accepts files named 007111NN.x12. */
    private const BCBSVT_MOVEIT_HOST = 'moveit.bcbsvt.com';

    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function applyRules(Claim837PRulesEvent $event): void
    {
        if ($this->globals->getBoolean(CmsvtGlobals::CLAIM_RULES_DISABLED)) {
            return;
        }
        $event->setRules(new CmsvtClaimRules(
            $this->list(CmsvtGlobals::CLAIM_ROUTINE_FOOT_CARE_NPIS),
            $this->list(CmsvtGlobals::CLAIM_NDC_SKIP_PAYER_IDS),
            $this->list(CmsvtGlobals::CLAIM_CLOSED_FACILITY_NPIS),
        ));
    }

    public function applyProvider(ClaimProviderFilterEvent $event): void
    {
        $providerId = $this->globals->getInt(CmsvtGlobals::CLAIM_RENDERING_PROVIDER_ID);
        if ($providerId > 0) {
            $event->setProviderId($providerId);
        }
    }

    public function applyRemoteFilename(X12RemoteFilenameFilterEvent $event): void
    {
        if (strcasecmp($event->sftpHost, self::BCBSVT_MOVEIT_HOST) === 0) {
            $event->setFilename('007111' . random_int(20, 99) . '.x12');
        }
    }

    /**
     * Vermont Medicaid's claim files go through its web portal when the site says so.
     */
    public function applyRemoteTransport(X12RemoteTransportEvent $event): void
    {
        if (
            $this->globals->getBoolean(CmsvtGlobals::CLAIM_VTMEDICAID_PORTAL)
            && VtMedicaidPortal::isPortalHost($event->sftpHost)
        ) {
            $event->setTransport(new VtMedicaidPortal(self::portalClient(), $event->sftpLogin, $event->getPassword()));
        }
    }

    public static function portalClient(): ClientInterface
    {
        return new Client([
            'base_uri' => VtMedicaidPortal::BASE_URI,
            'timeout' => 120,
            'connect_timeout' => 20,
            // The portal is a website for browsers; a library's default agent may be turned away.
            'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) OpenEMR claim upload'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function list(string $global): array
    {
        $parts = preg_split('/[\s,]+/', $this->globals->getString($global), -1, PREG_SPLIT_NO_EMPTY);
        return $parts === false ? [] : $parts;
    }
}
