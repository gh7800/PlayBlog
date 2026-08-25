<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 请示新增字段：
 * - type   请示类型（zongbanhui=总办会 / dangweihui=党委会 / dongshihui=董事会）
 * - remark 备注
 */
class AddTypeRemarkToDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('type')->nullable()->after('code')->comment('请示类型：zongbanhui/dangweihui/dongshihui');
            $table->text('remark')->nullable()->after('content')->comment('备注');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['type', 'remark']);
        });
    }
}
