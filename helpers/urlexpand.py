#
# The URL expander used to be a Python script. Now, it sets a flag in the database signaling we want URL expansion.
# URL expansion can be configurred through config.php though. This script can safely be disabled in cron, but this
# may require expanded permissions therefore we leave that up to the user.
#
import sys
import MySQLdb
import time
import re
import os.path

db_host = 'localhost'
db_user = 'tcatdbuser'
db_passwd = ''
db_db = 'twittercapture'

with open(os.path.dirname(__file__) + '/../config.php', 'r') as f:
    read_data = f.read()
    lines = read_data.split('\n')
    for line in lines:
        result = re.search('^\$dbuser *= *["\'](.*)["\']', line)
        if result:
            db_user = result.group(1)
        result = re.search('^\$dbpass *= *["\'](.*)["\']', line)
        if result:
            db_pass = result.group(1)
        result = re.search('^\$hostname *= *["\'](.*)["\']', line)
        if result:
            db_host = result.group(1)
        result = re.search('^\$database *= *["\'](.*)["\']', line)
        if result:
            db_db = result.group(1)
f.closed

db = MySQLdb.connect(host=db_host, user=db_user, passwd=db_pass, db=db_db)
cursor = db.cursor()

query = "INSERT INTO tcat_status ( variable, value ) SELECT 'enable_url_expander', 'true' FROM tcat_status WHERE NOT EXISTS ( SELECT * FROM tcat_status WHERE variable = 'enable_url_expander' ) LIMIT 1";
rs = cursor.execute(query)

time.sleep(3)
