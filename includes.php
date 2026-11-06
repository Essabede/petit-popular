<?php
if (!defined('ABSPATH')) { exit; }

final class Petit_Popular_Dates {
    private static function today() {
        return current_datetime()->setTime(0, 0);
    }
    private static function date($value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value, wp_timezone());
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Cal indicar dates vàlides amb el format AAAA-MM-DD.');
        }
        return $date;
    }
    private static function days($value) {
        if (!is_scalar($value) || !preg_match('/^\d+$/', (string) $value) || (int) $value < 1 || (int) $value > 3660) {
            throw new InvalidArgumentException('El nombre de dies ha de ser entre 1 i 3.660.');
        }
        return (int) $value;
    }
    private static function result($start, $end) {
        if ($end < $start) { throw new InvalidArgumentException('La data final ha de ser igual o posterior a la inicial.'); }
        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }
    public static function range($period, $days = 7, $from = '', $to = '') {
        $today = self::today();
        switch ($period) {
            case 'all': return null;
            case 'today': return self::result($today, $today);
            case '7d': $days = 7; break;
            case '30d': $days = 30; break;
            case 'days': $days = self::days($days); break;
            case 'week': return self::result($today->modify('-' . ((int) $today->format('N') - 1) . ' days'), $today);
            case 'month': return self::result($today->modify('first day of this month'), $today);
            case 'custom': return self::result(self::date($from), self::date($to));
            default: throw new InvalidArgumentException('Període de visites desconegut.');
        }
        return self::result($today->modify('-' . ($days - 1) . ' days'), $today);
    }
    public static function events($period, $days = 14, $from = '', $to = '') {
        $today = self::today();
        switch ($period) {
            case 'all': return null;
            case 'today': return self::result($today, $today);
            case 'next': return self::result($today, $today->modify('+' . (self::days($days) - 1) . ' days'));
            case 'month': return self::result($today->modify('first day of this month'), $today->modify('last day of this month'));
            case 'weekend':
                $weekday = (int) $today->format('N');
                $sat = $today->modify('+' . max(0, 6 - $weekday) . ' days');
                // On Sunday include this weekend, including its Saturday.
                if ($weekday === 7) { $sat = $today->modify('-1 day'); }
                return self::result($sat, $sat->modify('+1 day'));
            case 'custom': return self::result(self::date($from), self::date($to));
            default: throw new InvalidArgumentException('Període d’agenda desconegut.');
        }
    }
    public static function publication_start($months) {
        if (!is_scalar($months) || !preg_match('/^\d+$/', (string) $months) || (int) $months < 1 || (int) $months > 120) {
            throw new InvalidArgumentException('L’antiguitat de les notícies ha de ser entre 1 i 120 mesos.');
        }
        $today = self::today();
        $first = $today->modify('first day of this month')->modify('-' . (int) $months . ' months');
        $day = min((int) $today->format('j'), (int) $first->format('t'));
        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->format('Y-m-d 00:00:00');
    }
    public static function score($real, $factor) {
        return (int) round(max(0, (float) $real) * max(0.01, min(1000, (float) $factor)));
    }
}
