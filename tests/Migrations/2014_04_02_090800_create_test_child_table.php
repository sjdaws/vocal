<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestChildTable extends Migration
{
	/**
	 * The table name
	 *
	 * @var string
	 */
	private $table = 'test_children';

	/**
	 * Run the migrations
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create($this->table, function($t)
		{
			$t->increments('id')->unsigned();
			$t->integer('test_id')->unsigned()->nullable();
			$t->string('description', 100);
			$t->timestamps();
        });
    }

	/**
	 * Reverse the migrations
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop($this->table);
	}
}
