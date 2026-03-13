<?php

declare(strict_types=1);

namespace libs;

final class WLEDHttp
{
    public static function getParentInstanceId(int $instanceId): int
    {
        $instance = @\IPS_GetInstance($instanceId);
        if (!is_array($instance)) {
            return 0;
        }

        return (int)($instance['ConnectionID'] ?? 0);
    }

    public static function getHostFromSplitter(int $splitterInstanceId): string
    {
        $ioId = self::getParentInstanceId($splitterInstanceId);
        if ($ioId <= 0) {
            return '';
        }

        $url = (string)@\IPS_GetProperty($ioId, 'URL');
        if ($url === '') {
            return '';
        }

        $host = \parse_url($url, PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }

    public static function getHostFromDevice(int $deviceInstanceId): string
    {
        $splitterId = self::getParentInstanceId($deviceInstanceId);
        if ($splitterId <= 0) {
            return '';
        }

        return self::getHostFromSplitter($splitterId);
    }

    public static function getData(string $host, string $path, int $timeoutSeconds = 1): array
    {
        if ($host === '') {
            return [];
        }

        $jsonData = @\file_get_contents(
            \sprintf('http://%s%s', $host, $path),
            false,
            \stream_context_create([
                'http' => ['timeout' => $timeoutSeconds]
            ])
        );
        if ($jsonData === false) {
            return [];
        }

        try {
            $decoded = \json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}

