<?php

/**
 * Fixed-width text for the "PDF Custom" statement appearance.
 *
 * Lines are positioned to print over a full-page letterhead image (see
 * CustomPdfStatementPdf). Each statement starts with a 5-line address block,
 * then one line per charge, payment and adjustment, then a single aging line.
 * Long statements are split every 34 detail lines with a "CONTINUED PAGE n"
 * footer and a form feed; each statement ends with a form feed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\Statement;

use DateTimeImmutable;

final readonly class CustomPdfStatementText
{
    private const EOL = "\r\n";
    private const FORM_FEED = "\014";
    private const DETAIL_LINES_PER_PAGE = 34;
    private const NUM_AGES = 4;
    private const BAD_ADDRESS = '***BAD ADDRESS***';

    public function __construct(private DateTimeImmutable $today)
    {
    }

    /**
     * @param array<mixed> $stmt a statement as assembled by sl_eob_search.php
     */
    public function render(array $stmt): string
    {
        $amount = self::str($stmt['amount'] ?? '0.00');
        $out = $this->header($stmt, $amount);

        $count = 25;
        $aging = array_fill(0, self::NUM_AGES, 0.0);
        $todaysTime = $this->today->setTime(0, 0)->getTimestamp();
        // The newest payment date on the statement, which ages every line.
        $agedate = '';
        $detailCount = 0;
        $pageCount = 0;
        $continuedText = '';
        $lines = is_array($stmt['lines'] ?? null) ? $stmt['lines'] : [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $lineDos = self::str($line['dos'] ?? '');
            $dos = $lineDos;
            $description = substr(self::str($line['code_text'] ?? '') ?: self::str($line['desc'] ?? ''), 0, 42);
            $lineAmount = self::num($line['amount'] ?? 0);
            $linePaid = self::num($line['paid'] ?? 0);
            $details = is_array($line['detail'] ?? null) ? $line['detail'] : [];
            ksort($details);
            foreach ($details as $dkey => $ddata) {
                if (!is_array($ddata)) {
                    continue;
                }
                $out .= $continuedText;
                $continuedText = '';
                $ddate = substr((string) $dkey, 0, 10);
                if (preg_match('/^(\d\d\d\d)(\d\d)(\d\d)\s*$/', $ddate, $matches)) {
                    $ddate = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                }
                $method = self::str($ddata['pmt_method'] ?? '');
                $reason = self::str($ddata['rsn'] ?? '');
                $charge = self::num($ddata['chg'] ?? 0);
                if (self::num($ddata['pmt'] ?? 0) != 0) {
                    $dos = $ddate;
                    if ($dos > $agedate) {
                        $agedate = $dos;
                    }
                    $paid = sprintf('%.2f', self::num($ddata['pmt']));
                    $source = self::str($ddata['src'] ?? '');
                    if ($source === 'Pt Paid' || self::str($ddata['plv'] ?? '') === '0') {
                        $out .= sprintf('%-8s %-44s           %8s  ', $this->formatDate($dos), xl('Pt paid'), $paid) . self::EOL;
                    } else {
                        $desc = self::join([xl('Paid'), $source, $method]);
                        $out .= sprintf('%-8s %-44s           %8s', $this->formatDate($dos), $desc, $paid) . self::EOL;
                    }
                } elseif ($reason !== '') {
                    $dos = $ddate;
                    if ($charge != 0) {
                        $desc = self::join([xl('Adj'), $reason, $method]);
                        $adjusted = sprintf('%.2f', -$charge);
                    } else {
                        $desc = self::join([substr($reason, 0, 40), $method]);
                        $adjusted = '';
                    }
                    $out .= sprintf('%-8s %-44s           %8s', $this->formatDate($dos), $desc, $adjusted) . self::EOL;
                } elseif ($charge < 0) {
                    $out .= sprintf('%-8s %-44s           %8s', $this->formatDate($dos), xl('Patient Payment'), sprintf('%.2f', $charge)) . self::EOL;
                } else {
                    $dos = $lineDos;
                    $out .= sprintf(
                        '%-8s %-44s   %-8s          %-8s ',
                        $this->formatDate($dos),
                        $description,
                        str_pad(sprintf('%.2f', $charge), 7, ' ', STR_PAD_LEFT),
                        str_pad(sprintf('%.2f', $lineAmount - $linePaid), 7, ' ', STR_PAD_LEFT)
                    ) . self::EOL;
                }
                ++$count;
                ++$detailCount;
                if ($detailCount % self::DETAIL_LINES_PER_PAGE === 0) {
                    $pageCount++;
                    $continuedText = self::EOL . self::EOL
                        . sprintf('                       %.2f', self::num($amount))
                        . 'CONTINUED PAGE ' . $pageCount . ' ' . self::EOL
                        . self::FORM_FEED;
                }
            }
            if ($agedate === '') {
                $agedate = $dos;
            }
            $ageIndex = 0;
            if (self::num($stmt['dun_count'] ?? 0) != 0) {
                $ageTime = strtotime($agedate);
                $ageInDays = $ageTime === false ? 0 : intdiv($todaysTime - $ageTime, 60 * 60 * 24);
                $ageIndex = max(0, min(self::NUM_AGES - 1, intdiv($ageInDays - 1, 30)));
            }
            $aging[$ageIndex] += $lineAmount - $linePaid;
        }

        while ($count++ < 62) {
            $out .= self::EOL;
        }

        // Total, current, 31-60, 61-90, over 90, total: positioned for the letterhead's aging boxes.
        $ageline = sprintf(' %7.2f %10s %7.2f', self::num($amount), '', $aging[0]);
        for ($ageIndex = 1; $ageIndex < self::NUM_AGES - 1; ++$ageIndex) {
            $ageline .= sprintf('   %7.2f', $aging[$ageIndex]);
        }
        $ageline .= sprintf('      %.2f              %.2f', $aging[self::NUM_AGES - 1], self::num($amount));

        return $out . $ageline . self::EOL . self::FORM_FEED;
    }

    /**
     * @param array<mixed> $stmt
     */
    private function header(array $stmt, string $amount): string
    {
        // 'to' is the addressee name, the street lines present, then city/state/zip.
        $to = is_array($stmt['to'] ?? null) ? array_values(array_map(self::str(...), $stmt['to'])) : [];
        $name = $to[0] ?? '';
        $cityStateZip = count($to) > 1 ? $to[count($to) - 1] : '';
        $streets = array_slice($to, 1, max(0, count($to) - 2));
        $street1 = self::addressLine($streets[0] ?? '');
        $street2 = self::addressLine($streets[1] ?? '');
        if ($street1 === '' && $street2 === '') {
            $street1 = self::BAD_ADDRESS;
        }
        $date = $this->today->format('m d y');

        $out = sprintf('%-9s %-55s %6s ', '', strtoupper($name), self::str($stmt['pid'] ?? '')) . self::EOL;
        $out .= sprintf('%-9s %-43s %-8s ', '', $street1, $date) . self::EOL;
        $out .= ($street2 !== '' ? sprintf('%-9s %-43s ', '', $street2) : '') . self::EOL;
        $out .= sprintf('%-9s %-43s %-8s %9s', '', strtoupper($cityStateZip), $date, $amount) . self::EOL;
        return $out . self::EOL . self::EOL;
    }

    private function formatDate(string $date): string
    {
        $time = strtotime($date);
        return $time === false ? '' : date('m d y', $time);
    }

    private static function addressLine(string $line): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim($line)));
    }

    /**
     * @param list<string> $parts
     */
    private static function join(array $parts): string
    {
        return implode(' ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function num(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
