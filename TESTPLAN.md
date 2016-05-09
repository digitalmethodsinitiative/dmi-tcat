# TCAT Test Plan

**DRAFT**

## Introduction

This is a test plan for TCAT.

### Purpose

This document describes how to test a TCAT installation to behave *as
expected* by the authors and users. It exists as a procedures to be
manually followed, but could be automated in the future.

### Scope

This documentation describes testing and (detailed) verification of
TCAT functionality. It does not describe unit testing yet. Unit
testing is a more micro-approach where we verify PHP function input
and output. We could also opt for a hybrid testing model, with some
important unit tests combined with tests of functionality as it is
described here.

### Conformance

The TCAT software is defined to have passed this test plan if it
passes every test in it and every alternative test.

A test is defined to have **failed** if any of the expected items did
not occur, otherwise it is defined to have **passed**.  That is, all
of the expected items must occur for the test to pass.

### Overview

The tests in this test plan have been grouped into these sections:

- Install tests
- Capture tests
- Analysis tests
- Controlling capture tests
- URL expansion tests
- Rate limit tests
- Geographic search tests

## Install tests

### Install track mode

Pre-requsites:

- Host machine where TCAT has not been installed.
- A copy of the _helpers/tcat-install-linux.sh_ script.
- Configuration file with valid Twitter API credentials. It should
  only contain these parameters and no others:
   - CONSUMERKEY
   - CONSUMERSECRET
   - USERTOKEN
   - USERSECRET

Procedure:

0. Run the TCAT install script in batch mode (-b), saving the TCAT
   logins to a file (-l) and using the configuration file:

       sudo ./tcat-install-linux.sh -b -l -c myTCAT.conf

   Note: this will configure TCAT with the default Tweet capture mode
   of "track phrases and keywords".

0. Wait for the install script to finish running (a few minutes).

0. Check the exit status by running `echo $?`

0. Expect exit status to be zero (i.e. TCAT installed successfully).

Note: the following tests ("Cron configured", "Database created" and
"Apache configured") are probably redundant, because other tests
(e.g. "Create a query bin") will fail if these tests failed.

### Cron configured

Pre-requesites:

- Install track mode

Procedure:

0. Run `ls -l /etc/cron.d/tcat`.
0. Expect file exists.
0. Expect file owner and group to be both _root_.
0. Expect file permissions to be "-rw-r--r--".
0. Examine the contents by running `cat /etc/cron.d/tcat`.
0. Expect /var/www/dmi-tcat/capture/stream/controller.php to be run
   very regularly (e.g. every minute).
0. Expect /var/www/dmi-tcat/helpers/urlexpand.sh to be run
   regularly (e.g. every hour), if TCAT was installed with URL expansion.

### Database created

Pre-requsites:

- Install track mode

Procedure:

0. Run `sudo mysql --defaults-file=/etc/mysql/conf.d/tcat-root.cnf`
   (_sudo_ is required because the config file is only readable by the
    _mysql_ user)
0. Expect the "mysql>" prompt to appear.
0. Enter the command `show databases;`
0. Expect a database called "twittercapture" to exist.
0. Enter the command `use twittercapture;`
0. Enter the command `show tables;`
0. Expect there to be no tables in the database (i.e. "Empty set" to be printed out).
0. Enter the command `quit`.

Note: the controller.php does not work at this stage, because
there are no tables in the database.

### Apache configured

Pre-requsites:

- Install track mode

Procedure:

0. Run `curl -v http://localhost/capture/`.
0. Expect HTTP status to be "401 Unauthorized".
0. Run `curl -v http://localhost/analysis/`.
0. Expect HTTP status to be "401 Unauthorized".

Alternatively, run these commands:

```sh
curl --silent --output /dev/null --write-out '%{http_code}\n' http://localhost/capture/
curl --silent --output /dev/null --write-out '%{http_code}\n' http://localhost/analysis/
```

## Capture tests

In the following, replace localhost in the URLs with the hostname or
IP address of the host machine. The correct URLs should have been
printed out when the install script finished running.

### Login as admin

0. Visit http://localhost/capture/ in a Web browser.
0. Expect to be prompted by the browser to login to DMI-TCAT.
0. Enter "admin" and its password. The password is the one supplied to
   the installer or was generated and printed out at the end of the
   installation process (and can be found in
   _/etc/apache2/tcat-login-admin.txt_ if the `-l` option was used
   with the installer).
0. Expect the DMI-TCAT query manager page to appear.

### Create a query bin

Pre-requesites:

- Login as admin
- A query bin called "test1" has not been created.

Procedure:

0. Expect the "New query bin" form is shown.

0. Expect there is no query bin called "test1" listed in the table of
   query bins.

0. Fill in the "New query bin" form:
    - Bin type: keyword track
    - Bin name: test1
    - Phrases to track: apple
    - Optional notes: (leave blank)

0. Press the "add query bin" button.

0. Expect a dialog box to appear, asking if you are sure you want to create
   the query bin.

0. Press the "OK" button.

0. Expect a dialog box to appear, saying the new query bin has been created.

0. Press the "Close" button to close the dialog box.

0. Expect the new query to be listed in the table of query bins.

      0. Expect the name to be "test1".
      0. Expect the "active" to be "1".
      0. Expect the "type" to be "track".
      0. Expect the "queries" to be "apple".
      0. Expect the "no. tweets" to be "0" for the new query bin.
      0. Expect the "Periods in which the query bin was active" to be
         the time the "add query bin" button was pressed till "now".

0. Wait at least one minute (the interval of the _controller.php_
   cron job).

0. Refresh the capture page.

0. Expect the "no. tweets" to have increased to a non-zero value.

### Query bin database tables created

0. Run
   `sudo mysql --defaults-file=/etc/mysql/conf.d/tcat-root.cnf twittercapture`
   (_sudo_ is required because the config file is only readable by the
    _mysql_ user)
0. Expect the "mysql>" prompt to appear.
0. Enter the command `show tables;`
0. Expect 9 tables with names starting with "tcat_".
0. Expect 7 tables with names starting with the query bin name followed
   by an underscore (i.e. "test1_").
0. Enter the "quit" command.

### Query bin log files

0. Run `ls -l /var/www/dmi-tcat/logs`.
0. Expect directory to contain a file called controller.log.
0. Expect controller.log to have an owner and group of "tcat".
0. Expect controller.log to be non-empty
0. Expect directory to contain a file called track.error.log.
0. Expect track.error.log to have an owner and group of "tcat".

_TODO: Is it an issue that track.error.log is non-empty? As an error
file, it should be empty unless something goes wrong, otherwise
important error messages get lost in the noise. Is there supposed to
be a (non-error) track log file?_

## Analysis tests

### Select tweets

Pre-requesites:

- Query bin created
- Already logged in as the "admin" or "tcat" user.

Procedure:

0. Visit http://localhost/analysis/ in a Web browser.
0. Expect the analysis page to appear.
0. Expect the pop-up menu to contain the "test1" dataset.
0. Expect the pop-up menu to indicate there a non-zero number of tweets.
0. Change the start and end dates to include the tweet collection period.
   Leave all other fields blank.
0. Press the "update overview" button.
0. Expect a pie chart to appear showing tweets with links and those without
   links.
0. Expect lines to appear on the time-based graph.

### Graph resolution

0. In the "graph resolution" select the "hours" radio button.
0. Press the "update graph" button.
0. Expect a more detailed graph to appear.
0. In the "graph resolution" select the "minutes" radio button.
0. Press the "update graph" button.
0. Expect an even more detailed graph to appear.

Alternatives:

- If not logged in (as admin or tcat) the browser will not prompt
  for the user and password. Either "admin" or "tcat" user
  accounts can be used.

### Export statistics

Pre-requesites:

- Select tweets

Procedure:

0. In the "Export selected data" section, select the output format
   of "CSV (comma-separated)".
0. In the "Tweet statistics and activity metrics" subsection,
   select the "overall" radio button.
0. In the "Tweet stats" sub-subsection, click on the "launch" link.
0. Expect a tweet stats page to appear.
0. Download the file from the link on the page.
0. Expect...

Alternatives:

Repeat with all the combinations of:

- Other sub-subsections (i.e. the other "launch" links).
- Other statistics groupings.
- Output format of "TSV (tab-separated)".

### Export tweets

Pre-requesites:

- Select tweets

Procedure:

0. In the "Export selected data" section, select the output format
   of "CSV (comma-separated)".
0. In the "Tweet exports" subsection, select none of the
   additional column check boxes.
0. In the "Random set of tweets from selection" sub-subsection,
   click on the "launch" link.
0. Expect an export tweets page to appear.
0. Download the file from the link on the page.
0. Expect...

Alternatives:

Repeat with all the combinations of:

- Other sub-subsections (i.e. the other "launch"/"export" links).
- Other additional columns selected.
- Output format of "TSV (tab-separated)".

### Networks

TBD.

### Experimental

TBD.

## Controlling capture tests

### Stop capture

Pre-requsites:

- Capture

Procedure:

0. Visit http://localhost/capture/ in a Web browser.
0. Click on the "stop" link for "test1".
0. Expect a dialog box to appear, to confirm stopping the capture.
0. Press the "yes" button.
0. Expect a dialog box to appear, saying the query bin has been stopped.
0. Press the "Close" button to close the dialog box.
0. Expect the "stop" link has been replaced by a "start" link.
0. Show the time when the controller.php was excuted by running
   `ls -l /var/www/dmi-tcat/logs/controller.log`
0. Take note of the value of the "no. tweets" for "test1".
0. Take note of the finish time for the "periods in which the query bin was
   active" for "test1".
0. Wait at least one minute (the interval of the _controller.php_
   cron job).
0. Refresh the capture page.
0. Expect the "no. tweets" to not have changed.
0. Expect the finish time to not have changed.
0. Show the time when the controller.php was excuted by running
   `ls -l /var/www/dmi-tcat/logs/controller.log`
0. Expect the time is newer than when previously checked.
   That is, the controller.php is still being executed, but it is only not
   running that particular query bin.

Alternative:

Create another query bin that is not stopped. It should be updated
with new tweets while the stopped query bin is not.

### Restarting capture

Pre-requsites:

- Stop capture

0. Click on the "start" link for "test1".
0. Expect a dialog box to appear, to confirm the start of capturing.
0. Press the "yes" button.
0. Expect a dialog box to appear, saying the query bin has been started.
0. Press the "close" button.
0. Expect the "start" link has been replaced by a "stop" link.
0. Take note of the value of the "no. tweets" for "test1".
0. Take note of the finish time for the "periods in which the query bin was
   active" for "test1".
0. Wait at least one minute (the interval of the _controller.php_
   cron job).
0. Refresh the capture page.
0. Expect the "no. tweets" to have increased.
0. Expect the finish time to have advanced.

Alternative:

Check the graph.


## URL expansion tests

TBD.

## Rate limit tests

TBD.

## Geographic search tests

TBD.

## To do

- Tests for other Tweet capture modes (i.e. following users and one
  percent capture).


From dentoir's draft from 7 April 2016:
https://github.com/digitalmethodsinitiative/dmi-tcat/issues/170#issuecomment-206898863

>    - execute the command line script capture/search/search.php with specially crafted parameters to search a unique, long hashkey, which should return a single, real tweet, with a known ID and known content (or alternatively, multiple IDs with known content)
>    - curl the TCAT analysis URL with special parameters: do we get a correct overview, with the tweet(s) found?
>    - curl the TCAT analysis URL with special *search* parameters: do we get a correct overview, with the tweet(s) found?
>    - curl several or all of the TCAT analysis scripts with special parameters, downloading the .tsv or .csv files and comparing them to output we know is correct
> 

Note: Currently _url_ alone might not be sufficient, since tweet
selection currently depends on JavaScript on the page. Will need a
REST-ful API for _curl_ to work.