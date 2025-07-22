<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        foreach (glob(base_path('module/*/*ServiceProvider.php')) as $providerPath) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $providerPath);

            // 转换路径分隔符为反斜杠
            $class = str_replace(['/', '\\'], '\\', $relativePath);
            $class = str_replace('.php', '', $class);

            // 强制把 module 改成 Module（首字母大写）
            $class = preg_replace('/^module\\\\/', 'Module\\', $class);

            if (class_exists($class)) {
                logger("✅ 注册成功：$class");
                $this->app->register($class);
            } else {
                logger()->warning("❌ 未找到类: $class");
            }
        }


    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191); // 限制字符串字段默认长度
        Paginator::useTailwind();
    }
}
