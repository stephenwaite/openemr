# move dier billing to openemr

`SELECT * FROM `form_encounter` WHERE `last_level_billed` = 0 and `date` > '2021-01-11'`

`UPDATE `form_encounter` SET `last_level_billed`= 99 WHERE `date` < '2021-01-12'`

## step 1

run interface/billing/update_pubpid.php with input `l1out` from the output of `x-l` under `/home/die` which is fed `qstee.csv` from modified encounters report

## step 2

run `interface/billing/insco.php` to load insurance companies, it's input is the unload of insfile into `wsteve`

## step 3

run  `import_cpts_dxs.php` which needs `q5` from `sid-l` under dier to load the cpts and dxs from charcur

## step 4

run `garfile.php` which needs `w1` from unload of garfile
