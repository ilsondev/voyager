<?php

namespace TCG\Voyager\Tests;

use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;

class AssetsTest extends TestCase
{
    protected $prefix = '/voyager-assets?path=';

    public function setUp(): void
    {
        parent::setUp();

        Auth::loginUsingId(1);
    }

    public function testCanOpenFileInAssets()
    {
        $url = route('voyager.dashboard').$this->prefix.'css/app.css';

        $response = $this->call('GET', $url);
        $this->assertEquals(200, $response->status(), $url.' did not return a 200');
    }

    public static function urlProvider()
    {
        return [
            ['../dummy_content/pages/page1.jpg'],
            ['..../dummy_content/pages/page1.jpg'],
            ['images/../../dummy_content/pages/page1.jpg'],
            ['....//dummy_content/pages/page1.jpg'],
            ['..\dummy_content/pages/page1.jpg'],
            ['....\dummy_content/pages/page1.jpg'],
            ['images/..\..\dummy_content/pages/page1.jpg'],
            ['images/....\\....\\dummy_content/pages/page1.jpg'],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testCannotOpenFileOutsideAssets($url)
    {
        $response = $this->call('GET', route('voyager.dashboard').$this->prefix.$url);
        $this->assertContains($response->status(), [404, 500], $url.' did not return a 404 or 500');
    }

    public function testAssetUrlIsCacheBustedByFileVersion()
    {
        // A real, published asset gets a content-version token so the 1-year
        // cache lifetime doesn't serve stale JS/CSS across package rebuilds.
        $this->assertMatchesRegularExpression(
            '/[?&]v=\d+/',
            voyager_asset('css/app.css'),
            'voyager_asset() should append a version token for existing assets'
        );

        // A path with no backing file must not append a bogus version token.
        $this->assertStringNotContainsString(
            '&v=',
            voyager_asset('does/not/exist.js')
        );
    }
}
