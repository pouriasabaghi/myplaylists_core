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
       Schema::create('songs', function (Blueprint $table) {
           $table->id();
           $table->string('time')->nullable();
           $table->string('cover')->nullable();
           $table->string('name')->nullable();
           $table->string('artist')->nullable();
           $table->string('album')->nullable();
           $table->string('path');
           $table->timestamps();
       });
   }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
