<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
	/**
	 * The table name
	 *
	 * @var string
	 */
	private $table = 'users';

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
			$t->string('username', 100);
			$t->string('email');

			$t->timestamps();
			$t->softDeletes();

            $t->index('username');
            $t->index(array('deleted_at', 'username'));
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
