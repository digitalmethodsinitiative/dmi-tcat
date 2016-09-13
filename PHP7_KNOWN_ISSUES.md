# PHP 7.0 compatibility - known issues

## Capture segment

No issues known at the moment, but little tested. Keyword tracking works, controller works.

## Analysis segment

Export modules do not use PDO yet.

## Other components

 - Auto-installer does not install the GEOS PHP 7 module

## Work in progress

 - common/functions.php disabled dbconnect() and dbclose() completely
