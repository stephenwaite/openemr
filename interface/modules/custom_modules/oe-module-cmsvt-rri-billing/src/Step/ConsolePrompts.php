<?php

/**
 * Step 3's questions on the console.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreePrompts;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ConsolePrompts implements StepThreePrompts
{
    public function __construct(private SymfonyStyle $io)
    {
    }

    public function confirm(string $question, bool $default): bool
    {
        return $this->io->confirm($question, $default);
    }

    public function ask(string $question): string
    {
        $answer = $this->io->ask($question);
        return is_string($answer) ? $answer : '';
    }

    public function choose(string $question, array $choices): int
    {
        $answer = $this->io->choice($question, $choices);
        $index = array_search($answer, $choices, true);
        return is_int($index) ? $index : 0;
    }

    public function notice(string $message): void
    {
        $this->io->note($message);
    }

    public function warning(string $message): void
    {
        $this->io->warning($message);
    }
}
