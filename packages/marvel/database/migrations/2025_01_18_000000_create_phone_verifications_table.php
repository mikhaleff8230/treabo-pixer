<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phone_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index()->comment('Номер телефона в формате +79031290826');
            $table->string('uuid')->unique()->comment('UUID сообщения из REDSMS');
            $table->string('call_from_number', 20)->nullable()->comment('Номер, с которого поступил звонок');
            $table->timestamp('expires_at')->index()->comment('Время истечения (5 минут)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('phone_verifications');
    }
};











