<?php

/**
 * Lays out "PDF Custom" statement text (CustomPdfStatementText) over a
 * full-page letterhead image, one or more pages per statement.
 *
 * Each page gets the statement's 5-line address block at the top, the detail
 * lines in the middle and the aging line at the bottom. A statement split by
 * "CONTINUED" markers is re-flowed: its detail lines are gathered and printed
 * in pages of up to 40 lines, and the aging line goes on the last page.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\Statement;

use Cezpdf;

final class CustomPdfStatementPdf
{
    private const HEADER_LINES = 5;
    private const FONT_SIZE = 12;
    private const BODY_OFFSET = 130;
    private const FOOTER_OFFSET = 570;

    private readonly Cezpdf $pdf;
    private bool $anyPagePrinted = false;

    /**
     * @param string $letterheadPng full-page (612x792 pt) PNG printed behind every page; '' for none
     */
    public function __construct(private readonly string $letterheadPng)
    {
        $this->pdf = new Cezpdf('LETTER');
        $this->pdf->ezSetMargins(170, 0, 10, 0);
        $this->pdf->selectFont('Courier');
    }

    public function render(string $text): string
    {
        $isContinued = false;
        $heldBody = '';
        $header = '';
        $heldBodyCount = 0;
        foreach (explode("\014", $text) as $page) {
            $pageLines = explode("\n", $page);
            $lineCount = count($pageLines);
            if ($lineCount === 1 && $pageLines[0] === '') {
                continue;
            }
            $wasContinued = $isContinued;
            $isContinued = str_contains($page, 'CONTINUED');
            if (!$isContinued && !$wasContinued) {
                $header = '';
            }
            if (!$wasContinued) {
                for ($i = 0; $i < self::HEADER_LINES; $i++) {
                    $header .= $pageLines[$i] ?? '';
                }
            }

            $body = '';
            $bodyCount = 0;
            for ($i = self::HEADER_LINES; $i < $lineCount - 4; $i++) {
                $body .= $pageLines[$i];
                $bodyCount++;
            }

            if ($isContinued) {
                $footer = "CONTINUED \r\n";
            } else {
                $footer = '';
                for ($i = $lineCount - 2; $i < $lineCount; $i++) {
                    $footerLine = $pageLines[$i] ?? '';
                    $footer .= $footerLine === '' ? "\r" : $footerLine;
                }
            }

            if (!$isContinued && !$wasContinued) {
                // A statement that fits on one page.
                $this->printPage($header, $body, $footer);
                $header = '';
            } elseif ($isContinued && !$wasContinued) {
                // First part of a long statement: hold its lines.
                $heldBody = $body;
                $heldBodyCount = $bodyCount;
            } elseif ($isContinued) {
                // A middle part: print a page once the held lines would overflow.
                $heldBodyCount += $bodyCount;
                if ($heldBodyCount < 41) {
                    $heldBody .= $body;
                } else {
                    $this->printPage($header, $heldBody, $footer);
                    $heldBody = "\r" . $body;
                    $heldBodyCount = $bodyCount;
                }
            } else {
                // The last part: print what is held, with the aging line.
                $heldBodyCount += $bodyCount;
                if ($heldBodyCount < 35) {
                    $this->printPage($header, $heldBody . $body, $footer);
                } else {
                    $this->printPage($header, $heldBody, $footer);
                    $this->printPage($header, "\r" . $body, $footer);
                }
                $heldBody = '';
                $heldBodyCount = 0;
                $header = '';
            }
        }
        return $this->pdf->ezOutput();
    }

    private function printPage(string $header, string $body, string $footer): void
    {
        if ($this->anyPagePrinted) {
            $this->pdf->ezNewPage();
        }
        $this->anyPagePrinted = true;
        $ez = $this->pdf->ez;
        $pageHeight = is_array($ez) && is_numeric($ez['pageHeight'] ?? null) ? (float) $ez['pageHeight'] : 792.0;
        $topMargin = is_array($ez) && is_numeric($ez['topMargin'] ?? null) ? (float) $ez['topMargin'] : 0.0;
        $top = $pageHeight - $topMargin;
        $options = ['justification' => 'left', 'leading' => self::FONT_SIZE];

        $this->pdf->ezSetY($top);
        // Cezpdf can only place PNGs here; the Statement Logo global defaults to a GIF.
        if (str_ends_with(strtolower($this->letterheadPng), '.png') && is_file($this->letterheadPng)) {
            $this->pdf->addPngFromFile($this->letterheadPng, 0, 0, 612, 792);
        }
        $this->pdf->ezText($header, self::FONT_SIZE, $options);
        $this->pdf->ezSetY($top - self::BODY_OFFSET);
        $this->pdf->ezText($body, self::FONT_SIZE, $options);
        $this->pdf->ezSetY($top - self::FOOTER_OFFSET);
        $this->pdf->ezText($footer, self::FONT_SIZE, $options);
    }
}
