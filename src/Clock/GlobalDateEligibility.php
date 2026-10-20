<?php

declare(strict_types=1);

namespace WordleHitToday\Clock;

final class GlobalDateEligibility
{
    private const string EARLIEST_ZONE = '+14:00';
    private const string LATEST_ZONE = '-12:00';

    /** @return list<string> */
    public function datesAt(\DateTimeImmutable $utcInstant): array
    {
        $instant = $utcInstant->setTimezone(new \DateTimeZone('UTC'));
        $first = $instant->setTimezone(new \DateTimeZone(self::LATEST_ZONE))->setTime(0, 0);
        $last = $instant->setTimezone(new \DateTimeZone(self::EARLIEST_ZONE))->setTime(0, 0);

        $dates = [];
        for ($cursor = $first; $cursor->format('Y-m-d') <= $last->format('Y-m-d'); $cursor = $cursor->modify('+1 day')) {
            $dates[] = $cursor->format('Y-m-d');
        }

        return $dates;
    }

    public function earliestPublicationInstant(string $date): \DateTimeImmutable
    {
        $localMidnight = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            new \DateTimeZone(self::EARLIEST_ZONE),
        );

        if (!$localMidnight instanceof \DateTimeImmutable || $localMidnight->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException(sprintf('Invalid ISO date: %s', $date));
        }

        return $localMidnight->setTimezone(new \DateTimeZone('UTC'));
    }
}
