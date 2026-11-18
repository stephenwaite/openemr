<?php

/**
 * Load the diagnoses suggested for each accession from a dx_compare.php run.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Command;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxLists;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRule;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxSource;
use OpenEMR\BC\ServiceContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A run is the pair of files dx_compare.php writes: gifford-dx-run-<time>.json (the
 * accessions asked) and gifford-dx-run-<time>.results.jsonl beside it (the answers). Both
 * hold patient data and stay on the server. The charge file is staged first: what Gifford
 * sent for each accession (its diagnoses, codes and payer) is read from the staged lines.
 * Prints counts only.
 */
final class ImportDxCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('gifford:dx-import')
            ->setDescription('Load the diagnoses suggested from the reports (a dx_compare.php run) for the coders to confirm')
            ->addUsage('--site=2400 --run=/tmp/gifford-dx-run-20261116-021220.json --lists=/tmp/gifford-dx-lists.json --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('run', null, InputOption::VALUE_REQUIRED, 'Path of the run file (.json), as the container sees it; its .results.jsonl is beside it')
            ->addOption('lists', null, InputOption::VALUE_REQUIRED, 'Path of the coders\' workbook lists (gifford-dx-lists.json)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read the files and report without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runPath = $input->getOption('run');
        $listsPath = $input->getOption('lists');
        if (!is_string($runPath) || !is_string($listsPath) || !str_ends_with($runPath, '.json')) {
            $output->writeln('<error>--run must name the run file (.json) and --lists the workbook lists.</error>');
            return Command::INVALID;
        }
        $resultsPath = substr($runPath, 0, -5) . '.results.jsonl';
        $run = is_file($runPath) ? json_decode((string) file_get_contents($runPath), true) : null;
        if (!is_array($run) || !is_array($run['accessions'] ?? null) || !is_file($resultsPath)) {
            $output->writeln('<error>The run file or its .results.jsonl could not be read.</error>');
            return Command::FAILURE;
        }
        try {
            $lists = DxLists::fromFile($listsPath);
        } catch (\InvalidArgumentException) {
            $output->writeln('<error>--lists is not a lists file from gifford-dx-lists.py.</error>');
            return Command::FAILURE;
        }
        $dryRun = $input->getOption('dry-run') === true;

        $answers = [];
        foreach (file($resultsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $answer = json_decode($line, true);
            if (is_array($answer) && (is_string($answer['id'] ?? null) || is_int($answer['id'] ?? null))) {
                $answers[(string) $answer['id']] = $answer;
            }
        }

        $dx = new DxRepository();
        $now = ServiceContainer::getClock()->now();
        $source = basename($runPath, '.json');
        $counts = ['agreed' => 0, 'gifford' => 0, 'exam' => 0, 'pending' => 0, 'already confirmed' => 0, 'not staged' => 0, 'not answered' => 0];
        $why = [];
        foreach ($run['accessions'] as $id => $asked) {
            $accession = is_array($asked) && is_string($asked['accession'] ?? null) ? $asked['accession'] : '';
            $answer = $answers[(string) $id] ?? null;
            if ($accession === '' || !is_array($answer) || ($answer['outcome'] ?? null) !== 'succeeded') {
                $counts['not answered']++;
                continue;
            }
            $staged = $dx->staged($accession);
            if ($staged === null) {
                $counts['not staged']++;
                continue;
            }
            $suggested = self::suggested($answer['codes'] ?? null);
            $decision = DxRule::decide(
                $suggested,
                $staged['gifford'],
                $dx->invalidCodes(array_values(array_unique([DxRule::SCREENING_MAMMOGRAM, ...array_column($suggested, 'code'), ...$staged['gifford']]))),
                $staged['cpts'],
                DxRule::payerClass($staged['payer']),
                $staged['location'],
                $lists
            );
            foreach ($decision->reasons as $reason) {
                // Counts only: the invalid codes themselves are not printed.
                $reason = preg_replace('/not valid for coding: .*$/', 'a code is not valid for coding', $reason) ?? $reason;
                $why[$reason] = ($why[$reason] ?? 0) + 1;
            }
            if (!$dryRun && !$dx->saveSuggestion($accession, $suggested, $decision, $source, $now)) {
                $counts['already confirmed']++;
                continue;
            }
            $counts[match ($decision->source) {
                DxSource::Suggestion => 'agreed',
                DxSource::Gifford => 'gifford',
                DxSource::Exam => 'exam',
                DxSource::Coder => 'pending',
            }]++;
        }

        $output->writeln(sprintf(
            '%s %d accessions in the run: %d post as suggested, %d post with Gifford\'s codes, %d post by exam (screening mammogram), %d wait for a coder, %d a coder had already confirmed, %d not in a staged file, %d with no answer.',
            $dryRun ? 'Dry run, nothing written:' : 'Done:',
            count($run['accessions']),
            $counts['agreed'],
            $counts['gifford'],
            $counts['exam'],
            $counts['pending'],
            $counts['already confirmed'],
            $counts['not staged'],
            $counts['not answered']
        ));
        arsort($why);
        foreach ($why as $reason => $n) {
            $output->writeln(sprintf('  %-48s %d', $reason, $n));
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<array{code: string, confidence: string, rationale: string}>
     */
    private static function suggested(mixed $codes): array
    {
        $suggested = [];
        foreach (is_array($codes) ? $codes : [] as $item) {
            $code = is_array($item) && is_string($item['code'] ?? null) ? DxRule::normalize($item['code']) : '';
            if ($code === '' || !is_array($item) || in_array($code, array_column($suggested, 'code'), true)) {
                continue;
            }
            $suggested[] = [
                'code' => $code,
                'confidence' => is_string($item['confidence'] ?? null) ? $item['confidence'] : '',
                'rationale' => is_string($item['rationale'] ?? null) ? $item['rationale'] : '',
            ];
        }

        return array_slice($suggested, 0, DxRepository::MAX_CODES);
    }
}
