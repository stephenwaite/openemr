
# migrate to OpenEMR

0. first load insurance companies and x12 partners, it's input is the unload of insfile into `wsteve`, so copy wsteve to /tmp
```console
cd /home/<dir>
vutil -unl -t insfile wsteve
cp wsteve /tmp/.

php /var/www/html/openemr/interface/billing/insco.php <siteid>
```

1. for those accts who have people in the emr already, first need to put garnos in pubpid in emr, so we create /tmp/pubpidout
  ```console
  php /var/www/html/openemr/interface/billing/getNumericPubpids.php <siteid>
  ```

2. use above output as input to sid's program to try to match garnos, script is `/home/sidw/die-38`, output is `d1out`

```console
cd /home/<dir>
cp /tmp/pubpidout /tmp/d1.csv
die-38
joe d1out
```
3. take output from above and update pubpid in openemr with 
```console
cp d1out /tmp/.
php /var/www/html/openemr/interface/billing/update_pubpid.php <siteid>
```

4. get last 3 years of garnos with charges and ref provs, set date range in agedate, creates w11
```console
cd /home/<dir>
scr-6
cp w11 /tmp/.
```

5. now bring over all the goods, need `w1` from unload of garfile and `w2` from unload of medigaps,
```console
vutil -unl -t garfile /tmp/w1
vutil -unl -t /home/sidw/gapfile /tmp/w2
php /var/www/html/openemr/interface/billing/garfile.php <siteid>
```



