<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ? This migration added due oauth services
        Schema::table('users', function (Blueprint $table) {
            // remove previous unique index
            $table->dropUnique('users_email_unique'); 

            // make email nullable
            $table->string('email')->nullable()->change();

            // add unique index again 
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']); // remove unique
            $table->string('email')->nullable(false)->change(); // back to previous
            $table->unique('email'); // add unique again
        });
    }
};
