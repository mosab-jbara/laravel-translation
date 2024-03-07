<?php

namespace Mosab\Translation\Rules;


use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UniqueTranslationValue implements Rule
{
    protected $modelClass;
    protected $key;
    protected $duplicateLangKey;

    public function __construct($model, $key)
    {
        // Assuming $model is given as a simple name (e.g., "Attribute"), resolve to full class name
        $this->modelClass = $this->resolveModelClass($model);
        $this->key = $key;
        $this->duplicateLangKey = null;
    }

    protected function resolveModelClass($model)
    {
        // Attempt to resolve model class from its name dynamically. Adjust namespace as necessary.
        $modelClass = "App\\Models\\" . Str::studly($model);
        if (!class_exists($modelClass)) {
            Log::error("UniqueTranslationValue: Model class {$modelClass} does not exist.");
            return null;
        }
        return $modelClass;
    }

    public function passes($attribute, $values)
    {
        if (!$this->modelClass) {
            return false; // Model class not resolved
        }

        foreach ($values as $langKey => $langTranslation) {
            $exists = call_user_func([$this->modelClass, 'query'])
                ->whereHas('translations', function ($query) use ($langKey, $langTranslation) {
                    $query->where('attribute', $this->key)
                          ->where('language', '=', $langKey)
                          ->where('value', '=', $langTranslation);
                })->exists();

            if ($exists) {
                $this->duplicateLangKey = $langKey;
                return false;
            }
        }

        return true;
    }

    public function message()
    {
        return "The :attribute contains duplicate values in the '{$this->duplicateLangKey}' language.";
    }
}
