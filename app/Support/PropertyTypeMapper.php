<?php

namespace App\Support;

use App\Models\PropertyAssessmentValue;
use App\Models\PropertyList;

class PropertyTypeMapper
{
    /**
     * Alphacen / CSV sentence-case → snake_case (e.g. "Basic Commercial" → basic_commercial).
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    /**
     * Map imported property_type values to property_assessment_values.property_type keys.
     *
     * @return array<string, string>
     */
    public static function pavAliases(): array
    {
        return [
            'block_of_flats' => 'blocks_of_flats',
            'blocks_of_flat' => 'blocks_of_flats',
            'restaurants_and_eateries' => 'restaurants_and_eatries',
            'restaurant' => 'restaurants_and_eatries',
            'restaurants' => 'restaurants_and_eatries',
            'restaurant_lounge' => 'restaurants_and_eatries',
            'restaurants_and_eateries_live_band' => 'restaurants_and_eatries',
            'construction_site' => 'construction_sites',
            'undeveloped_site' => 'construction_sites',
            'construction_site_undeveloped_site' => 'construction_sites',
            'single_family_unit' => 'single_family_units',
            'banks' => 'banks_per_branch',
            'bank' => 'banks_per_branch',
            'church' => 'churches_mosques',
            'mosque' => 'churches_mosques',
            'school' => 'schools_hospitals',
            'hospital' => 'schools_hospitals',
            'event_center_medium_commercial' => 'event_centre',
            'live_band_event_center' => 'event_centre',
            'event_center' => 'event_centre',
            'hotel' => 'hotels',
            'hotels_large' => 'hotels',
            'hotels_large_corporates' => 'hotels',
            'large_mall' => 'large_malls',
        ];
    }

    public static function resolvePavPropertyType(string $normalizedType): string
    {
        if ($normalizedType === '') {
            return '';
        }

        return self::pavAliases()[$normalizedType] ?? $normalizedType;
    }

    public static function findPav(
        ?string $propertyType = null,
        ?string $syncWord = null,
        ?string $propertyUse = null,
        ?string $pavCode = null,
    ): ?PropertyAssessmentValue {
        if ($pavCode !== null && trim($pavCode) !== '') {
            $byCode = PropertyAssessmentValue::where('pav_code', trim($pavCode))->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        foreach ([$propertyType, $syncWord, $propertyUse] as $candidate) {
            $pav = self::findPavByTypeCandidate($candidate);
            if ($pav !== null) {
                return $pav;
            }
        }

        return null;
    }

    public static function findPavForProperty(PropertyList $property): ?PropertyAssessmentValue
    {
        return self::findPav(
            $property->property_type,
            $property->sync_word,
            $property->property_use,
            $property->pav_code,
        );
    }

    protected static function findPavByTypeCandidate(?string $candidate): ?PropertyAssessmentValue
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return null;
        }

        $normalized = self::normalize($candidate);
        $keys = array_unique(array_filter([
            $candidate,
            $normalized,
            self::resolvePavPropertyType($normalized),
        ]));

        foreach ($keys as $key) {
            $pav = PropertyAssessmentValue::where('property_type', $key)->first();
            if ($pav !== null) {
                return $pav;
            }

            $pav = PropertyAssessmentValue::query()
                ->whereRaw('LOWER(TRIM(property_type)) = ?', [strtolower($key)])
                ->first();
            if ($pav !== null) {
                return $pav;
            }
        }

        return null;
    }

    public static function pavTableEmpty(): bool
    {
        return PropertyAssessmentValue::count() === 0;
    }
}
