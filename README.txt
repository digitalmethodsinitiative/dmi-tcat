Notes on using ytk_analysis


Todo:
- retweets in linegraph
- BASE_URL vs $branch=DMI_PRODUCTION

*******************************************************************************************
Git repository
*******************************************************************************************
Clone
    git clone ssh://username@lab.digitalmethods.net/home/git/dmi-tcat.git


*******************************************************************************************
Config
*******************************************************************************************
Modify config.php to reflect your setup, after copying a template file
    cp dmi-tcat/config.php.local dmi-tcat/config.php
mkdir dmi-tcat/analysis/cache; chown 777;

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

