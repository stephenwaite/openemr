<?php

/**
 * Database-backed tests for the insurance policy search.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\Search\TokenSearchField;
use OpenEMR\Tests\Fixtures\FixtureManager;
use PHPUnit\Framework\TestCase;

class InsuranceServiceTest extends TestCase
{
    private const COMPANY = 'ZZTEST insurance company';

    private InsuranceService $service;
    private FixtureManager $fixtureManager;
    private int $patientPid;
    private int $companyId;

    protected function setUp(): void
    {
        $this->service = new InsuranceService();
        $this->fixtureManager = new FixtureManager();
        $this->fixtureManager->installPatientFixtures();

        $patient = QueryUtils::querySingleRow(
            "SELECT `pid` FROM `patient_data` WHERE `pubpid` LIKE ? LIMIT 1",
            [FixtureManager::PATIENT_FIXTURE_PUBPID_PREFIX . "%"]
        );
        if (!is_array($patient) || !is_numeric($patient['pid'] ?? null)) {
            self::fail('patient fixture not found');
        }
        $this->patientPid = (int) $patient['pid'];
        $this->companyId = (int) QueryUtils::sqlInsert(
            "INSERT INTO `insurance_companies` (`name`) VALUES (?)",
            [self::COMPANY]
        );
    }

    protected function tearDown(): void
    {
        QueryUtils::sqlStatementThrowException("DELETE FROM `insurance_data` WHERE `pid` = ?", [$this->patientPid]);
        QueryUtils::sqlStatementThrowException("DELETE FROM `insurance_companies` WHERE `id` = ?", [$this->companyId]);
        $this->fixtureManager->removePatientFixtures();
    }

    private function insertPolicy(string $type, string $provider): void
    {
        $uuid = UuidRegistry::getRegistryForTable('insurance_data')->createUuid();
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `insurance_data` (`uuid`, `type`, `provider`, `pid`, `date`) VALUES (?, ?, ?, ?, NULL)",
            [$uuid, $type, $provider, $this->patientPid]
        );
    }

    /**
     * @return array<string, mixed> provider by policy type
     */
    private function searchProvidersByType(): array
    {
        $providers = [];
        $policies = $this->service->search(['pid' => new TokenSearchField('pid', (string) $this->patientPid)])->getData();
        $this->assertIsArray($policies);
        foreach ($policies as $policy) {
            if (is_array($policy) && is_string($policy['type'] ?? null)) {
                $this->assertArrayNotHasKey('insurerid', $policy, 'the join column is not part of the result');
                $providers[$policy['type']] = $policy['provider'] ?? null;
            }
        }
        return $providers;
    }

    public function testZeroPaddedProviderIsReturnedAsTheCompanyId(): void
    {
        // insurance_data.provider is a varchar, and databases from older releases hold
        // the company id zero-padded ("003"), which SQL still joins but a string compare doesn't.
        $this->insertPolicy('primary', str_pad((string) $this->companyId, strlen((string) $this->companyId) + 2, '0', STR_PAD_LEFT));
        $this->insertPolicy('secondary', (string) $this->companyId);

        $providers = $this->searchProvidersByType();

        $this->assertSame((string) $this->companyId, $providers['primary'] ?? null);
        $this->assertSame((string) $this->companyId, $providers['secondary'] ?? null);
    }

    public function testProviderWithNoCompanyIsReturnedAsStored(): void
    {
        $this->insertPolicy('primary', '999999999');

        $providers = $this->searchProvidersByType();

        $this->assertSame('999999999', $providers['primary'] ?? null);
    }
}
