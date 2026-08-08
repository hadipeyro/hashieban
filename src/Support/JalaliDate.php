<?php

declare(strict_types=1);

namespace Hashieban\Support;

use DateTimeImmutable;
use DateTimeInterface;

final class JalaliDate
{
    private const MONTHS = array(
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    );

    public static function parts(
        DateTimeInterface $date
    ): array {
        return self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );
    }

    public static function format(
        DateTimeInterface $date
    ): string {
        list(
            $year,
            $month,
            $day
        ) = self::parts($date);

        return Currency::toPersianDigits(
            (string) $day
        )
             . ' '
             . self::MONTHS[$month]
             . ' '
             . Currency::toPersianDigits(
                 (string) $year
             );
    }

    public static function numeric(
        DateTimeInterface $date
    ): string {
        list(
            $year,
            $month,
            $day
        ) = self::parts($date);

        return Currency::toPersianDigits(
            sprintf(
                '%04d/%02d/%02d',
                $year,
                $month,
                $day
            )
        );
    }

    public static function shortNumeric(
        DateTimeInterface $date
    ): string {
        list(
            $year,
            $month,
            $day
        ) = self::parts($date);

        return Currency::toPersianDigits(
            sprintf(
                '%02d/%02d',
                $month,
                $day
            )
        );
    }

    public static function monthLabel(
        DateTimeInterface $date
    ): string {
        list(
            $year,
            $month
        ) = self::parts($date);

        return self::MONTHS[$month]
             . ' '
             . Currency::toPersianDigits(
                 (string) $year
             );
    }

    public static function weekRangeLabel(
        DateTimeInterface $start
    ): string {
        $end = new DateTimeImmutable(
            '@'
          . (
              $start->getTimestamp()
              + (6 * DAY_IN_SECONDS)
          )
        );

        $end = $end->setTimezone(
            wp_timezone()
        );

        return self::shortNumeric($start)
             . ' تا '
             . self::shortNumeric($end);
    }

    public static function fromYmd(
        string $ymd
    ): string {
        $date =
            DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $ymd,
                wp_timezone()
            );

        if (! $date) {
            return $ymd;
        }

        return self::format($date);
    }

    private static function gregorianToJalali(
        int $gy,
        int $gm,
        int $gd
    ): array {
        $gDaysInMonth = array(
            31,
            28,
            31,
            30,
            31,
            30,
            31,
            31,
            30,
            31,
            30,
            31,
        );

        $jDaysInMonth = array(
            31,
            31,
            31,
            31,
            31,
            31,
            30,
            30,
            30,
            30,
            30,
            29,
        );

        $gy -= 1600;
        $gm -= 1;
        $gd -= 1;

        $gDayNo =
            365 * $gy
        + intdiv($gy + 3, 4)
            - intdiv($gy + 99, 100)
        + intdiv($gy + 399, 400);

        for ($i = 0; $i < $gm; $i++) {
            $gDayNo +=
                $gDaysInMonth[$i];
        }

        if (
            $gm > 1
            && (
                (
                    $gy % 4 === 0
                    && $gy % 100 !== 0
                )
                || $gy % 400 === 0
            )
        ) {
            $gDayNo++;
        }

        $gDayNo += $gd;

        $jDayNo =
            $gDayNo - 79;

        $jNp =
            intdiv(
                $jDayNo,
                12053
            );

        $jDayNo %= 12053;

        $jy =
            979
        + 33 * $jNp
        + 4 * intdiv(
            $jDayNo,
            1461
        );

        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jy += intdiv(
                $jDayNo - 1,
                365
            );

            $jDayNo =
                ($jDayNo - 1)
            % 365;
        }

        for (
            $i = 0;
            $i < 11
              && $jDayNo
            >= $jDaysInMonth[$i];
            $i++
        ) {
            $jDayNo -=
                $jDaysInMonth[$i];
        }

        $jm = $i + 1;
        $jd = $jDayNo + 1;

        return array(
            $jy,
            $jm,
            $jd,
        );
    }
}
