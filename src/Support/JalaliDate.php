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

        return Currency::toPersianDigits((string) $day)
             . ' '
             . self::MONTHS[$month]
             . ' '
             . Currency::toPersianDigits((string) $year);
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
             . Currency::toPersianDigits((string) $year);
    }

    public static function weekRangeLabel(
        DateTimeInterface $start
    ): string {
        $end = new DateTimeImmutable(
            '@' . ($start->getTimestamp() + (6 * DAY_IN_SECONDS))
        );

        $end = $end->setTimezone(wp_timezone());

        return self::shortNumeric($start)
             . ' تا '
             . self::shortNumeric($end);
    }

    public static function fromYmd(
        string $ymd
    ): string {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $ymd,
            wp_timezone()
        );

        if (! $date) {
            return $ymd;
        }

        return self::format($date);
    }

    public static function parseInputToGregorianYmd(
        string $value
    ): ?string {
        $value = trim(
            Currency::toEnglishDigits($value)
        );

        if ($value === '') {
            return null;
        }

        $value = str_replace('-', '/', $value);

        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        /*
         * اگر سال شمسی بود، به میلادی تبدیل می‌کنیم.
         * اگر از قبل میلادی بود، همان را نگه می‌داریم.
         */
        if ($year < 1700) {
            list($gy, $gm, $gd) = self::jalaliToGregorian(
                $year,
                $month,
                $day
            );

            return sprintf(
                '%04d-%02d-%02d',
                $gy,
                $gm,
                $gd
            );
        }

        return sprintf(
            '%04d-%02d-%02d',
            $year,
            $month,
            $day
        );
    }

    private static function gregorianToJalali(
        int $gy,
        int $gm,
        int $gd
    ): array {
        $gDaysInMonth = array(
            31, 28, 31, 30, 31, 30,
            31, 31, 30, 31, 30, 31,
        );

        $jDaysInMonth = array(
            31, 31, 31, 31, 31, 31,
            30, 30, 30, 30, 30, 29,
        );

        $gy -= 1600;
        $gm -= 1;
        $gd -= 1;

        $gDayNo = 365 * $gy
        + intdiv($gy + 3, 4)
				- intdiv($gy + 99, 100)
        + intdiv($gy + 399, 400);

        for ($i = 0; $i < $gm; $i++) {
            $gDayNo += $gDaysInMonth[$i];
        }

        if (
            $gm > 1
            && (
                ($gy % 4 === 0 && $gy % 100 !== 0)
                || $gy % 400 === 0
            )
        ) {
            $gDayNo++;
        }

        $gDayNo += $gd;

        $jDayNo = $gDayNo - 79;

        $jNp = intdiv($jDayNo, 12053);
        $jDayNo %= 12053;

        $jy = 979
        + 33 * $jNp
        + 4 * intdiv($jDayNo, 1461);

        $jDayNo %= 1461;

        if ($jDayNo >= 366) {
            $jy += intdiv($jDayNo - 1, 365);
            $jDayNo = ($jDayNo - 1) % 365;
        }

        for (
            $i = 0;
            $i < 11 && $jDayNo >= $jDaysInMonth[$i];
            $i++
        ) {
            $jDayNo -= $jDaysInMonth[$i];
        }

        $jm = $i + 1;
        $jd = $jDayNo + 1;

        return array($jy, $jm, $jd);
    }

    private static function jalaliToGregorian(
        int $jy,
        int $jm,
        int $jd
    ): array {
        $jy -= 979;
        $jm -= 1;
        $jd -= 1;

        $jDayNo = 365 * $jy
        + intdiv($jy, 33) * 8
        + intdiv(($jy % 33) + 3, 4);

        if ($jm < 6) {
            $jDayNo += $jm * 31;
        } else {
            $jDayNo += ($jm * 30) + 6;
        }

        $jDayNo += $jd;

        $gDayNo = $jDayNo + 79;

        $gy = 1600 + 400 * intdiv($gDayNo, 146097);
        $gDayNo %= 146097;

        $leap = true;

        if ($gDayNo >= 36525) {
            $gDayNo--;
            $gy += 100 * intdiv($gDayNo, 36524);
            $gDayNo %= 36524;

            if ($gDayNo >= 365) {
                $gDayNo++;
            } else {
                $leap = false;
            }
        }

        $gy += 4 * intdiv($gDayNo, 1461);
        $gDayNo %= 1461;

        if ($gDayNo >= 366) {
            $leap = false;
            $gDayNo--;
            $gy += intdiv($gDayNo, 365);
            $gDayNo %= 365;
        }

        $gDaysInMonth = array(
            31,
            $leap ? 29 : 28,
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

        for ($gm = 0; $gm < 12 && $gDayNo >= $gDaysInMonth[$gm]; $gm++) {
            $gDayNo -= $gDaysInMonth[$gm];
        }

        $gd = $gDayNo + 1;

        return array($gy, $gm + 1, $gd);
    }
}
