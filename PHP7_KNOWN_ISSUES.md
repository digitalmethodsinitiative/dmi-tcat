# PHP 7.0 compatibility branch - known issues

## Capture segment

No issues known at the moment, but little tested. Keyword tracking works, controller works.

## Analysis segment

Paramater validation for element 'mysql' is now critically weak, see validate() function for details.

## Other components

Auto-installer does not install the GEOS PHP 7 module, because it simply is not supported by PHP 7.
