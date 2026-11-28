<?php

/**
 * Which claim files the Claim File Tracker lets a biller send again or mark as
 * resolved, and how a row's stored messages are read.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Isolated\Billing;

use OpenEMR\Billing\BillingProcessor\X12RemoteTrackerActions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class X12RemoteTrackerActionsRulesTest extends TestCase
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
     * @return array<string, array{string, bool, bool}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function statusProvider(): array
    {
        return [
            'parameter error' => ['parameter-error', true, true],
            'claim file error' => ['claim-file-error', true, true],
            'login error' => ['login-error', true, true],
            'chdir error' => ['chdir-error', true, true],
            'upload error' => ['upload-error', true, true],
            'in progress' => ['in-progress', false, true],
            'waiting' => ['waiting', false, false],
            'sent' => ['success', false, false],
            'resolved' => ['resolved', false, false],
            'unknown' => ['something-else', false, false],
            'empty' => ['', false, false],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testWhatCanBeDoneAboutAFile(string $status, bool $sendAgain, bool $resolve): void
    {
        $this->assertSame($sendAgain, X12RemoteTrackerActions::canSendAgain($status));
        $this->assertSame($resolve, X12RemoteTrackerActions::canResolve($status));
    }

    /**
     * @return array<string, array{mixed, list<string>}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function messagesProvider(): array
    {
        return [
            'none stored' => [null, []],
            'empty text' => ['', []],
            'a stored list' => ['["one","two"]', ['one', 'two']],
            'a list already read' => [['one', 'two'], ['one', 'two']],
            'keys are dropped' => ['{"a":"one","b":"two"}', ['one', 'two']],
            'numbers become text' => ['[1,"two"]', ['1', 'two']],
            'nested values are dropped' => ['["one",["two"],null]', ['one']],
            'not JSON' => ['Could not log in', []],
            'a JSON scalar' => ['"one"', []],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('messagesProvider')]
    public function testDecodeMessages(mixed $stored, array $expected): void
    {
        $this->assertSame($expected, X12RemoteTrackerActions::decodeMessages($stored));
    }
}
