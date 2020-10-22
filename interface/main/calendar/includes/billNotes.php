<?php

// Copyright (C) 2013-2016 Mark Kuperman <mkuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
require_once("{$GLOBALS['srcdir']}/pnotes.inc");

function billNotes($pid) {
    $r = sqlQuery("SELECT genericname2, genericval2 FROM patient_data where pid = ?", array($pid));
    $note = ($r['genericname2'] == 'Billing') ? $r['genericval2'] : '';
    if (strcmp($note, 'IN COLLECTIONS') != 0)
        $ret = $note . "\n";
    else
        $ret = '';
    
    $ar = getPNotesByPid($pid);
    if (count($ar) > 0)
        $ret .= "Bill/Collect Notes:" . $ret;
    else 
        $ret .= "No Bill/Collect Notes" . $ret;
    
    foreach ($ar as $k => $v) {
        if ($v['title'] == 'Bill/Collect') {
            $ret .= "\n" . $v['body'];
            $em = 1;
        }
    }
    return $ret;
}

?>
