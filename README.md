
# migrate to OpenEMR

1. select numeric pubpids since most patients already have garnos
`select concat('',pubpid * 1) from patient_data where concat('',pubpid * 1) <> 0;`

2. input to sid's program, `pubpid-garno.acu`, to find garnos, script is `die-38`

3. run below from command line to read flat file, `d1out`,
with numeric pubpid and garno and update openemr `patient_data`

```
php7.3 /var/www/html/openemr/interface/reports/update_pubpid_garno.php default
```

4. run `interface/billing/insco.php` to load insurance companies, it's input is the
   unload of insfile into `wsteve`

5. run `garfile.php` which needs `w1` from unload of garfile and `w2` from unload of medigaps,
which relies on `getInsuranceProvider()` in `library/patient.inc`


