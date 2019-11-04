<?php

namespace Nikolag\Square\Tests;

use Faker\Factory as Faker;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    use DatabaseMigrations, WithoutMiddleware;

    /**
     * @var \Faker\Factory
     */
    protected $faker;

    /**
     * Setup the test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        // make sure, our .env file is loaded
        $this->app->useEnvironmentPath(__DIR__.'/..');
        $this->app->bootstrapWith([LoadEnvironmentVariables::class]);
        parent::getEnvironmentSetUp($this->app);
        // setup database migrations, factories and migrate
        $this->loadLaravelMigrations(['--database' => 'square_test']);
        $this->artisan('migrate', ['--database' => 'square_test']);
        $this->withFactories(__DIR__.'/../src/database/factories');
        $this->faker = Faker::create();
    }

    /**
     * Add service providers this package depends on.
     */
    protected function getPackageProviders($app)
    {
        return [
            'Nikolag\Square\Providers\SquareServiceProvider',
            'Nikolag\Core\Providers\MigrationServiceProvider',
        ];
    }

    /**
     * Add aliases this package depends on.
     */
    protected function getPackageAliases($app)
    {
        return [
            'Square' => 'Nikolag\Square\Facades\Square',
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'square_test');
        $app['config']->set('database.connections.square_test', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
//        $app['config']->set('database.default', 'square_test');
//        $app['config']->set('database.connections.square_test', [
//            'driver' => 'mysql',
//            'host' => '127.0.0.1',
//            'port' => '3306',
//            'database' => 'square_test',
//            'username' => 'root',
//            'password' => '',
//            'unix_socket' => '',
//            'charset' => 'utf8mb4',
//            'collation' => 'utf8mb4_unicode_ci',
//            'prefix' => '',
//            'strict' => true,
//            'engine' => null
//        ]);
    }
}
