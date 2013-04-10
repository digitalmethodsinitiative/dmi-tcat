*******************************************************************************************
Config
*******************************************************************************************
Modify config.php to reflect your setup, after copying a template file
    cp dmi-tcat/config.php.example dmi-tcat/config.php

Make analysis cache dir and make it writable by web server
	mkdir dmi-tcat/analysis/cache; chown 777 dmi-tcat/analysis/cache;

Make sure custom settings in dmi-tcat/analysis/common/config.php are ok

Make capture log dir  writeable by web server
	chown dmi-tcat/capture/stream/logs

Make sure the right directories are mentioned in dmi-tcat/capture/stream/config.php // @todo

Add queries to be captured with the stream
    cp dmi-tcat/querybins.php.example dmi-tcat/querybins.php

Make crontab for capturing tweets from the stream (controller.php then checks each minute whether the capture script is still running)
* * * * * php /var/www/dmi-tcat/capture/stream/controller.php

Make crontab for expanding URLs
0 * * * * . /home/eelke/urlexpand.sh


*******************************************************************************************
Logging in to coword.digitalmethods.net
*******************************************************************************************
Open first terminal screen and set up tunnel to the coword machine (via temlab.digitalmethods.net)
    ssh -L4022:coword:22 temlab.digitalmethods.net
Open second terminal to ssh into the coword machine
    ssh -p4022 localhost
    OR point your code editor to work with sftp/scp on port 4022


*******************************************************************************************
Updating database on coword.digitalmethods.net
*******************************************************************************************
export table from lab.digitalmethods.net (the server aggregating data through yourTwapperKeeper)
    mysqldump -h 82.94.190.198 -u ytk -p yourTwapperKeeper z_XXX | bzip2 > yourTwapperKeeper.z_XXX.DATE.sql.bz2
get pass from
    cat common/config.php
	
import table on coword.digitalmethods.net
    bunzip2 yourTwapperKeeper.z_XXX.DATE.sql.bz2
    mysql -u ytk -p yourTwapperKeeper < yourTwapperKeeper.z_XXX.DATE.sql

