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
     * Run tests
     *
     * @return void
     */
    public function testVocal()
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
        $this->assertFalse($result, 'Validation on $test should have failed');

        // Fix error and attempt to save
        $input->replace(array(
            'description' => 'Parent',
            'children'    => array(
                array('description' => 'Child 1'),
                array('description' => 'Child 2'),
            )
        ));

        $test = new Test;
        $result = $test->saveRecursive($input->all());

        // Make sure validation passed
        $this->assertTrue($result, '$test was not created');

        // Make sure we have children
        $this->assertTrue(count($test->children) > 0, '$test children were not created');

        // Check child records
        foreach ($test->children as $child) $this->assertTrue(strpos($child->description, 'Child') === 0, '$test children were not saved correctly');

        // Test create method
        $input->replace(array(
            'description' => 'Parent B',
            'children'    => array(
                array('description' => 'Child 1a'),
                array('description' => 'Child 2a'),
            )
        ));

        $test2 = Test::create();

        // Make sure we have children
        $this->assertTrue(count($test2->children) > 0, '$test2 children were not created');

        // Check child records
        foreach ($test2->children as $child) $this->assertTrue(strpos($child->description, 'Child') === 0, '$test2 children were not saved correctly');

        // Update second relationship and save again
        $input->replace(array(
            'id'          => 1,
            'description' => 'Parent',
            'children'    => array(
                array('id' => 1, 'description' => 'Child 1'),
                array('id' => 2, 'description' => 'Child X'),
            )
        ));

        $test3 = clone $test;
        $result = $test3->saveRecursive();

        // Check save was successful
        $this->assertTrue($result, '$test3 was not updated');

        // Make sure child was updated
        $this->assertTrue($test3->children[1]->description == 'Child X', '$test3 were not updated correctly');

        // Test saving non-recursive
        $input->replace(array(
            'description' => 'Non-recursive'
        ));

        $test4 = new Test;
        $result = $test4->save();

        // Check save was successful
        $this->assertTrue($result, '$test4 was not created');

        // Test hashes
        $input->replace(array(
            'description' => 'Password test',
            'password'    => 'password'
        ));

        $test5 = new Test;
        $result = $test5->save();

        // Check save was successful
        $this->assertTrue($result, '$test5 was not created');
        $this->assertTrue($test5->password && $test5->password != $input->get('password'), '$test5 was not saved correctly');
        $this->assertTrue($test5->password != $input->get('password'), 'Password for $test5 was not hashed');

        // Test that the inverse relationship (belongsTo) works
        $input->replace(array(
            'description' => 'Child 3',
            'parent' => array(
                'description' => 'Parent 2',
            )
        ));

        $test6 = new TestChild;
        $result = $test6->saveRecursive();

        // Make sure validation passed
        $this->assertTrue($result, '$test6 was not created');

        // Make sure the parent has an ID (that means it was actually saved)
        $this->assertTrue( !! $test6->parent->id, 'Parent ID can not be found via $test6 child record');
    }
}
