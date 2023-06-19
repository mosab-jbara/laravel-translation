<?php

namespace Mosab\Translation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Mosab\Translation\Models\TranslationsLanguage;
use Illuminate\Support\Facades\File;
use Mosab\Translation\Models\Translation;
use Symfony\Component\HttpFoundation\Response;

class LanguagesController extends Controller
{
    public function index()
    {
        $TranslationsLanguages = TranslationsLanguage::all();
        return response()->json(['TranslationsLanguages' => $TranslationsLanguages], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|array', translation_rule(),
        ]);

        // Load the JSON file contents
        $jsonPath = public_path('languageUniversalCode.json');
        $jsonContents = File::get($jsonPath);

        // Decode the JSON into an array
        $languageData = json_decode($jsonContents, true);

        // Find the language with the matching title
        $requestedTitle = $request->title['en'];
        $language = collect($languageData)->firstWhere('name', $requestedTitle);

        if (!$language) {
            return response()->json([
                'status' => 'error',
                'message' => 'Language not found.',
            ], 404);
        }

        $TranslationsLanguage = new TranslationsLanguage;

        $TranslationsLanguage->code = $language['code'];
        $TranslationsLanguage->title = $requestedTitle;

        $TranslationsLanguage->save();


        return response()->json(['TranslationsLanguage' => $TranslationsLanguage], Response::HTTP_CREATED);
    }

    public function destroy(TranslationsLanguage $language)
    {
        $translations = Translation::where('language', $language->code)->get();
        foreach ($translations as $key => $translation) {
            $translation->delete();
        }
        $language->delete();

        return Response::HTTP_NO_CONTENT;
    }

    public function show()
    {
        $jsonFile = File::get(public_path('languageUniversalCode.json'));
        $data = json_decode($jsonFile, true);

        $keyNames = array_column($data, 'name');

        return response()->json(['avaliableLanguages' => $keyNames], Response::HTTP_OK);
    }
}
