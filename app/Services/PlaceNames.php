<?php

namespace App\Services;

/**
 * Towns and provinces for Add Resolved Report (item 72): MDRRMO Rosales
 * also responds outside Rosales. These are the suggestions on the form,
 * and they keep the spelling the same ("Sto. Tomas" → "Santo Tomas",
 * "Urdaneta" → "Urdaneta City"). Any other town can still be typed.
 */
class PlaceNames
{
    /** The 44 municipalities and 4 cities of Pangasinan. */
    public const PANGASINAN = [
        'Agno', 'Aguilar', 'Alaminos City', 'Alcala', 'Anda', 'Asingan', 'Balungao', 'Bani',
        'Basista', 'Bautista', 'Bayambang', 'Binalonan', 'Binmaley', 'Bolinao', 'Bugallon',
        'Burgos', 'Calasiao', 'Dagupan City', 'Dasol', 'Infanta', 'Labrador', 'Laoac', 'Lingayen',
        'Mabini', 'Malasiqui', 'Manaoag', 'Mangaldan', 'Mangatarem', 'Mapandan', 'Natividad',
        'Pozorrubio', 'Rosales', 'San Carlos City', 'San Fabian', 'San Jacinto', 'San Manuel',
        'San Nicolas', 'San Quintin', 'Santa Barbara', 'Santa Maria', 'Santo Tomas', 'Sison',
        'Sual', 'Tayug', 'Umingan', 'Urbiztondo', 'Urdaneta City', 'Villasis',
    ];

    /** Pangasinan and the provinces around it. */
    public const PROVINCES = [
        'Pangasinan', 'Nueva Ecija', 'Tarlac', 'La Union', 'Benguet', 'Nueva Vizcaya', 'Zambales',
    ];

    /** The towns the form suggests: Pangasinan's, without Rosales. */
    public static function otherTowns(): array
    {
        return array_values(array_diff(self::PANGASINAN, ['Rosales']));
    }

    /**
     * A town as it's saved: a Pangasinan town spelled the usual way
     * ("sto. tomas" → "Santo Tomas", "City of Urdaneta" → "Urdaneta City");
     * any other town as typed, without extra spaces ("cuyapo" → "Cuyapo").
     */
    public static function town(string $town, string $province = 'Pangasinan'): string
    {
        $town = self::tidy($town);
        if (self::province($province) !== 'Pangasinan') {
            return self::capitalized($town);
        }

        $key = self::key($town);
        foreach (self::PANGASINAN as $name) {
            $base = preg_replace('/ City$/', '', $name);
            if (in_array($key, [self::key($name), self::key($base), self::key("City of {$base}")], true)) {
                return $name;
            }
        }

        return self::capitalized($town);
    }

    /** "nueva ecija" → "Nueva Ecija"; any other province as typed, tidied. */
    public static function province(string $province): string
    {
        $province = self::tidy($province);
        $key = self::key((string) preg_replace('/^province of\s+/iu', '', $province));
        foreach (self::PROVINCES as $name) {
            if ($key === self::key($name)) {
                return $name;
            }
        }

        return self::capitalized($province);
    }

    /** Rosales, Pangasinan typed as "another town". */
    public static function isRosales(string $town, string $province): bool
    {
        return self::province($province) === 'Pangasinan' && self::town($town, $province) === 'Rosales';
    }

    /** "Brgy. Poblacion" / "Barangay Poblacion" → "Poblacion" ("Brgy. " is added when saved). */
    public static function barangay(string $barangay): string
    {
        return self::capitalized(self::tidy((string) preg_replace('/^(brgy\.?|bgy\.?|barangay)\s+/iu', '', self::tidy($barangay))));
    }

    private static function tidy(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Typed all in lowercase → "San Jose"; anything else stays as typed. */
    private static function capitalized(string $text): string
    {
        return ($text !== '' && mb_strtolower($text) === $text) ? mb_convert_case($text, MB_CASE_TITLE, 'UTF-8') : $text;
    }

    /** For comparing: lowercase; "sta." → "santa", "sto." → "santo"; "municipality of" dropped. */
    private static function key(string $text): string
    {
        $text = mb_strtolower(self::tidy($text));
        $text = (string) preg_replace('/^(the\s+)?municipality of\s+/u', '', $text);
        $text = (string) preg_replace('/^sta\.?\s+/u', 'santa ', $text);

        return (string) preg_replace('/^sto\.?\s+/u', 'santo ', $text);
    }
}
