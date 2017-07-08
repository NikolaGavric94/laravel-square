<?php
namespace Nikolag\Square\Tests;

use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Orchestra\Testbench\TestCase as BaseTestCase;


class TestCase extends BaseTestCase {

	use DatabaseMigrations, DatabaseTransactions, WithoutMiddleware;

    /**
     * @var Faker\Factory
     */
    protected $faker;
    
    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'square_test']);
        $this->loadLaravelMigrations(['--database' => 'square_test']);
        $this->withFactories(__DIR__.'/../src/database/factories');
        $this->faker = Faker::create();
    }

    /**
     * Add service providers this package depends on.
     */
    protected function getPackageProviders($app)
    {
        return ['Nikolag\Square\Providers\SquareServiceProvider'];
    }

    /**
     * Add aliases this package depends on.
     */
    protected function getPackageAliases($app)
    {
        return [
            'Square' => 'Nikolag\Square\Facades\Square'
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
    */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'square_test');
        $app['config']->set('database.connections.square_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => ''
        ]);
    }
}