<?php

// try to update schema
$ignoreAuth = true; // no login required

require_once('interface/globals.php');

// change database default collation
sqlStatement("ALTER DATABASE `openemr` CHARACTER SET utf8 COLLATE utf8_general_ci;");

// change table collation/char set
$sql = "SELECT concat('ALTER TABLE `', TABLE_SCHEMA, '`.`', table_name, '` CHARACTER SET utf8 COLLATE utf8_general_ci;')
from information_schema.tables where TABLE_SCHEMA like 'openemr';";
    
echo $sql;

$res = sqlStatement($sql);

while ($row = sqlFetchArray($res)) {
    foreach($row as $query) {
        echo $query;
        sqlStatement($query);
    }
}

// change colun collation/char set
$sql = "SELECT concat('ALTER TABLE `', t1.TABLE_SCHEMA, '`.`', t1.table_name, '` MODIFY `', t1.column_name, '` ', t1.data_type , '(' , t1.CHARACTER_MAXIMUM_LENGTH , ')' , ' CHARACTER SET utf8 COLLATE utf8_general_ci;') " .
"from information_schema.columns t1 where t1.TABLE_SCHEMA like 'openemr' and t1.COLLATION_NAME = 'latin1_swedish_ci';";

echo $sql;
$res = sqlStatement($sql);

while ($row = sqlFetchArray($res)) {
    foreach($row as $query) {
        echo $query;
        sqlStatement($query);
    }
}

