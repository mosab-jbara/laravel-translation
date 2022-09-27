<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mosab\Translation\Middleware\RequestLanguage;
use Mosab\Translation\Models\TranslationsLanguage;

class TranslationsLanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('translations_languages')->truncate();
        DB::table('translations')->where('translatable_type', TranslationsLanguage::class)->delete();
        Schema::enableForeignKeyConstraints();

        RequestLanguage::$all_languages = ['en', 'ar'];

        TranslationsLanguage::create([
            'code'  => 'en',
            'title' => 'English',
        ]);

        TranslationsLanguage::create([
            'code'  => 'ar',
            'title' => 'Arabic',
        ]);
    }
}
