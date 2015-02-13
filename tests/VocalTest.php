<?php

namespace Sjdaws\Tests;

use Illuminate\Contracts\Console\Kernel;
use Orchestra\Testbench\TestCase;
use Sjdaws\Tests\Models\Test;
use Sjdaws\Tests\Models\TestChild;
use ReflectionClass;
use ReflectionException;

class Tests extends TestCase
{
    /**
     * Disable query log to save memory
     *
     * @return null
     */
    private function disableQueryLog()
    {
        $this->app->make('Illuminate\Database\DatabaseManager')->connection()->disableQueryLog();
    }

    /**
     * Define environment setup
     *
     * @param  Illuminate\Foundation\Application $app
     * @return null
     */
    protected function getEnvironmentSetUp($app)
    {
        // Use package src path as base
        $app['path.base'] = __DIR__ . '/../src';

        // Set up in memory database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', array(
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ));
    }

    /**
     * Migrate a fresh copy of the database
     *
     * @return null
     */
    private function migrateDatabase()
    {
        // Laravel 5 changes console class
        try
        {
            $console = $this->app->make('Illuminate\Contracts\Console\Kernel');
        }
        catch (ReflectionException $Exception)
        {
            $console = $this->app->make('artisan');
        }

        $console->call('migrate', array('--database' => 'testbench', '--path' => '../tests/Migrations'));
    }

    /**
     * Set up test environment
     *
     * @return null
     */
    public function setUp()
    {
        parent::setUp();
        $this->disableQueryLog();
        $this->migrateDatabase();
    }

    /**
     * Test hydration
     *
     * @return null
     */
    public function testHydration()
    {
        $data = array(
            'name'        => 'Parent',
            'description' => 'The parent'
        );

        // Fake input
        $input = $this->app->make('request');
        $input->replace($data);

        // Hydration is enabled by default, disable to make sure it doesn't work first
        $test1 = new Test;
        $test1->setAllowHydration(false);
        $test1->hydrateModel();
        $this->assertNull($test1->name, "Model was hydrated when it shouldn't have been");

        // Enable hydration and try again
        $test2 = new Test;
        $test2->setAllowHydration(true);
        $test2->hydrateModel();
        $this->assertTrue($test2->name == $data['name'], "Name doesn't match input data");

        // Reset data
        $data = array(
            'name' => 'Banana'
        );

        // Test that a passed array overrides input
        $test3 = new Test;
        $test3->setAllowHydration(true);
        $test3->hydrateModel($data);
        $this->assertTrue($test3->name == $data['name'], "Name doesn't match passed array");
        $this->assertNull($test3->description, "Description should be empty but isn't");
    }

    /**
     * Test events
     *
     * @return null
     */
    public function testEvents()
    {
        $data = array(
            'name'        => 'Parent',
            'description' => 'The parent'
        );

        // Fake input
        $input = $this->app->make('request');
        $input->replace($data);

        // Make sure everything is working correctly
        $test1 = new Test;
        $test1->hydrateModel();
        $this->assertTrue($test1->name == $data['name'], "Name wasn't hydrated during event control test");
        $this->assertTrue($test1->description == $data['description'], "Description wasn't hydrated during event control test");

        // beforeHydrate returns false so hydration should abort
        $test2 = new Test;
        $test2->setCallbackTest(1);
        $test2->hydrateModel();
        $this->assertNull($test2->name, "beforeHydrate callback failed to prevent hydration");

        // afterHydrate should alter the description
        $test3 = new Test;
        $test3->setCallbackTest(2);
        $test3->hydrateModel();
        $this->assertTrue($test3->name == 'Callback', "afterHydrate callback failed to alter model");
    }
}
