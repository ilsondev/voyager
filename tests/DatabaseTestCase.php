<?php

namespace TCG\Voyager\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use TCG\Voyager\Models\User;
use TCG\Voyager\VoyagerServiceProvider;

/**
 * Standalone test base for the Database Manager tests.
 *
 * The shared TestCase still extends the legacy BrowserKit base, which is not
 * compatible with PHPUnit 12 (its tests are silently skipped). Migrating the
 * whole suite off BrowserKit is Phase 3; to keep the Phase 2 database-manager
 * tests runnable now, this base extends the standard Orchestra Testbench
 * TestCase directly and reuses the same environment/install bootstrap.
 */
abstract class DatabaseTestCase extends OrchestraTestCase
{
    protected $withDummy = true;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        if (!is_dir(base_path('routes'))) {
            mkdir(base_path('routes'));
        }

        if (!file_exists(base_path('routes/web.php'))) {
            file_put_contents(
                base_path('routes/web.php'),
                "<?php Route::get('/', function () {return view('welcome');});"
            );
        }

        $this->app->make('Illuminate\Contracts\Http\Kernel')->pushMiddleware('Illuminate\Session\Middleware\StartSession');
        $this->app->make('Illuminate\Contracts\Http\Kernel')->pushMiddleware('Illuminate\View\Middleware\ShareErrorsFromSession');

        $this->install();
    }

    protected function getPackageProviders($app)
    {
        return [
            VoyagerServiceProvider::class,
        ];
    }

    public function tearDown(): void
    {
        $this->app->forgetInstance(ExceptionHandler::class);

        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('voyager.user.namespace', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function install()
    {
        $this->artisan('voyager:install', ['--with-dummy' => $this->withDummy]);

        app(VoyagerServiceProvider::class, ['app' => $this->app])->loadAuth();

        if (file_exists(base_path('routes/web.php'))) {
            require base_path('routes/web.php');
        }
    }
}
