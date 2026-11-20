<?php

/**
 * Queries behind the Press Ganey export page.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\PressGaney;

use OpenEMR\Common\Database\QueryUtils;

final readonly class PressGaneyRepository
{
    /**
     * @return list<array<mixed>>
     */
    public function fetchEncounters(PressGaneyExportFilter $filter): array
    {
        [$where, $binds] = $this->where($filter);
        return QueryUtils::fetchRecords(
            "SELECT DISTINCT fe.encounter, fe.date AS visit_date,
                    p.lname, p.fname, p.mname, p.pubpid, p.DOB, p.sex, p.street, p.street_line_2,
                    p.city, p.state, p.postal_code, p.phone_home, p.phone_cell, p.email,
                    fac.id AS facility_id, fac.name AS facility_name, fac.street AS facility_street,
                    fac.city AS facility_city, fac.state AS facility_state,
                    fac.postal_code AS facility_postal_code,
                    u.lname AS provider_lname, u.fname AS provider_fname, u.npi AS provider_npi,
                    u.physician_type, u.taxonomy AS specialty
               FROM form_encounter AS fe
          LEFT JOIN patient_data AS p ON p.pid = fe.pid
          LEFT JOIN users AS u ON u.id = fe.provider_id
          LEFT JOIN facility AS fac ON fac.id = fe.facility_id
              WHERE $where
           ORDER BY fe.date, p.lname, p.fname",
            $binds
        );
    }

    public function countEncounters(PressGaneyExportFilter $filter): int
    {
        [$where, $binds] = $this->where($filter);
        $count = QueryUtils::fetchSingleValue(
            "SELECT COUNT(DISTINCT fe.encounter) AS count FROM form_encounter AS fe WHERE $where",
            'count',
            $binds
        );
        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @return list<array<mixed>>
     */
    public function providers(): array
    {
        return QueryUtils::fetchRecords(
            "SELECT id, lname, fname FROM users WHERE authorized = 1 ORDER BY lname, fname"
        );
    }

    /**
     * @return list<array<mixed>>
     */
    public function facilities(): array
    {
        return QueryUtils::fetchRecords("SELECT id, name FROM facility ORDER BY name");
    }

    /**
     * @return list<array<mixed>>
     */
    public function encounterCategories(): array
    {
        return QueryUtils::fetchRecords(
            "SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories WHERE pc_active = 1 ORDER BY pc_catname"
        );
    }

    /**
     * @return array{string, list<int|string>}
     */
    private function where(PressGaneyExportFilter $filter): array
    {
        $where = 'fe.date >= ? AND fe.date <= ?';
        $binds = [$filter->fromDate . ' 00:00:00', $filter->toDate . ' 23:59:59'];
        if ($filter->providerId !== null) {
            $where .= ' AND fe.provider_id = ?';
            $binds[] = $filter->providerId;
        }
        if ($filter->facilityId !== null) {
            $where .= ' AND fe.facility_id = ?';
            $binds[] = $filter->facilityId;
        }
        if ($filter->categoryId !== null) {
            $where .= ' AND fe.pc_catid = ?';
            $binds[] = $filter->categoryId;
        }
        return [$where, $binds];
    }
}
