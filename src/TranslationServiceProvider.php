<?php

namespace Mosab\Translation;

use Exception;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Mosab\Translation\Middleware\RequestLanguage;
use Mosab\Translation\Models\TranslationsLanguage;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_translations_table.php.stub' => $this->getMigrationFileName('create_translations_table.php'),
            __DIR__ . '/../database/migrations/create_translations_languages_table.php.stub' => $this->getMigrationFileName('create_translations_languages_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders/TranslationsLanguageSeeder.php.stub' =>  database_path('seeders/TranslationsLanguageSeeder.php'),
        ], 'seeders');

        // Publish the JSON file when the package is published
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../src/public/languageUniversalCode.json' => public_path('languageUniversalCode.json'),
            ], 'public');
        }

        try {
            if (Schema::hasTable('translations_languages')) {
                $languages = TranslationsLanguage::query()->pluck('code')->toArray();
                RequestLanguage::$all_languages = $languages;
            }
        } catch (Exception) {
        }
    }

    protected function getMigrationFileName($migrationFileName)
    {
        $timestamp = date('Y_m_d_His');
        return database_path("migrations/{$timestamp}_{$migrationFileName}");
    }
}
