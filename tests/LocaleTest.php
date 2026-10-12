<?php

use Laf\Util\Locale;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Locale display helpers: numbers and temporal values formatted with the locale.* settings.
 */
class LocaleTest extends TestCase
{
    protected function setUp(): void
    {
        foreach (['locale.date.format', 'locale.datetime.format', 'locale.time.format', 'locale.decimal_separator', 'locale.thousands_separator'] as $key) {
            Settings::remove($key);
        }
    }

    protected function tearDown(): void
    {
        $this->setUp();
    }

    /** @test */
    public function numbers_are_formatted_with_the_default_separators()
    {
        $this->assertSame('1,234,567.50', Locale::formatNumber('1234567.5'));
        $this->assertSame('1,234,567.50', Locale::formatNumber(1234567.5));
        $this->assertSame('42.00', Locale::formatNumber(42));
        $this->assertSame('-0.1', Locale::formatNumber('-0.10', 1));
        $this->assertSame('1,000', Locale::formatNumber('1000', 0));
        $this->assertSame('1,234.5', Locale::formatNumber('1234.5000', null), 'null decimals keeps the significant digits');
        $this->assertSame('12', Locale::formatNumber('12.000', null));
    }

    /** @test */
    public function numbers_follow_the_configured_separators_and_accept_typed_input()
    {
        Settings::set('locale.decimal_separator', ',');
        Settings::set('locale.thousands_separator', '.');
        $this->assertSame('1.234.567,50', Locale::formatNumber('1234567.5'));
        $this->assertSame('1.234,50', Locale::formatNumber('1.234,5'), 'a user-typed value is parsed first');
        $this->assertSame('0,25', Locale::formatNumber('0,25'));
    }

    /** @test */
    public function empty_and_invalid_numbers_give_the_empty_text()
    {
        $this->assertSame('', Locale::formatNumber(null));
        $this->assertSame('', Locale::formatNumber(''));
        $this->assertSame('n/a', Locale::formatNumber('abc', 2, 'n/a'));
        $this->assertSame('-', Locale::formatNumber(null, 2, '-'));
    }

    /** @test */
    public function dates_times_and_datetimes_are_formatted_from_iso_values_objects_or_display_strings()
    {
        $this->assertSame('October 12, 2026', Locale::formatDate('2026-10-12'));
        $this->assertSame('October 12, 2026', Locale::formatDate('2026-10-12 14:30:00'), 'a datetime stored value shows its date');
        $this->assertSame('October 12, 2026', Locale::formatDate(new \DateTimeImmutable('2026-10-12 09:00:00')));
        $this->assertSame('October 12, 2026', Locale::formatDate('October 12, 2026'), 'already in the display format');
        $this->assertSame('October 12, 2026 14:30', Locale::formatDateTime('2026-10-12 14:30:15'));
        $this->assertSame('October 12, 2026 00:00', Locale::formatDateTime('2026-10-12'));
        $this->assertSame('2:30 PM', Locale::formatTime('14:30:00'));
        $this->assertSame('2:30 PM', Locale::formatTime('14:30'));
        $this->assertSame('', Locale::formatDate(null));
        $this->assertSame('', Locale::formatDate(''));
        $this->assertSame('?', Locale::formatDate('2026-02-30', '?'), 'an invalid date is not shown');
        $this->assertSame('?', Locale::formatTime('nope', '?'));
    }

    /** @test */
    public function temporal_formats_follow_the_settings()
    {
        Settings::set('locale.date.format', 'd.m.Y');
        Settings::set('locale.datetime.format', 'd.m.Y H:i:s');
        Settings::set('locale.time.format', 'H:i');
        $this->assertSame('12.10.2026', Locale::formatDate('2026-10-12'));
        $this->assertSame('12.10.2026', Locale::formatDate('12.10.2026'));
        $this->assertSame('12.10.2026 14:30:15', Locale::formatDateTime('2026-10-12 14:30:15'));
        $this->assertSame('14:30', Locale::formatTime('14:30:59'));
    }
}
