<?php

use Core\Database\Migration;
use Core\Database\Schema;
use Core\Database\Table;

return new class implements Migration
{
    /**
     * Jalankan migrasi.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('guests', function (Table $table) {
            $table->id();

            $table->integer('user_id');
            $table->string('name', 50);
            $table->string('token', 32)->unique();
            $table->string('rsvp_status', 20)->default('pending');
            $table->integer('guest_count')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->timeStamp();
        });

        Schema::table('comments', function (Table $table) {
            $table->addColumn(function (Table $table) {
                $table->integer('guest_id')->nullable();
            });
        });
    }

    /**
     * Kembalikan seperti semula.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('comments', function (Table $table) {
            $table->dropColumn('guest_id');
        });

        Schema::drop('guests');
    }
};