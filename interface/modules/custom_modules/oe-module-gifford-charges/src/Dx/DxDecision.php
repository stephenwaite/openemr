<?php

/**
 * What DxRule decided for an accession: the codes that post, or why a coder confirms.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Dx;

final readonly class DxDecision
{
    /**
     * @param list<string> $codes the codes that post, in claim order; [] when a coder confirms
     * @param list<string> $reasons why a coder confirms; [] when the codes post
     */
    private function __construct(public DxSource $source, public array $codes, public array $reasons)
    {
    }

    /**
     * @param non-empty-list<string> $codes
     */
    public static function suggestion(array $codes): self
    {
        return new self(DxSource::Suggestion, $codes, []);
    }

    /**
     * @param non-empty-list<string> $codes
     */
    public static function gifford(array $codes): self
    {
        return new self(DxSource::Gifford, $codes, []);
    }

    /**
     * @param non-empty-list<string> $codes
     */
    public static function exam(array $codes): self
    {
        return new self(DxSource::Exam, $codes, []);
    }

    /**
     * @param non-empty-list<string> $reasons
     */
    public static function coder(array $reasons): self
    {
        return new self(DxSource::Coder, [], $reasons);
    }

    public function posts(): bool
    {
        return $this->source !== DxSource::Coder;
    }
}
