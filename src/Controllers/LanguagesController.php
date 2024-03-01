<?php

namespace Mosab\Translation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Mosab\Translation\Models\TranslationsLanguage;
use Illuminate\Support\Facades\File;
use Mosab\Translation\Models\Translation;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class LanguagesController extends Controller
{
    public function index()
    {
        $cacheKey = 'translationsLanguages.all';
        $ttl = 86400; // Cache time-to-live in seconds, 1 day
    
        $translationsLanguages = Cache::remember($cacheKey, $ttl, function () {
            return TranslationsLanguage::all();
        });
    
        return response()->json(['TranslationsLanguages' => $translationsLanguages], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|array', translation_rule(),
        ]);

        $languageData = $this->getLanguageData();
        $language = collect($languageData)->firstWhere('name', $request->title);

        if (!$language) {
            abort(404, 'Language not found.');
        }

        $translationsLanguage = TranslationsLanguage::create([
            'code' => $language['code'],
            'title' => $request->title,
        ]);

        return response()->json(['TranslationsLanguage' => $translationsLanguage], Response::HTTP_CREATED);
    }

    public function destroy(TranslationsLanguage $language)
    {
        Translation::where('language', $language->code)->delete();
        $language->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function show()
    {
        $languageData = $this->getLanguageData();
        $keyNames = array_column($languageData, 'name');

        return response()->json(['availableLanguages' => $keyNames], Response::HTTP_OK);
    }

    private function getLanguageData()
    {
        return Cache::remember('languageData', 60 * 60*60, function () {
            $jsonFile = File::get(public_path('languageUniversalCode.json'));
            return json_decode($jsonFile, true);
        });
    }

}
