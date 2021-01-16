
# migrate to OpenEMR

1. select numeric pubpids so can find garnos
`select concat('',pubpid * 1) from patient_data where concat('',pubpid * 1) <> 0;`

2. run `interface/reports/match_pubpid_garno.php` from command line to generate flat file
   to input to sid's program, `pubpid-garno.acu`, to find garnos

```
php7.3 /var/www/html/lkuperman.com/openemr/interface/reports/match_pubpid_garno.php default
```

3. create flat file with garno and pubpid and run below script
```
php7.3 /var/www/html/lkuperman.com/openemr/interface/reports/update_pubpid_garno.php default
```

4.
