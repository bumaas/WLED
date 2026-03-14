<?php

declare(strict_types=1);

namespace libs;

class WLEDPresentations
{
    public static function switch(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
        ];
    }

    public static function color(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_COLOR,
            'ENCODING'     => 0
        ];
    }

    public static function slider(
        int|float $min,
        int|float $max,
        int|float $stepSize,
        string $suffix = '',
        ?int $usageType = null
    ): array {
        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
            'MIN'          => $min,
            'MAX'          => $max,
            'STEP_SIZE'    => $stepSize
        ];

        if ($suffix !== '') {
            $presentation['SUFFIX'] = $suffix;
        }

        if ($usageType !== null) {
            $presentation['USAGE_TYPE'] = $usageType;
        }

        return $presentation;
    }

    public static function enumeration(array $options): array
    {
        $normalizedOptions = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $normalizedOptions[] = [
                'Value'      => (int)($option['Value'] ?? 0),
                'Caption'    => (string)($option['Caption'] ?? ''),
                'IconActive' => (bool)($option['IconActive'] ?? false),
                'IconValue'  => (string)($option['IconValue'] ?? ''),
                'Color'      => (int)($option['Color'] ?? -1)
            ];
        }

        if (count($normalizedOptions) === 0) {
            $normalizedOptions[] = [
                'Value'      => 0,
                'Caption'    => '',
                'IconActive' => false,
                'IconValue'  => '',
                'Color'      => -1
            ];
        }

        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode($normalizedOptions, JSON_THROW_ON_ERROR)
        ];
    }

    public static function timeOnly(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
            'DATE'         => 0,
            'TIME'         => 2
        ];
    }
}
