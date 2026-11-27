<?php

/**
 * Load the measure answers read from the reports and settle the questions they answer by themselves.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Command;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\Measure;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureSuggestion;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A run is the pair of files measure_compare.php writes (cms repo):
 * gifford-measure-run-<time>.json (the accessions asked) and its .results.jsonl beside it
 * (the answers). Both hold patient data and stay on the server. An answer of "no finding"
 * the reading was sure of settles its question; the others are shown to the coder on
 * Fees > Quality Measures. A question a coder has answered is never changed. Prints counts
 * only.
 */
final class ImportMeasuresCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('gifford:measure-import')
            ->setDescription('Load the quality measure answers read from the reports (a measure_compare.php run)')
            ->addUsage('--site=2400 --run=/tmp/gifford-measure-run-20261117-224303.json --dry-run')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Name of site', 'default')
            ->addOption('run', null, InputOption::VALUE_REQUIRED, 'Path of the run file (.json), as the container sees it; its .results.jsonl is beside it')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read the files and report without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runPath = $input->getOption('run');
        if (!is_string($runPath) || !str_ends_with($runPath, '.json')) {
            $output->writeln('<error>--run must name the run file (.json).</error>');
            return Command::INVALID;
        }
        $resultsPath = substr($runPath, 0, -5) . '.results.jsonl';
        $run = is_file($runPath) ? json_decode((string) file_get_contents($runPath), true) : null;
        if (!is_array($run) || !is_array($run['accessions'] ?? null) || !is_file($resultsPath)) {
            $output->writeln('<error>The run file or its .results.jsonl could not be read.</error>');
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

        $measures = new MeasureRepository();
        $now = ServiceContainer::getClock()->now();
        $source = basename($runPath, '.json');
        $counts = ['loaded' => 0, 'not staged' => 0, 'not answered' => 0, 'questions' => 0, 'stand alone' => 0, 'lines answered' => 0];
        foreach ($run['accessions'] as $id => $asked) {
            $accession = is_array($asked) && is_string($asked['accession'] ?? null) ? $asked['accession'] : '';
            $answer = $answers[(string) $id] ?? null;
            if ($accession === '' || !is_array($answer) || ($answer['outcome'] ?? null) !== 'succeeded') {
                $counts['not answered']++;
                continue;
            }
            $suggestions = self::suggestions($answer['answers'] ?? null);
            if ($suggestions === []) {
                $counts['not answered']++;
                continue;
            }
            if (QueryUtils::fetchSingleValue('SELECT `id` FROM `gifford_charge_line` WHERE `accession` = ? LIMIT 1', 'id', [$accession]) === null) {
                $counts['not staged']++;
                continue;
            }
            $counts['loaded']++;
            $counts['questions'] += count($suggestions);
            $counts['stand alone'] += count(array_filter($suggestions, static fn (MeasureSuggestion $s): bool => $s->standsAlone()));
            if (!$dryRun) {
                $measures->saveSuggestions($accession, $suggestions, $source, $now);
                $counts['lines answered'] += $measures->applySuggestions($accession, $now);
            }
        }

        $output->writeln(sprintf(
            '%s %d accessions in the run: %d loaded, %d not in a staged file, %d with no answer.',
            $dryRun ? 'Dry run, nothing written:' : 'Done:',
            count($run['accessions']),
            $counts['loaded'],
            $counts['not staged'],
            $counts['not answered']
        ));
        $output->writeln(sprintf(
            '  %d questions answered from the reports: %d a sure "no finding" that stands by itself, %d for a coder.',
            $counts['questions'],
            $counts['stand alone'],
            $counts['questions'] - $counts['stand alone']
        ));
        if (!$dryRun) {
            $output->writeln(sprintf(
                '  %d held lines answered now; a line not yet through a posting run is answered when it gets there.',
                $counts['lines answered']
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<MeasureSuggestion> the answers that name a measure and one of its options
     */
    private static function suggestions(mixed $answers): array
    {
        $found = [];
        foreach (is_array($answers) ? $answers : [] as $value => $a) {
            $measure = Measure::tryFrom((string) $value);
            $answer = is_array($a) && is_int($a['answer'] ?? null) ? $a['answer'] : null;
            if ($measure === null || !is_array($a) || $answer === null || $measure->option($answer) === null) {
                continue;
            }
            $text = static fn (string $key): string => is_string($a[$key] ?? null) ? $a[$key] : '';
            $found[] = new MeasureSuggestion($measure, $answer, $text('confidence'), $text('evidence'), $text('rationale'));
        }

        return $found;
    }
}
