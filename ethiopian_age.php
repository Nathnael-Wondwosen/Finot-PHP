<?php
// ethiopian_age.php
// Usage: ethiopian_age('YYYY-MM-DD')
function ethiopian_age($birth_date_gregorian) {
    // Parse Gregorian birth date
    $birthDate = new DateTime($birth_date_gregorian);
    $gregorianYear = (int)$birthDate->format('Y');
    $gregorianMonth = (int)$birthDate->format('m');
    $gregorianDay = (int)$birthDate->format('d');

    // Ethiopian year is usually Gregorian year - 7 or -8 depending on month
    // Ethiopian New Year is September 11 (or 12 in Gregorian leap years)
    $ethiopianYear = $gregorianYear - 8;
    if ($gregorianMonth > 9 || ($gregorianMonth == 9 && $gregorianDay >= 11)) {
        $ethiopianYear = $gregorianYear - 7;
    }

    // Get current Ethiopian year
    $today = new DateTime();
    $currentGregorianYear = (int)$today->format('Y');
    $currentGregorianMonth = (int)$today->format('m');
    $currentGregorianDay = (int)$today->format('d');
    $currentEthiopianYear = $currentGregorianYear - 8;
    if ($currentGregorianMonth > 9 || ($currentGregorianMonth == 9 && $currentGregorianDay >= 11)) {
        $currentEthiopianYear = $currentGregorianYear - 7;
    }

    $age = $currentEthiopianYear - $ethiopianYear;
    return $age;
}
?>
