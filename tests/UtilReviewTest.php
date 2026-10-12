<?php

declare(strict_types=1);

use Laf\Util\Settings;
use Laf\Util\UrlParser;
use Laf\Util\Util;
use PHPUnit\Framework\TestCase;

/**
 * Util folder review (2026-10-12): UUID ids in the route and in links, encoded link parts,
 * array request values, reset(), getLink(), Util date/uuid/coalesce helpers, Settings nested
 * loading, typed readers and settings files.
 */
class UtilReviewTest extends TestCase
{
    protected function setUp(): void
    {
        UrlParser::reset();
        Settings::set('settings.use_pretty_url', true);
    }

    protected function tearDown(): void
    {
        unset($_GET['uriRewrite'], $_GET['module'], $_GET['submodule'], $_GET['action'], $_GET['id'], $_GET['mod']);
        Settings::set('settings.use_pretty_url', true);
        UrlParser::reset();
    }

    /** @test */
    public function a_uuid_id_is_recognised_and_lower_cased(): void
    {
        $_GET['uriRewrite'] = 'crm/customer/view/3F2504E0-4F89-11D3-9A0C-0305E82C3301';
        $this->assertSame('3f2504e0-4f89-11d3-9a0c-0305e82c3301', UrlParser::getId());
        $this->assertSame('3F2504E0-4F89-11D3-9A0C-0305E82C3301', UrlParser::getRawId());
        $this->assertSame('/crm/customer/update/3f2504e0-4f89-11d3-9a0c-0305e82c3301', UrlParser::getUpdateLink());
    }

    /** @test */
    public function link_builders_accept_numbers_and_uuids_and_fall_back_to_the_route_id(): void
    {
        $_GET['uriRewrite'] = 'crm/customer/view/7';
        $this->assertSame('/crm/customer/view/9', UrlParser::getViewLink(9));
        $this->assertSame('/crm/customer/view/9', UrlParser::getViewLink('9'));
        $this->assertSame('/crm/customer/delete/3f2504e0-4f89-11d3-9a0c-0305e82c3301', UrlParser::getDeleteLink('3f2504e0-4f89-11d3-9a0c-0305e82c3301'));
        $this->assertSame('/crm/customer/view/7', UrlParser::getViewLink('abc'), 'anything else means the route id');
        $this->assertSame('/crm/customer/view/7', UrlParser::getViewLink(null));
        $this->assertSame('/crm/customer/new', UrlParser::getInsertLink());
        $this->assertSame(UrlParser::getNewLink(), UrlParser::getInsertLink());
        $this->assertSame('/crm/customer/export/7', UrlParser::getLink('export'));
        $this->assertSame('/crm/customer/list/all', UrlParser::getListLink());
    }

    /** @test */
    public function a_route_without_id_builds_links_with_an_empty_id_part_as_before(): void
    {
        $_GET['uriRewrite'] = 'crm/customer/list';
        $this->assertNull(UrlParser::getId());
        $this->assertSame('/crm/customer/view/', UrlParser::getViewLink());
        $this->assertSame('/crm/customer/view/4', UrlParser::getViewLink(4));
        $this->assertSame('/crm/customer/new', UrlParser::getNewLink());
        $this->assertSame('/crm/customer/export', UrlParser::getLink('export', null, false));
    }

    /** @test */
    public function module_and_submodule_are_url_encoded_in_links(): void
    {
        $_GET['uriRewrite'] = 'ad min/us<er/view/1';
        $this->assertSame('ad min', UrlParser::getModule());
        $this->assertSame('/ad%20min/us%3Cer/view/1', UrlParser::getViewLink());

        Settings::set('settings.use_pretty_url', false);
        UrlParser::reset();
        unset($_GET['uriRewrite']);
        $_GET['module'] = 'a&b';
        $_GET['submodule'] = 'c';
        $_GET['id'] = '5';
        $this->assertSame('?module=a%26b&submodule=c&action=update&id=5', UrlParser::getUpdateLink());
        $this->assertSame('?module=a%26b&submodule=c&action=new', UrlParser::getNewLink());
    }

    /** @test */
    public function array_request_values_count_as_absent(): void
    {
        Settings::set('settings.use_pretty_url', false);
        $_GET['module'] = ['x'];
        $_GET['id'] = ['1'];
        $this->assertSame('', UrlParser::getModule());
        $this->assertNull(UrlParser::getId());
        $this->assertNull(UrlParser::getRawId());
    }

    /** @test */
    public function reset_rereads_the_request(): void
    {
        $_GET['uriRewrite'] = 'a/b/view/1';
        $this->assertSame('a', UrlParser::getModule());
        $_GET['uriRewrite'] = 'c/d/view/2';
        $this->assertSame('a', UrlParser::getModule(), 'parsed once per request');
        UrlParser::reset();
        $this->assertSame('c', UrlParser::getModule());
        $this->assertSame(2, UrlParser::getId());
        $this->assertTrue(UrlParser::isUuid('3f2504e0-4f89-11d3-9a0c-0305e82c3301'));
        $this->assertFalse(UrlParser::isUuid('3f2504e0'));
    }

    /** @test */
    public function format_date_for_db_parses_the_locale_format_and_iso_and_rejects_garbage(): void
    {
        $this->assertSame('2024-01-15', Util::formatDateForDb('January 15, 2024'));
        $this->assertSame('2024-01-15', Util::formatDateForDb('2024-01-15'));
        $this->assertNull(Util::formatDateForDb('garbage'));
        $this->assertNull(Util::formatDateForDb(''));
        $this->assertNull(Util::formatDateForDb(null));
        $this->assertNull(Util::formatDateForDb('February 30, 2024'));
    }

    /** @test */
    public function util_helpers_keep_their_contracts(): void
    {
        $this->assertSame('', Util::tableFieldNameToLabel(null));
        $this->assertSame('', Util::tableNameToClassName(null));
        $this->assertSame([], Util::coalesce(null, [], ['a']), 'an array is always a value, an empty one too');
        $this->assertSame(0, Util::coalesce(0, 1));
        $this->assertNull(Util::coalesce());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Util::uuid());
        $this->assertFalse(Util::isJSON(['a']));
        $this->assertSame('2 minutes ago', Util::timeAgo(new \DateTimeImmutable('-125 seconds')));
    }

    /** @test */
    public function settings_load_nested_arrays_and_keep_lists(): void
    {
        Settings::load(['utilreview' => ['database' => ['hostname' => 'h', 'port' => '3306'], 'tags' => ['a', 'b'], 'empty' => []]]);
        $this->assertSame('h', Settings::get('utilreview.database.hostname'));
        $this->assertSame(['a', 'b'], Settings::get('utilreview.tags'));
        $this->assertSame([], Settings::get('utilreview.empty'));
        $this->assertFalse(Settings::has('utilreview.database'));

        Settings::load(['x' => 1], 'utilreview.prefixed');
        $this->assertSame(1, Settings::get('utilreview.prefixed.x'));
        Settings::remove('utilreview.prefixed.x');
        $this->assertFalse(Settings::has('utilreview.prefixed.x'));
        $this->assertArrayHasKey('utilreview.tags', Settings::getInstance()->getProperties());
    }

    /** @test */
    public function typed_readers_fall_back_to_their_defaults(): void
    {
        Settings::set('utilreview.s', 12);
        Settings::set('utilreview.i', '42');
        Settings::set('utilreview.f', '4.5');
        Settings::set('utilreview.arr', [1]);
        Settings::set('utilreview.b', true);
        $this->assertSame('12', Settings::getString('utilreview.s'));
        $this->assertSame('1', Settings::getString('utilreview.b'));
        $this->assertSame('d', Settings::getString('utilreview.arr', 'd'));
        $this->assertSame('d', Settings::getString('utilreview.missing', 'd'));
        $this->assertSame(42, Settings::getInt('utilreview.i'));
        $this->assertSame(12, Settings::getInt('utilreview.s'));
        $this->assertSame(1, Settings::getInt('utilreview.b'));
        $this->assertSame(7, Settings::getInt('utilreview.f', 7), 'not a whole number');
        $this->assertSame(7, Settings::getInt('utilreview.missing', 7));
    }

    /** @test */
    public function settings_files_round_trip(): void
    {
        $dir = sys_get_temp_dir();
        $php = tempnam($dir, 'laf') . '.php';
        $json = tempnam($dir, 'laf') . '.json';
        $ini = tempnam($dir, 'laf') . '.ini';
        try {
            Settings::set('utilreview.file.name', 'n');
            Settings::set('utilreview.file.flag', true);
            $this->assertTrue(Settings::getInstance()->saveToFile($php));
            $this->assertTrue(Settings::getInstance()->saveToFile($json));
            $this->assertFalse(Settings::getInstance()->saveToFile($ini), 'ini is read-only');
            $this->assertFalse(Settings::getInstance()->saveToFile(null));

            $loaded = include $php;
            $this->assertSame('n', $loaded['utilreview.file.name']);
            Settings::remove('utilreview.file.name');
            $this->assertTrue(Settings::getInstance()->loadFromFile($php));
            $this->assertSame('n', Settings::get('utilreview.file.name'));
            Settings::remove('utilreview.file.name');
            $this->assertTrue(Settings::getInstance()->loadFromFile($json));
            $this->assertSame('n', Settings::get('utilreview.file.name'));
            $this->assertTrue(Settings::get('utilreview.file.flag'));

            file_put_contents($ini, "[utilreview]\nini_key = 5\nini_flag = true\n");
            $this->assertTrue(Settings::getInstance()->loadFromFile($ini));
            $this->assertSame(5, Settings::get('utilreview.ini_key'));
            $this->assertTrue(Settings::get('utilreview.ini_flag'));

            $this->assertFalse(Settings::getInstance()->loadFromFile($dir . '/does-not-exist.php'));
            file_put_contents($json, 'not json');
            $this->assertFalse(Settings::getInstance()->loadFromFile($json));
        } finally {
            foreach ([$php, $json, $ini, substr($php, 0, -4), substr($json, 0, -5), substr($ini, 0, -4)] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
