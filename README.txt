Notes on using ytk_analysis


*******************************************************************************************
Git repository
*******************************************************************************************
Init
    git clone ssh://username@lab.digitalmethods.net/home/git/ytk_analysis


*******************************************************************************************
Config
*******************************************************************************************
Modify config.php to reflect your setup, after copying a template file
    cp common/config.php.lab common/config.php


*******************************************************************************************
Logging in to coword.digitalmethods.net
*******************************************************************************************
Open first terminal screen and set up tunnel to the coword machine (via temlab.digitalmethods.net)
    ssh -L4022:coword:22 temlab.digitalmethods.net
Open second terminal to ssh into the coword machine
    ssh -p4022 localhost


*******************************************************************************************
Updating database on coword.digitalmethods.net
*******************************************************************************************
export table from lab.digitalmethods.net (the server aggregating data through yourTwapperKeeper)
    mysqldump -h 82.94.190.198 -u ytk -p yourTwapperKeeper z_XXX | bzip2 > /home/erik/data/yourTwapperKeeper.z_XXX.DATE.sql.bz2
get pass from
    cat common/config.php
	
import table on coword.digitalmethods.net
    mysql -u ytk -p yourTwapperKeeper < /home/erik/data/yourTwapperKeeper.z_XXX.DATE.sql.bz2

