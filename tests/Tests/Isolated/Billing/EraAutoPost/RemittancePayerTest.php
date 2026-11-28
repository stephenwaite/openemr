<?php

/**
 * The insurance company a remittance file is posted for by hand.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing\EraAutoPost;

use OpenEMR\Billing\EraAutoPost\CheckPoster;
use OpenEMR\Billing\EraAutoPost\CheckRecord;
use OpenEMR\Billing\EraAutoPost\CheckStatus;
use OpenEMR\Billing\EraAutoPost\RemittanceCheck;
use OpenEMR\Billing\EraAutoPost\RemittancePayer;
use OpenEMR\Billing\EraAutoPost\RemittanceReader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('isolated')]
class RemittancePayerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/remittance-payer-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testAFileIsPostedForTheCompanyAllItsChecksLeadTo(): void
    {
        $path = Era835::write($this->directory, 'a.edi', Era835::file(
            Era835::check('CHK1', '70.00', [['10-20', '1', '70.00']]),
            Era835::check('CHK2', '25.00', [['12-22', '1', '25.00']], control: '0002'),
        ));

        $this->assertSame(77, $this->payer(['CHK1' => '77', 'CHK2' => '77'])->forFile($path));
    }

    public function testNoCompanyIsOfferedWhenTheChecksDoNotAgreeOrOneLeadsNowhere(): void
    {
        $path = Era835::write($this->directory, 'a.edi', Era835::file(
            Era835::check('CHK1', '70.00', [['10-20', '1', '70.00']]),
            Era835::check('CHK2', '25.00', [['12-22', '1', '25.00']], control: '0002'),
        ));

        $this->assertSame(0, $this->payer(['CHK1' => '77', 'CHK2' => '88'])->forFile($path));
        // None of the second check's claims was matched to an insurance company.
        $this->assertSame(0, $this->payer(['CHK1' => '77', 'CHK2' => ''])->forFile($path));
        $this->assertSame(0, $this->payer(['CHK1' => '0', 'CHK2' => '0'])->forFile($path));
    }

    public function testNoCompanyIsOfferedForAFileThatIsMissingOrIsNotAnEra(): void
    {
        $notAnEra = Era835::write($this->directory, 'b.edi', 'not an 835');

        $this->assertSame(0, $this->payer([])->forFile($this->directory . '/missing.edi'));
        $this->assertSame(0, $this->payer([])->forFile($notAnEra));
    }

    /**
     * @param array<string, string> $payers the insurance company each check number leads to
     */
    private function payer(array $payers): RemittancePayer
    {
        $poster = new class ($payers) implements CheckPoster {
            /**
             * @param array<string, string> $payers
             */
            public function __construct(private readonly array $payers)
            {
            }

            public function payerFor(RemittanceCheck $check): string
            {
                return $this->payers[$check->checkNumber] ?? '';
            }

            public function postedForAnotherPayer(RemittanceCheck $check, string $payerId): bool
            {
                return false;
            }

            public function post(RemittanceCheck $check, string $payerId, int $postUser, \DateTimeImmutable $today): CheckRecord
            {
                return new CheckRecord($check, CheckStatus::Posted, null, $payerId, 0, []);
            }
        };

        return new RemittancePayer(new RemittanceReader(), $poster);
    }
}
