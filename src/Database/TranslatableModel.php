<?php

namespace Mosab\Translation\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mosab\Translation\Middleware\RequestLanguage;
use Mosab\Translation\Database\Builder as TranslationsBuilder;
use Mosab\Translation\Models\Translation;

abstract class TranslatableModel extends Model
{
    protected $translatable = [];

    protected $translation_attributes = [];
    protected $translation_original = [];
    protected $translation_changes = [];

    private $translations_to_insert = [];
    private $translations_to_update = [];

    public function toArray()
    {
        return array_merge($this->attributesToArray(), $this->translatedAttributesToArray(), $this->relationsToArray());
    }

    public function getTranslatable()
    {
        return $this->translatable;
    }

    public function translatedAttributesToArray()
    {
        $language = RequestLanguage::$language;
        $translated_attributes = [];
        // TODO support array of languages
        foreach ($this->translation_attributes as $key => $value)
            $translated_attributes[$key] = $value[$language];
        return $translated_attributes;
    }

    public function __get($key)
    {
        $language = RequestLanguage::$language;
        if (isset($this->translation_attributes[$key][$language]))
            return $this->translation_attributes[$key][$language];
        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        if (in_array($key, $this->translatable)) {
            $languages = RequestLanguage::$all_languages;
            if (!isset($this->translation_attributes[$key]))
                $this->translation_attributes[$key] = [];
            if (is_array($value)) {
                foreach ($languages as $language)
                    $this->translation_attributes[$key][$language] = "";
                foreach ($value as $k => $v)
                    if (in_array($k, $languages))
                        $this->translation_attributes[$key][$k] = $v;
                return;
            } elseif (is_string($value)) {
                foreach ($languages as $language)
                    $this->translation_attributes[$key][$language] = $value;
                return;
            } else {
                foreach ($languages as $language)
                    $this->translation_attributes[$key][$language] = null;
                return;
            }
        }
        parent::__set($key, $value);
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    protected function getTranslationValue(array $dictionary, $key)
    {
        $result = [];
        $values = $dictionary[$key];

        $translatable = $this->translatable ?? [];
        $languages = RequestLanguage::$all_languages;
        foreach ($translatable as $col) {
            $result[$col] = [];
            foreach ($languages as $lang)
                $result[$col][$lang] = "";
        }

        foreach ($values as $value) {
            if (isset($result[$value->attribute][$value->language]))
                $result[$value->attribute][$value->language] = $value->value;
        }
        return collect($result);
    }

    public function setRelation($relation, $value)
    {
        if ($relation == "translations") {
            $value = $this->getTranslationValue(['translations' => $value], 'translations');
            $this->setTranslationsValue($value);
        }
        $this->relations[$relation] = $value;

        return $this;
    }

    protected static function booted()
    {
        static::addGlobalScope('translations', function (Builder $builder) {
            $builder->with('translations');
        });
    }

    public function setTranslationsValue(Collection $relationValue)
    {
        foreach ($relationValue as $key => $value) {
            $this->translation_attributes[$key] = $value;
            $this->translation_original[$key] = $value;
        }
    }

    protected static function boot()
    {
        parent::boot();

        self::deleted(function ($model) {
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model)))
                self::forceDeleted(function ($model) {
                    $model->translations()->delete();
                });
            else
                $model->translations()->delete();
        });


        self::saving(function ($model) {
            $updated = false;
            foreach ($model->translation_attributes as $attribute => $translations) {
                if (!isset($model->translation_original[$attribute])) {
                    foreach ($translations as $lang => $value)
                        $model->translations_to_insert[] = [
                            'translatable_type' => $model::class,
                            'language'  => $lang,
                            'attribute' => $attribute,
                            'value'     => $value ?? "",
                            'created_at' => \Carbon\Carbon::now(),
                            'updated_at' => \Carbon\Carbon::now(),
                        ];
                    $updated = true;
                } elseif ($model->translation_original[$attribute] != $translations) {
                    foreach ($translations as $lang => $value)
                        $model->translations_to_update[] = [
                            'translatable_id' => $model->id,
                            'translatable_type' => $model::class,
                            'language'  => $lang,
                            'attribute' => $attribute,
                            'value'     => $value ?? "",
                        ];
                    $model->translation_changes[$attribute] = $translations;
                    $updated = true;
                }
                $model->translation_original[$attribute] = $translations;
            }
            if ($updated && $model->usesTimestamps())
                $model->updateTimestamps();
        });
        self::saved(function ($model) {
            for ($i = 0; $i < count($model->translations_to_insert); $i++)
                $model->translations_to_insert[$i]['translatable_id'] = $model->id;
            if (count($model->translations_to_insert))
                Translation::insert($model->translations_to_insert);
            if (count($model->translations_to_update))
                Translation::upsert(
                    $model->translations_to_update,
                    ['translatable_id', 'translatable_type', 'language', 'attribute'],
                    ['value']
                );
            if (count($model->translations_to_insert) + count($model->translations_to_update)) {
                $model->unsetRelation('translations');
                $model->translations;
            }
            $model->translations_to_insert = [];
            $model->translations_to_update = [];
        });
    }

    public function newEloquentBuilder($query)
    {
        return new TranslationsBuilder($query);
    }

    public function fill(array $attributes)
    {
        $translatable = $this->translatable;
        $translatable_attributes = [];
        $other_attributes = [];
        foreach ($attributes as $key => $value)
            if (in_array($key, $translatable))
                $translatable_attributes[$key] = $value;
            else
                $other_attributes[$key] = $value;

        foreach ($translatable_attributes as $key => $value)
            $this->$key = $value;

        return parent::fill($other_attributes);
    }

    public function discardChanges()
    {
        [$this->translation_attributes, $this->translation_changes] = [$this->translation_original, []];

        return parent::discardChanges();
    }

    public function getDirtyTranslatable()
    {
        $dirty = [];
        foreach ($this->translation_attributes as $key => $value) {
            if ($this->translation_original[$key] ?? null != $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function getDirty($with_translatable = false)
    {
        $dirty = parent::getDirty();

        if ($with_translatable)
            $dirty += self::getDirtyTranslatable();

        return $dirty;
    }

    // Define the scope for searching translations
    public function scopeWhereTranslation($query, $attribute, $value)
    {
        return $query->whereHas('translations', function ($translationQuery) use ($attribute, $value) {
            $translationQuery->where('attribute', $attribute)
                ->where('value', '=', $value);
        });
    }
}
