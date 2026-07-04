<?php

namespace TCG\Voyager\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use TCG\Voyager\Models\Menu;

class MenuDisplayCacheTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Auth::loginUsingId(1);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Mirrors Laravel's own default scaffold since v11: cached objects are
        // not unserializable unless explicitly allow-listed. Menu::display()
        // must not cache hydrated Eloquent models under this default, or the
        // cached value silently comes back as __PHP_Incomplete_Class.
        $app['config']->set('cache.default', 'database');
        $app['config']->set('cache.serializable_classes', false);
    }

    public function testDisplayWorksWithRestrictedCacheUnserialization()
    {
        Cache::forget('voyager_menu_admin_id');

        // Cold cache: writes the cache entry.
        $items = Menu::display('admin', '_json');
        $this->assertNotFalse($items);
        $this->assertGreaterThan(0, $items->count());

        // Warm cache: reads the cache entry back.
        $items = Menu::display('admin', '_json');
        $this->assertNotFalse($items);
        $this->assertGreaterThan(0, $items->count());
    }
}
