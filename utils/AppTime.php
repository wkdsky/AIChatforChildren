<?php

namespace Utils;

use Core\Config;
use DateTimeImmutable;
use DateTimeZone;

class AppTime
{
    public static function timezoneName(): string
    {
        return (string) Config::get('APP_TIMEZONE', Config::get('app.timezone', 'Asia/Shanghai'));
    }

    public static function timezone(): DateTimeZone
    {
        return new DateTimeZone(self::timezoneName());
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timezone());
    }

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable('today', self::timezone());
    }

    public static function toIso8601(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $timezone = self::timezone();

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);
        if ($date && $date->format('Y-m-d H:i:s') === $value) {
            return $date->format(DATE_ATOM);
        }

        try {
            return (new DateTimeImmutable($value, $timezone))
                ->setTimezone($timezone)
                ->format(DATE_ATOM);
        } catch (\Exception $e) {
            return null;
        }
    }
}
