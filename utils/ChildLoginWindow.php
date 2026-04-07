<?php

namespace Utils;

use DateTimeImmutable;

class ChildLoginWindow
{
    public static function evaluate(?string $allowedStart, ?string $allowedEnd, ?DateTimeImmutable $reference = null): array
    {
        $now = $reference ? $reference->setTimezone(AppTime::timezone()) : AppTime::now();
        $start = self::normalizeTime($allowedStart);
        $end = self::normalizeTime($allowedEnd);

        if ($start === null || $end === null) {
            return [
                'is_configured' => false,
                'is_allowed_now' => true,
                'spans_overnight' => false,
                'window_start' => $start,
                'window_end' => $end,
                'now_time' => $now->format('H:i:s'),
                'now_time_short' => $now->format('H:i'),
                'timezone' => AppTime::timezoneName(),
            ];
        }

        $nowTime = $now->format('H:i:s');
        $isFullDay = $start === $end;
        $spansOvernight = !$isFullDay && $start > $end;

        if ($isFullDay) {
            $isAllowedNow = true;
        } elseif ($spansOvernight) {
            $isAllowedNow = $nowTime >= $start || $nowTime < $end;
        } else {
            $isAllowedNow = $nowTime >= $start && $nowTime < $end;
        }

        return [
            'is_configured' => true,
            'is_allowed_now' => $isAllowedNow,
            'spans_overnight' => $spansOvernight,
            'window_start' => $start,
            'window_end' => $end,
            'now_time' => $nowTime,
            'now_time_short' => $now->format('H:i'),
            'timezone' => AppTime::timezoneName(),
        ];
    }

    private static function normalizeTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $value = trim($time);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            $value .= ':00';
        }

        $parsed = DateTimeImmutable::createFromFormat('H:i:s', $value, AppTime::timezone());
        if (!$parsed || $parsed->format('H:i:s') !== $value) {
            return null;
        }

        return $value;
    }
}
