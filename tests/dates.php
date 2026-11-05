<?php
// Standalone: php tests/dates.php
const ABSPATH = '/';
$GLOBALS['clock'] = '2026-11-05 15:00:00';
function wp_timezone() { return new DateTimeZone('Europe/Madrid'); }
function current_datetime() { return new DateTimeImmutable($GLOBALS['clock'], wp_timezone()); }
require dirname(__DIR__) . '/includes.php';
$checks = 0;
function same($actual, $expected, $label) {
    global $checks;
    $checks++;
    if ($actual !== $expected) { throw new RuntimeException($label . ': ' . json_encode($actual)); }
}
function rejects($callback) {
    global $checks;
    $checks++;
    try { $callback(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected an invalid interval to fail');
}
same(Petit_Popular_Dates::range('7d'), ['2026-10-30', '2026-11-05'], '7 calendar days');
same(Petit_Popular_Dates::range('30d'), ['2026-10-07', '2026-11-05'], '30 calendar days');
same(Petit_Popular_Dates::range('week'), ['2026-11-02', '2026-11-05'], 'Monday week');
same(Petit_Popular_Dates::range('month'), ['2026-11-01', '2026-11-05'], 'Month');
same(Petit_Popular_Dates::range('days', 1), ['2026-11-05', '2026-11-05'], 'One day');
same(Petit_Popular_Dates::range('all'), null, 'All time');
same(Petit_Popular_Dates::events('next', 14), ['2026-11-05', '2026-11-18'], '14 inclusive days');
same(Petit_Popular_Dates::events('weekend'), ['2026-11-07', '2026-11-08'], 'Upcoming weekend');
$GLOBALS['clock'] = '2026-11-08 12:00:00';
same(Petit_Popular_Dates::events('weekend'), ['2026-11-07', '2026-11-08'], 'Sunday weekend');
$GLOBALS['clock'] = '2026-03-30 00:30:00';
same(Petit_Popular_Dates::range('7d'), ['2026-03-24', '2026-03-30'], 'DST boundary');
same(Petit_Popular_Dates::events('custom', 14, '2028-02-29', '2028-03-01'), ['2028-02-29', '2028-03-01'], 'Leap date');
rejects(fn() => Petit_Popular_Dates::range('custom', 7, '2026-02-30', '2026-03-10'));
rejects(fn() => Petit_Popular_Dates::range('custom', 7, '2026-11-05', '2026-11-01'));
rejects(fn() => Petit_Popular_Dates::range('custom', 7, 'yesterday', 'tomorrow'));
rejects(fn() => Petit_Popular_Dates::range('days', 0));
rejects(fn() => Petit_Popular_Dates::range('days', '2 days'));
rejects(fn() => Petit_Popular_Dates::events('next', 3661));
rejects(fn() => Petit_Popular_Dates::range('unknown'));
same(Petit_Popular_Dates::score(250, 4), 1000, 'News factor');
same(Petit_Popular_Dates::score(250, 2.5), 625, 'Event factor');
same(Petit_Popular_Dates::score(3, 1.5), 5, 'Rounding');
same(Petit_Popular_Dates::score(-10, 4), 0, 'Negative count');
echo "PASS: $checks date and multiplier checks\n";
