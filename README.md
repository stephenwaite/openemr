
# migrate to OpenEMR


1. for those accts who have people in the emr already, first need to put garnos in pubpid in emr
  run `interface/billing/getNumericPubpids.php

2. use above output as input to sid's program, `pubpid-garno.acu`, to try to match garnos, script is `die-38`

3. take output from above and update pubpid in openemr with `interface/reports/update_pubpid_garno.php default`

4. get last 3 years of garnos with charges, run scr-6 in sid land

5. create a list of those garnos not in emr

6. run `interface/billing/insco.php` to load insurance companies, it's input is the
   unload of insfile into `wsteve`

7. run `garfile.php` which needs `w1` from unload of garfile and `w2` from unload of medigaps,
which relies on `getInsuranceProvider()` in `library/patient.inc`

```
php7.3 /var/www/html/openemr/interface/billing/garfile.php default
```

