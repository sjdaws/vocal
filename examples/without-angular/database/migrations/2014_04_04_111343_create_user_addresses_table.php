<?php

use Illuminate\Database\Migrations\Migration;

class CreateUserAddressesTable extends Migration
{
	/**
	 * The table name
	 *
	 * @var string
	 */
	private $table = 'user_addresses';

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
			$t->integer('user_id')->unsigned();
			$t->string('address', 100);
			$t->string('city', 50);

			$t->timestamps();
			$t->softDeletes();

            $t->index('user_id');
            $t->index(array('deleted_at', 'user_id'));
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
