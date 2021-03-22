# OpenEMR upgrade Lane Fertility Clinic

1. launch aws cloud standard, chose 200 G for documents, 50 G in the os volume for moving large files around

2. add your local ip to the security group so you can open up ssh and phpmyadmin

3. ssh into your instance with your keys

> need super user so  `sudo bash`

4. do the ubuntu upgrades, might make sense to upgrade to ubuntu 20 later

5. build your own docker phpmyadmin image with the let's encrypt keys from the openemr docker
```
docker cp d62e382d3794:/etc/letsencrypt/archive/cmsvt.dev/cert1.pem certs/cert.pem
docker cp d62e382d3794:/etc/letsencrypt/archive/cmsvt.dev/privkey1.pem certs/privkey.pem
docker cp d62e382d3794:/etc/letsencrypt/archive/cmsvt.dev/chain1.pem certs/fullchain.pem
```


```
FROM phpmyadmin/phpmyadmin

RUN a2enmod ssl

RUN sed -ri -e 's,80,443,' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e '/^<\/VirtualHost>/i SSLEngine on' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e '/^<\/VirtualHost>/i SSLCertificateFile /cert/cert.pem' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e '/^<\/VirtualHost>/i SSLCertificateKeyFile /cert/privkey.pem' /etc/apache2/sites-available/000-default.conf
RUN sed -i -e '/^<\/VirtualHost>/i SSLCertificateChainFile /cert/fullchain.pem' /etc/apache2/sites-available/000-default.conf

EXPOSE 443
```

6. 
`docker run -d -p 8080:443 -e PMA_HOST='crazy_host_name_rds.amazonaws.com' -e UPLOAD_LIMIT=16G -v /home/stee/certs:/cert:ro my_pma_ssl_image`

7. log in to phpmyadmin at port 8080 of your public ip with openemr and the aws password created from the standard template

8. drop database openemr; create database openemr; with utf8_general_ci collation

9. import old 4.1.2 database, drop duplicate `MEETING` calendar category from `openemr_postcalendar_categories`

```
CREATE TABLE `modules` (
  `mod_id` INT(11) NOT NULL AUTO_INCREMENT,
  `mod_name` VARCHAR(64) NOT NULL DEFAULT '0',
  `mod_directory` VARCHAR(64) NOT NULL DEFAULT '',
  `mod_parent` VARCHAR(64) NOT NULL DEFAULT '',
  `mod_type` VARCHAR(64) NOT NULL DEFAULT '',
  `mod_active` INT(1) UNSIGNED NOT NULL DEFAULT '0',
  `mod_ui_name` VARCHAR(20) NOT NULL DEFAULT '''',
  `mod_relative_link` VARCHAR(64) NOT NULL DEFAULT '',
  `mod_ui_order` TINYINT(3) NOT NULL DEFAULT '0',
  `mod_ui_active` INT(1) UNSIGNED NOT NULL DEFAULT '0',
  `mod_description` VARCHAR(255) NOT NULL DEFAULT '',
  `mod_nick_name` VARCHAR(25) NOT NULL DEFAULT '',
  `mod_enc_menu` VARCHAR(10) NOT NULL DEFAULT 'no',
  `permissions_item_table` CHAR(100) DEFAULT NULL,
  `directory` VARCHAR(255) NOT NULL,
  `date` DATETIME NOT NULL,
  `sql_run` TINYINT(4) DEFAULT '0',
  `type` TINYINT(4) DEFAULT '0',
  PRIMARY KEY (`mod_id`,`mod_directory`)
) ENGINE=InnoDB;
```


10. open a shell in the openemr docker and create a quick /bin/sh to grab the 5.0.2 upgrade scripts

```
#!/bin/sh

# retrieve files deleted for security
OE_INSTANCE=$(docker ps | grep _openemr | cut -f 1 -d " ")
docker exec -it $OE_INSTANCE sh -c 'curl -L https://raw.githubusercontent.com/openemr/openemr/v5_0_1/sql_upgrade.php > /var/www/localhost/htdocs/openemr/sql_upgrade.php'
docker exec -it $OE_INSTANCE sh -c 'curl -L https://raw.githubusercontent.com/openemr/openemr/v5_0_1/acl_upgrade.php > /var/www/localhost/htdocs/openemr/acl_upgrade.php'
docker exec $OE_INSTANCE chown apache:root /var/www/localhost/htdocs/openemr/sql_upgrade.php /var/www/localhost/htdocs/openemr/acl_upgrade.php
docker exec $OE_INSTANCE chmod 400 /var/www/localhost/htdocs/openemr/sql_upgrade.php /var/www/localhost/htdocs/openemr/acl_upgrade.php
```

11. upload document tar ball, first chown ubuntu /mnt/docker so can upload as ubuntu, should change back when done cp'ing docs in

12. cp'ing the openemr tarball into the docker, docker cp <file.tgz> container:<dir with plenty of room>, extract the documents into place, with strip-components relative to openemr webroot, ie
`tar xvfz openemr.tar.gz --strip-components=1 openemr/sites/default/documents`

13. go to the upgrade script, `http://<your ip>/sql_upgrade.php`
choose 4.1.2 and run it

14. reset password to pass with salt for old version for all active users

```
UPDATE users_secure SET password = '$2a$05$MKtnxYsfFPlb2mOW7Qzq2Oz61S26s5E80Yd60lKdX4Wy3PBdEufNu', salt = '$2a$05$MKtnxYsfFPlb2mOW7Qzq2b$' WHERE username = 'admin'
```

14. restore mistakenly deleted field to demographics layout via `patient_data` table

```
  `hipaa_allowsms` VARCHAR(3) NOT NULL DEFAULT 'NO',
```

15. 



### License

[GNU GPL](LICENSE)
