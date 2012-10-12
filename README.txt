Notes on using ytk_analysis

Todo:
- extract variability of association from mod.hashtag
- Warning: urls have not been imported on temlab or localhost
- what is difference between absolute weighting and coocurrence weight normalization?
- encoding of tweets (current coword class can only tokenize tweets which consists fully out of latin characters; allmost all other tweets are discarded.)
- BASE_URL vs $branch=DMI_PRODUCTION
- CowordOnTools.php calls coword (persistent version) but uses 100% CPU

*******************************************************************************************
Git repository
*******************************************************************************************
Init
    git clone ssh://username@lab.digitalmethods.net/home/git/ytk_analysis


*******************************************************************************************
Config
*******************************************************************************************
Modify config.php to reflect your setup, after copying a template file
    cp common/config.php.local common/config.php
    mkdir files; chown 777;

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
    mysqldump -h 82.94.190.198 -u ytk -p yourTwapperKeeper z_XXX | bzip2 > yourTwapperKeeper.z_XXX.DATE.sql.bz2
get pass from
    cat common/config.php
	
import table on coword.digitalmethods.net
    bunzip2 yourTwapperKeeper.z_XXX.DATE.sql.bz2
    mysql -u ytk -p yourTwapperKeeper < yourTwapperKeeper.z_XXX.DATE.sql

