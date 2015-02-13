<?php

namespace Sjdaws\Tests;

use Orchestra\Testbench\TestCase;
use Sjdaws\Tests\Models\Test;
use Sjdaws\Tests\Models\TestChild;

class Tests extends TestCase
{
    /**
     * Define environment setup
     *
     * @param Illuminate\Foundation\Application $app
     * @return void
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
     * Set up test environment
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        // Turn off query log to save memory
        $this->app->make('Illuminate\Database\DatabaseManager')->connection()->disableQueryLog();

        // Migrate fresh copy of database
        $artisan = (class_exists('Illuminate\Contracts\Console\Kernel')) ? 'Illuminate\Contracts\Console\Kernel' : 'artisan';
        $this->app->make($artisan)->call('migrate', array('--database' => 'testbench', '--path' => '../tests/Migrations'));
    }

    /**
     * Test validate()
     *
     * @return void
     */
    public function testValidate()
    {
        $input = $this->app->make('request');

        // Create a record which is invalid
        $input->replace(array(
            'description' => ''
        ));

        $test = new Test;
        $result = $test->validate();

        // Make sure validation failed due to second child not having a description
        $this->assertFalse($result, 'Validation should fail');

        // Add description and try again
        $input->replace(array(
            'description' => 'Parent'
        ));

        $result = $test->validate();

        // Make sure validation failed due to second child not having a description
        $this->assertTrue($result, 'Validation should pass');
    }

    /**
     * Test validateRecursive()
     *
     * @return void
     */
    public function testValidateRecursive()
    {
        $input = $this->app->make('request');

        // Create a record with some children, one of which is invalid
        $input->replace(array(
            'description' => 'Parent',
            'children'    => array(
                array('description' => 'Child 1'),
                array('description' => ''),
            )
        ));

        $test = new Test;
        $result = $test->validateRecursive();

        // Make sure validation failed due to second child not having a description
        $this->assertFalse($result, 'Recursive validation should fail');

        // Add description and try again
        $input->replace(array(
            'description' => 'Parent',
            'children'    => array(
                array('description' => 'Child 1'),
                array('description' => 'Child 2'),
            )
        ));

        $result = $test->validateRecursive();

        // Make sure validation failed due to second child not having a description
        $this->assertTrue($result, 'Recursive validation should pass');
    }

    /**
     * Test save()
     *
     * @return void
     */
    public function testSave()
    {
        $input = $this->app->make('request');
    }

    /**
     * Test saveRecursive()
     *
     * @return void
     */
    public function testSaveRecursive()
    {
        $input = $this->app->make('request');
    }

    /**
     * Test create()
     *
     * @return void
     */
    public function testCreate()
    {
        $input = $this->app->make('request');
    }

    /**
     * Run tests
     *
     * @return void
     */
    public function testVocal()
    {




return;

        // Fix error and attempt to save
        $input->replace(array(
            'description' => 'Parent',
            'children'    => array(
                array('description' => 'Child 1'),
                array('description' => 'Child 2'),
            )
        ));

        $test3 = new Test;
        $result = $test3->saveRecursive($input->all());

        // Make sure validation passed
        $this->assertTrue($result, '$test3 was not created');

        // Make sure we have children
        $this->assertTrue(count($test3->children) > 0, '$test3 children were not created');

        // Check child records
        foreach ($test3->children as $child) $this->assertTrue(strpos($child->description, 'Child') === 0, '$test3 children were not saved correctly');

        // Test create method
        $input->replace(array(
            'description' => 'Parent B',
            'children'    => array(
                array('description' => 'Child 1a'),
                array('description' => 'Child 2a'),
            )
        ));

        $test4 = Test::create();

        // Make sure we have children
        $this->assertTrue(count($test4->children) > 0, '$test4 children were not created');

        // Check child records
        foreach ($test4->children as $child) $this->assertTrue(strpos($child->description, 'Child') === 0, '$test4 children were not saved correctly');

        // Update second relationship and save again
        $input->replace(array(
            'id'          => 1,
            'description' => 'Parent',
            'children'    => array(
                array('id' => 1, 'description' => 'Child 1'),
                array('id' => 2, 'description' => 'Child X'),
            )
        ));

        $test5 = clone $test;
        $result = $test5->saveRecursive();

        // Check save was successful
        $this->assertTrue($result, '$test5 was not updated');

        // Make sure child was updated
        $this->assertTrue($test5->children[1]->description == 'Child X', '$test5 were not updated correctly');

        // Test saving non-recursive
        $input->replace(array(
            'description' => 'Non-recursive'
        ));

        $test6 = new Test;
        $result = $test6->save();

        // Check save was successful
        $this->assertTrue($result, '$test6 was not created');

        // Test hashes
        $input->replace(array(
            'description' => 'Password test',
            'password'    => 'password'
        ));

        $test7 = new Test;
        $result = $test7->save();

        // Check save was successful
        $this->assertTrue($result, '$test7 was not created');
        $this->assertTrue($test7->password && $test7->password != $input->get('password'), '$test7 was not saved correctly');
        $this->assertTrue($test7->password != $input->get('password'), 'Password for $test7 was not hashed');

        // Test that the inverse relationship (belongsTo) works
        $input->replace(array(
            'description' => 'Child 3',
            'parent' => array(
                'description' => 'Parent 2',
            )
        ));

        $test8 = new TestChild;
        $result = $test8->saveRecursive();

        // Make sure validation passed
        $this->assertTrue($result, '$test8 was not created');

        // Make sure the parent has an ID (that means it was actually saved)
        $this->assertTrue( !! $test8->parent->id, 'Parent ID can not be found via $test8 child record');
    }
}
