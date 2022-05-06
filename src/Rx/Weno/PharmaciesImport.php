<?php

/*
 *  @package OpenEMR
 *  @link    http://www.open-emr.org
 *  @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @copyright Copyright (c) 2020 Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Rx\Weno;

use Pharmacy;

class PharmaciesImport
{
    private $filename;
    private $state;

    public function __construct()
    {
        $this->filename = "/tmp/PharmacyDirectory.zip";
        $this->state = $this->getState();
    }

    /**
     * @return string
     */
    public function importPharmacy()
    {
        $i = 0;
        if (file_exists($this->filename)) {
            $za = new \ZipArchive();
            $za->open($this->filename);
            $za->extractTo('/tmp');
            $za_filename = '/tmp' . "/" . $za->statIndex(0)['name'];

            $import = fopen($za_filename, "r");
            while (! feof($import)) {
                $line = fgetcsv($import);
                if (($line[12] ?? null) === $this->state) {
                    $pharmacy = new Pharmacy();
                    $pharmacy->set_id();
                    $pharmacy->set_name($line[8]);
                    // if the 2020 file was imported then the
                    // npi and ncpdp are reversed in the db
                    // todo: add an update mechanism here so
                    // doesn't create duplicate pharmacies
                    $pharmacy->set_ncpdp(substr($line[3],1,7));
                    $pharmacy->set_npi($line[6]);
                    $pharmacy->set_address_line1($line[9]);
                    $pharmacy->set_city($line[11]);
                    $pharmacy->set_state($line[12]);
                    $pharmacy->set_zip(substr($line[14],1,5));
                    $pharmacy->set_fax($line[21]);
                    $pharmacy->set_phone($line[19]);
                    $pharmacy->set_transmit_method("4");
                    $pharmacy->persist();
                    ++$i;
                }
            }
            fclose($import);
            return "imported";
        } else {
            return "file not found";
        }
    }

    private function getState()
    {
        $sql = "select state from facility";
        $res = sqlQuery($sql);
        return $res['state'];
    }
}
