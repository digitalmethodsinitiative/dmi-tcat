import sys
import gevent
from gevent import socket
from gevent.pool import Pool
from gevent.timeout import Timeout
import urlparse
import sys
from random import shuffle
import MySQLdb

from gevent import monkey
monkey.patch_all(thread=False)

#import umysql
import urllib2
from urllib2 import HTTPError, URLError

# set socket timeout in seconds
timeout = 7
socket.setdefaulttimeout(timeout)

#conn = umysql.Connection()

db = MySQLdb.connect(host='localhost', user='', passwd='', db="twittercapture")
cursor = db.cursor()

finished = 0
pool = Pool(50)
updates = []


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
    urls = [r[0] for r in cursor.fetchall()]
    print 'DATABASE -- Returning %s urls from %s..' % (len(urls), table)
    return urls


def job(url, table):
    global finished
    try:
        resp = urllib2.urlopen(url)
        url_followed = resp.geturl()
        status_code = resp.getcode()

        hostname = urlparse.urlparse(url_followed).hostname
        if hostname.startswith('www.'):
            hostname = hostname[4:]
        
        record = (url_followed, hostname, status_code, url)
        update_row(record, table)

    except HTTPError as e:
        record = ('', '', e.code, url)
        update_row(record, table)

    except (URLError, Timeout) as e:
        record = ('', '', 0, url)
        update_row(record, table)
    
    except:
        record = ('', '', 0, url)
        update_row(record, table)     
         
    finally:
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
        for u in urls:
            pool.spawn(job, u, _table)

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
