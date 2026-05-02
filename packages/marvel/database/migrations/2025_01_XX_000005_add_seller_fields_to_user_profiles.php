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
        Schema::table('user_profiles', function (Blueprint $table) {
            // Добавляем поля после contact, так как phone_verified_at может не существовать
            $table->string('seller_id')->nullable()->after('contact')->comment('Seller ID');
            $table->string('ownership_form')->nullable()->after('seller_id')->comment('Форма собственности');
            $table->string('full_name')->nullable()->after('ownership_form')->comment('ФИО');
            $table->string('company_name')->nullable()->after('full_name')->comment('Название компании');
            $table->text('registration_address')->nullable()->after('company_name')->comment('Адрес регистрации');
            $table->text('actual_address')->nullable()->after('registration_address')->comment('Фактический адрес');
            $table->string('tax_id')->nullable()->after('actual_address')->comment('ИНН');
            $table->string('payment_method')->nullable()->after('tax_id')->comment('Платёжный метод');
            $table->string('company_account')->nullable()->after('payment_method')->comment('Расчётный счёт компании');
            $table->string('bank_bik')->nullable()->after('company_account')->comment('БИК банка');
            $table->string('bank_name')->nullable()->after('bank_bik')->comment('Название банка');
            $table->string('currency')->default('RUB')->after('bank_name')->comment('Валюта');
            $table->text('contract_text')->nullable()->after('currency')->comment('Текст договора');
            $table->boolean('contract_read')->default(false)->after('contract_text')->comment('Договор прочитан');
            $table->boolean('contract_accepted')->default(false)->after('contract_read')->comment('Договор принят');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_accepted')->comment('Дата подписания договора');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['seller_id', 'ownership_form', 'full_name', 'company_name', 'registration_address', 'actual_address', 'tax_id', 'payment_method', 'company_account', 'bank_bik', 'bank_name', 'currency', 'contract_text', 'contract_read', 'contract_accepted', 'contract_signed_at']);
        });
    }
};

