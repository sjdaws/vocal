<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestChildChildTable extends Migration
{
    /**
     * The table name
     *
     * @var string
     */
    private $table = 'test_child_child';

    /**
     * Run the migrations
     *
     * @return void
     */
    public function up()
    {
        Schema::create($this->table, function(Blueprint $t)
        {
            $t->increments('id')->unsigned();
            $t->integer('test_child_id')->unsigned()->nullable();
            $t->string('name', 100);
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
