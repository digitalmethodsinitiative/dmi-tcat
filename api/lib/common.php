<?php
// Common code that is loaded by all the API PHP scripts.

define('API_VERSION', '1.0');

// Speed multiplier for estimating purge times.
//
// Set this to a number greater than 1.0 for slower machines.
// Set this to a number less than 1.0 for faster machines.

define('PURGE_TIME_FACTOR', 1.0);

// Default timezone
//
// Set this to a string indicating the timezone to use for displaying
// times and for parsing strings (when no explicit timezone is in the
// string).
//
// Valid values can be found at: <http://php.net/manual/timezones.php>
//
// Strings of the form "+HH:MM" DO NOT WORK correctly. if you must
// specify an offset, try the values under the "Other" section of the
// above page. Note: those values are incomplete and the sign is
// reversed (e.g. for UTC+10, the name is "Etc/GMT-10"). PHP!
//
// If not set (or set to NULL), times will be displayed in UTC and strings
// being parsed must explicitly include a timezone.
//
// Examples:
//   $api_timezone = date_default_timezone_get(); // PHP's default
//   $api_timezone = 'UTC';
//   $api_timezone = 'Europe/Amsterdam';
//   $api_timezone = 'Australia/Brisbane';

$api_timezone = NULL;

