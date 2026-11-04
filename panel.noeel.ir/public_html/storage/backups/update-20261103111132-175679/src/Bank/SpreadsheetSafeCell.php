<?php
declare(strict_types=1);
namespace Babanoell\Panel\Bank;

/** Prevent formula execution when customer-controlled fields are opened in Excel/LibreOffice. */
final class SpreadsheetSafeCell
{
    public static function escape(mixed $value):string
    {
        $text=(string)($value??'');
        // A leading space, tab, CR or LF must not mask an Excel formula prefix.
        if(preg_match('/^[\s\x{200B}\x{FEFF}]*[=+@-]/u',$text)===1)return "'".$text;
        return $text;
    }
}
