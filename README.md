## zero thing
edit sqlconf.php, don't forget change 0 to 1 after mysql import

## first thing
update procedure providers by saving them

## 2nd thing
make labs subdir in sites/default

### for pdftk to print non quest labs
sudo snap install pdftk
sudo ln -fs /snap/pdftk/current/usr/bin/pdftk /usr/bin/pdftk

### for deploy test to cmsvt.dev

follow phpmyadmin guide at hakimab

locally import into docker so can trim in phpmyadmin
truncate `log` before exporting sql to shrink the size of upload

really slow to restore in the cloud standard package




