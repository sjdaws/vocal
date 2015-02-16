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
     * Test events and callbacks
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
        $this->assertNull($test2->name, 'beforeHydrate callback failed to prevent hydration');

        // afterHydrate should alter the description
        $test3 = new Test;
        $test3->setCallbackTest(2);
        $test3->hydrateModel();
        $this->assertTrue($test3->name == 'Callback', 'afterHydrate callback failed to alter model');
    }

    /**
     * Test validation
     *
     * @return void
     */
    public function testValidate()
    {
        $data = array(
            'description' => 'Parent record'
        );

        $input = $this->app->make('request');
        $input->replace($data);

        // Test with invalid input (name is missing)
        $test1 = new Test;
        $this->assertFalse($test1->validate(), 'Validation should fail as name is missing');

        // Reset data
        $data = array(
            'name'        => 'Parent',
            'description' => 'The parent'
        );

        $input->replace($data);

        // Test again with correct data
        $test2 = new Test;
        $this->assertTrue($test2->validate(), "Validation should pass but it didn't");
    }

    /**
     * Test recursive validation
     *
     * @return void
     */
    public function testValidateRecursive()
    {
        $data = array(
            'name'        => 'Parent',
            'description' => 'Parent record',
            'children'    => array(
                array('name' => ''),
                array(
                    'name'     => 'Child 2',
                    'children' => array(
                        array('name' => 'ChildChild 1')
                    )
                )
            )
        );

        $input = $this->app->make('request');
        $input->replace($data);

        // Test with invalid input (name is missing)
        $test1 = new Test;
        $this->assertFalse($test1->validateRecursive(), 'Recursive validation should fail as child name is missing');
dd(\Lang::get('validation.required'));
dd($test1->getValidationErrors());

        // Reset data
        $data = array(
            'name'        => 'Parent',
            'description' => 'Parent record',
            'children'    => array(
                array('name' => 'Child 1'),
                array(
                    'name'     => 'Child 2',
                    'children' => array(
                        array('name' => 'ChildChild 1')
                    )
                )
            )
        );

        $input->replace($data);

        // Test again with correct data
        $test2 = new Test;
        $this->assertTrue($test2->validateRecursive(), "Recursive validation should pass but it didn't");
    }
}
