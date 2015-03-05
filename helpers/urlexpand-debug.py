import sys
import gevent
from gevent import socket
from gevent.pool import Pool
from gevent.timeout import Timeout
import urlparse
import sys
from random import shuffle
import MySQLdb
import time
from collections import deque
import re

from gevent import monkey
monkey.patch_all(thread=False)

import requests

# set socket timeout in seconds
socket_timeout = 7
socket.setdefaulttimeout(socket_timeout)

db_host = 'localhost'
db_user = 'root'
db_passwd = ''
db_db = 'twittercapture'

with open('../config.php', 'r') as f:
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

on_busy_wait = 20

finished = 0
working = {}
# For debugging, we have a pool of only 5 threads
pool = Pool(5)
updates = []

request_headers = {'User-agent': 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0'}

# Disable rate-limiting of requests to these domains:

whitelist = [ 'j.mp',
              'doubleclick.net',
              'ow.ly',
              'bit.ly',
              'goo.gl',
              'dld.bz',
              'tinyurl.com',
              'fp.me',
              'wp.me',
              'is.gd',
              'twitter.com',
              'tmblr.co'
            ]

def get_twitter_tables(table = None):
    if table is not None:
        query = "SHOW TABLES LIKE '%s'" % table
    else:
        query = "SHOW TABLES LIKE '%_urls'"
    cursor.execute(query)
    return [t[0] for t in cursor.fetchall()]


def get_urls_from_db(table):
    print 'DATABASE -- Getting urls from %s ...' % table
    # update_query = "UPDATE " + table + " SET url_expanded = url WHERE url_expanded IS NULL"
    # conn.query(update_query)
    query = "SELECT DISTINCT url_expanded FROM " + table  + """
            WHERE (domain IS NULL OR domain = '') 
            AND (error_code IS NULL OR error_code = '')
            AND (url_expanded != '' AND url_expanded IS NOT NULL)
            """
    rs = cursor.execute(query)
    urls = deque()
    for r in cursor.fetchall():
        urls.append(r[0])
    print 'DATABASE -- Returning %s urls from %s..' % (len(urls), table)
    return urls


def job(url, table):
    global finished
    # Use the domainname from url_expanded to rate limit requests to certain hostnames
    initialhost = urlparse.urlparse(url).hostname
    if initialhost.startswith('www.'):
        initialhost = initialhost[4:]

    print "child thread handling " + url

    try:
        resp = requests.get(url, headers=request_headers, timeout=socket_timeout, verify=False)
        url_followed = resp.url
        status_code = resp.status_code

        hostname = urlparse.urlparse(url_followed).hostname
        if hostname.startswith('www.'):
            hostname = hostname[4:]
       
        record = (url_followed, hostname, status_code, url)
        update_row(record, table)

    except (requests.exceptions.RequestException, requests.exceptions.ConnectionError, requests.exceptions.URLRequired, requests.exceptions.TooManyRedirects, requests.exceptions.Timeout) as e:
        record = ('', '', 0, url)
        update_row(record, table)

    finally:
        if initialhost not in whitelist:
            del working[initialhost]
        finished += 1


def update_row(record, table):
    #print "RESULTS -- %s, %s to insert into %s" % (record[2], record[0], table) 
    global updates
    updates.append(record)
    if len(updates) == 500:
        flush_db_queue(table)


def flush_db_queue(table):
    global updates
    query = "UPDATE " + table + " SET url_followed = %s, domain = %s, error_code = %s WHERE url_expanded = %s"        
    print "DATABASE -- Flushing %s records to the db" % len(updates)
    cursor.executemany(query, updates)
    updates[:] = []      


def main(argv = None):
    total = 0
    try:
        table = argv[0]
    except (TypeError, IndexError):
        print "No tablename provided"
        table = None
    for _table in get_twitter_tables(table):
        urls = get_urls_from_db(_table)
        total += len(urls)
        shuffle(urls)
        while len(urls):
            u = urls.popleft()
            host = urlparse.urlparse(u).hostname
            if host.startswith('www.'):
                host = host[4:]

            if working.has_key(host):
                time.sleep(0.25)
                # Useful debug line:
                print "sleeping with thread count " + str(5 - pool.free_count())
                urls.append(u)
                continue

            if host not in whitelist:
                working[host] = True
            pool.spawn(job, u, _table)

            print '' + str(total - finished) + ' urls left in queue'

        pool.join()
        
        # Flush left over updates in the queue to the db
        flush_db_queue(_table)    
            
    print ('RESULTS -- finished: %s/%s' % (finished, total))


if __name__ == '__main__':
    try:
        sys.argv[1]
    except IndexError:
        main()
    else:
        main(sys.argv[1:])
