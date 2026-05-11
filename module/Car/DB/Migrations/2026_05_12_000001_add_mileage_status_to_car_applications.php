<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMileageStatusToCarApplications extends Migration
{
    public function up()
    {
        Schema::table('car_applications', function (Blueprint $table) {
            $table->enum('mileage_status', ['normal', 'abnormal'])
                ->default('normal')
                ->comment('里程状态: normal=正常, abnormal=异常')
                ->after('end_km');
        });
    }

    public function down()
    {
        Schema::table('car_applications', function (Blueprint $table) {
            $table->dropColumn('mileage_status');
        });
    }
}
