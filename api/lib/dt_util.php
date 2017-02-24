<?php
// DateTime functions used by the API scripts.

//----------------------------------------------------------------
// DateTime exception
//
// Exception thrown by dt_parse function to indicate why it failed.

class DtException extends Exception
{
    const SYNTAX_ERROR = 0;
    const BAD_TIME = 1;
    const MISSING_TIMEZONE = 2;
    const INVALID_TIMEZONE = 3;
    const RANGE_YEAR = 4;
    const RANGE_MONTH = 5;
    const RANGE_DAY = 6;
    const RANGE_HOUR = 7;
    const RANGE_MINUTE = 8;
    const RANGE_SECONDS = 9;

    public static $MESSAGES = [
        "Bad syntax",
        "Bad time",
        "Missing timezone",
        "Invalid timezone",
        "Year out of range",
        "Month out of range",
        "Day out of range",
        "Hour out of range",
        "Minute out of range",
        "Seconds out of range",
    ];

    public $dt_str;
    public $reason_code;

    // Redefine the exception so message isn't optional
    public function __construct($str, $r, $code = 0,
                                Exception $previous = null)
    {
        $this->dt_str = $str;
        $this->reason_code = $r;

        $message = self::$MESSAGES[$r];
        parent::__construct($message, $code, $previous);
    }

//    public function __toString() {
//        return __CLASS__ . ": [" . self::$MESSAGES[$this->reason_code] . "]: " . $this->dt_str;
//    }

}

//----------------------------------------------------------------
// Globals

// Timezone for UTC

$utc_tz = new DateTimeZone('UTC');

//----------------------------------------------------------------
// Parses a string into a DateTime object.
//
// Expected format: YYYY-MM-DD HH:MM:SS [Z|UTC|[+-]HH:MM].  Multiple
// whitespaces are allowed between the date, time and timezone; as
// well as before or after the value. The letter "T" (with no
// whitespace around it) can also be used to separate the date from
// the time.
//
// It also accepts datetime strings in the TCAT front-end panel format
// This format is either YYYY-MM-DD, or YYYY-MM-DD HH:MM:SS
// 
// Partial times are supported. Only the year is mandatory. The other
// components are inferred based on whether $is_end is true or
// false. When false, they default to the beginning of the period
// (e.g. 1st of January 00:00:00, if only the year is provided). When
// true, they default to the end of the period (e.g. 31th of December
// 23:59:59, if only the year is provided)
//
// Timezones can be explicitly specified in the string (either as "Z",
// "UTC" or [+-]HH:MM offset.  If an explicit timezone is not
// specified in the string: if $tz_name is set to a string value from
// <http://php.net/manual/timezones.php>, it is used; otherwise an
// exception is raised.
// 
// Leap seconds are not supported.
//
// Throws DtException if the string cannot be parsed into a DateTime object.

// This function was initially created because the PHP function
// `DateTime::createFromFormat` does not do enough error checking. For
// example, it treats "2000-13-32" as a valid!

function dt_parse($str, $is_end = false, $tz_name = NULL)
{
    // Start front-end panel datetime handling

    if (substr_count($str, ' ') < 2 &&
        strpos($str, 'Z') == FALSE && strpos($str, 'UTC') == FALSE &&
        strpos($str, '+') == FALSE && substr_count($str, '-') <= 2) {
        if (substr_count($str, ' ') == 0) {
            // Year-month-day only
            if ($is_end) {
                $str .= ' 23:59:59 UTC';
            } else {
                $str .= ' 00:00:00 UTC';
            }
        } else {
            $str .= ' UTC';
        }
    }

    // End of front-end panel datetime handling

    $c = array();
    if (!preg_match('/^\s* (\d+) (-(\d\d?) (-(\d\d?)? )? )?' .
        '(([Tt]|(\s+)) (\d\d?) (:(\d\d?) (:(\d\d?))? )? )?' .
        '(\s* (Z | (UTC) | ([+-]\d\d?(:\d\d?)?) ))?' .
        '\s*$/ix',
        $str, $c)
    ) {
        throw new DtException($str, DtException::SYNTAX_ERROR);
    }

    // Year

    $y = $c[1];
    if ($y < 1902 || 9999 < $y) {
        // 32-bit PHP fails for dates before Fri, 1901-12-13 20:45:52 UTC.
        // Though strangely years above 2038 seem to work.
        // Approximation: reject years before 1902 to keep code simple
        throw new DtException($str, DtException::RANGE_YEAR);
    }

    // Month

    if (isset($c[3]) && $c[3] !== '') {
        $m = $c[3];
        if ($m < 1 || 12 < $m) {
            throw new DtException($str, DtException::RANGE_MONTH);
        }
    } else {
        $m = (!$is_end) ? '01' : '12';
    }

    if ($m == 2) {
        if ($y % 4 != 0) {
            $leap = false;
        } else if ($y % 100 != 0) {
            $leap = true;
        } else if ($y % 400 != 0) {
            $leap = false;
        } else {
            $leap = true;
        }
        $max_days_in_month = ($leap) ? '29' : '28';
    } else if ($m == 4 || $m == 6 || $m == 9 || $m == 11) {
        $max_days_in_month = '30';
    } else {
        $max_days_in_month = '31';
    }

    // Day

    if (isset($c[5]) && $c[5] !== '') {
        $d = $c[5];
        if ($d < 1 || $max_days_in_month < $d) {
            throw new DtException($str, DtException::RANGE_DAY);
        }
    } else {
        $d = (!$is_end) ? '01' : $max_days_in_month;
    }

    // Hour

    if (isset($c[9]) && $c[9] !== '') {
        $hour = $c[9];
        if (23 < $hour) {
            throw new DtException($str, DtException::RANGE_HOUR);
        }
    } else {
        $hour = (!$is_end) ? '00' : '23';
    }

    // Minutes

    if (isset($c[11]) && $c[11] !== '') {
        $min = $c[11];
        if (59 < $min) {
            throw new DtException($str, DtException::RANGE_MINUTE);
        }
    } else {
        $min = (!$is_end) ? '00' : '59';
    }

    // Seconds

    if (isset($c[13]) && $c[13] !== '') {
        $sec = $c[13];
        if (59 < $sec) {
            throw new DtException($str, DtException::RANGE_SECONDS);
        }
    } else {
        $sec = (!$is_end) ? '00' : '59';
    }

    $canonical_date = sprintf('%04d-%02d-%02d', $y, $m, $d);
    $canonical_time = sprintf('%02d:%02d:%02d', $hour, $min, $sec);

    // Timezone

    if ((isset($c[15]) && $c[15] !== '')) {
        // String has explicit timezone

        $tz_str = $c[15];

        $tzc = array();
        if (strtolower($tz_str) === 'z' || strtolower($tz_str) === 'utc') {
            $canonical_tz = '+00:00';

        } else if (preg_match('/^([+-])(\d\d?)(:(\d\d?))?$/', $tz_str, $tzc)) {
            $tzS = $tzc[1];
            $tzH = $tzc[2];
            $tzM = (isset($tzc[4]) && $tzc[4] != '') ? $tzc[4] : '00';

            if (59 < $tzM || ($tzM % 15 != 0) ||
                ($tzS === '+' && 14 < $tzH) ||
                ($tzS === '+' && $tzH == 14 && $tzM != 0) ||
                ($tzS === '-' && 12 < $tzH) ||
                ($tzS === '-' && $tzH == 12 && $tzM != 0)
            ) {
                throw new DtException($str, DtException::INVALID_TIMEZONE);
            }

            $canonical_tz = sprintf('%s%02d:%02d', $tzS, $tzH, $tzM);

        } else {
            throw new DtException($str, DtException::INVALID_TIMEZONE);
        }

        // Convert into DateTime object

        $canonical = "$canonical_date $canonical_time $canonical_tz";
        $result = DateTime::createFromFormat('Y-m-d H:i:sP', $canonical);

    } else {
        // No explicit timezone in string

        if (!isset($tz_name) || $tz_name === '') {
            throw new DtException($str, DtException::MISSING_TIMEZONE);
        }
        if (preg_match('/^\s*[+-]?\d\d(:\d\d)?/', $tz_name)) {
            // Bug in PHP's DateTimeZone class or DateTime->setTimezone method.
            // If a string of [+-]HH:MM is used, a DateTimeZone object is
            // created without an error, but using it to set the timezone
            // on a DateTime object modifies the value, changing the time
            // it is supposed to represent.
            throw new Exception("Invalid timezone name: \"$tz_name\" (only PHP supported names allowed)");
        }
        $tz = new DateTimeZone($tz_name);

        // Convert into DateTime object

        $canonical = "$canonical_date $canonical_time";
        $result = DateTime::createFromFormat('Y-m-d H:i:s', $canonical, $tz);
    }

    if ((!isset($result)) || $result === false) {
        // DateTime::createFromFormat unexpectedly failed
        throw new DtException($str, DtException::BAD_TIME);
    }

    return $result;
}

//----------------------------------------------------------------
// Creates a DateTime from a string "YYYY-MM-DD HH:MM:SS" in UTC.

function dt_from_utc($str)
{
    $r = array();
    if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $str, $r)) {
        return dt_parse($str . 'Z', false, NULL);
    } else {
        throw new DtException($str, DtException::SYNTAX_ERROR);
    }
}

//----------------------------------------------------------------
// Returns a string representation of the DateTime for display to a user.
//
// A timezone indicator is always included so the user will not
// have any confusion about what timezone the value is in.
//
// If $tz_name is NULL, it is shown in UTC; otherwise it is shown
// in the provided timezone.

define('DT_FORMAT_PATTERN', 'Y-m-d H:i:s');

function dt_format_text(DateTime $dt, $tz_name = NULL)
{
    global $utc_tz;
    
    if (isset($tz_name)) {
        if (preg_match('/^\s*[+-]?\d+(:\d+)?/', $tz_name)) {
            // Bug in PHP's DateTimeZone class or DateTime->setTimezone method.
            // If a string of [+-]HH:MM is used, a DateTimeZone object is
            // created without an error, but using it to set the timezone
            // on a DateTime object modifies the value, changing the time
            // it is supposed to represent.
            throw new Exception("Invalid timezone name: \"$tz_name\" (only PHP supported names allowed)");
        }

        $dt = $dt->setTimezone(new DateTimeZone($tz_name));
        return $dt->format(DT_FORMAT_PATTERN . ' P'); // P = +hh:mm
    } else {
        $dt = $dt->setTimezone($utc_tz);
        return ($dt->format(DT_FORMAT_PATTERN) . ' UTC');
    }
}

//----------------------------------------------------------------
// Returns a string containing HTML to display a DateTime to a user.
//
// If $tz_name is NULL, it is shown in UTC; otherwise it is shown
// in the provided timezone. A tooltip is added that always
// shows it in UTC.

function dt_format_html(DateTime $dt, $tz_name = NULL)
{
    $html = '<span class="datetime" title="';
    $html .= htmlspecialchars(dt_format_text($dt, NULL)); // always in UTC
    $html .= '">';
    $html .= htmlspecialchars(dt_format_text($dt, $tz_name));

    $html .= '</span>';

    return $html;
}
