<?php

namespace Mosab\Translation\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mosab\Translation\Middleware\RequestLanguage;
use Mosab\Translation\Database\Builder as TranslationsBuilder;
use Mosab\Translation\Models\Translation;

/**
 * An abstract class that extends Laravel's Eloquent Model to support translation capabilities.
 * It allows for easy management of translatable attributes across different languages.
 */
abstract class TranslatableModel extends Model
{
    /**
     * An array listing the attributes of the model that can be translated.
     */
    protected $translatable = [];

    /**
     * Stores the current translations of the translatable attributes.
     */
    protected $translation_attributes = [];

    /**
     * Stores the original translations of the translatable attributes to detect changes.
     */
    protected $translation_original = [];

    /**
     * Stores the changes made to translations, to track what needs to be updated.
     */
    protected $translation_changes = [];

    /**
     * Holds translations that need to be inserted into the database.
     */
    private $translations_to_insert = [];

    /**
     * Holds translations that need to be updated in the database.
     */
    private $translations_to_update = [];

    /**
     * Converts the model instance to an array, including translatable attributes.
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->attributesToArray(), $this->translatedAttributesToArray(), $this->relationsToArray());
    }

    /**
     * Getter for the translatable attributes array.
     *
     * @return array
     */
    public function getTranslatable()
    {
        return $this->translatable;
    }

    /**
     * Converts translated attributes to an array, selecting translations based on the current language.
     *
     * @return array Translated attributes in the current language.
     */
    public function translatedAttributesToArray()
    {
        $language = RequestLanguage::$language;
        $translated_attributes = [];
        foreach ($this->translation_attributes as $key => $value) {
            $translated_attributes[$key] = $value[$language];
        }
        return $translated_attributes;
    }

    /**
     * Overrides the magic __get method to retrieve translations of attributes if they exist.
     *
     * @param string $key The attribute name.
     * @return mixed
     */
    public function __get($key)
    {
        $language = RequestLanguage::$language;
        if (isset($this->translation_attributes[$key][$language])) {
            return $this->translation_attributes[$key][$language];
        }
        return parent::__get($key);
    }

    /**
     * Overrides the magic __set method to set translations of attributes if they are translatable.
     *
     * @param string $key The attribute name.
     * @param mixed $value The value to set.
     */
    public function __set($key, $value)
    {
        if (in_array($key, $this->translatable)) {
            $languages = RequestLanguage::$all_languages;
            if (!isset($this->translation_attributes[$key])) {
                $this->translation_attributes[$key] = [];
            }
            if (is_array($value)) {
                // If the value is an array, assume it's an associative array of language => translation
                foreach ($languages as $language) {
                    $this->translation_attributes[$key][$language] = "";
                }
                foreach ($value as $k => $v) {
                    if (in_array($k, $languages)) {
                        $this->translation_attributes[$key][$k] = $v;
                    }
                }
            } elseif (is_string($value)) {
                // If the value is a string, apply it to all languages
                foreach ($languages as $language) {
                    $this->translation_attributes[$key][$language] = $value;
                }
            } else {
                // If the value is neither an array nor a string, nullify the translation
                foreach ($languages as $language) {
                    $this->translation_attributes[$key][$language] = null;
                }
            }
            return;
        }
        parent::__set($key, $value);
    }

    /**
     * Defines the relationship to the Translation model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function translations()
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Retrieves translation values from a collection and organizes them by attribute and language.
     *
     * @param array $dictionary The collection of translations.
     * @param string $key The key within the collection that holds the translations.
     * @return \Illuminate\Support\Collection
     */
    protected function getTranslationValue(array $dictionary, $key)
    {
        $result = [];
        $values = $dictionary[$key];
        $translatable = $this->translatable ?? [];
        $languages = RequestLanguage::$all_languages;

        // Initialize the result array with empty strings for each translatable attribute and language
        foreach ($translatable as $col) {
            $result[$col] = [];
            foreach ($languages as $lang) {
                $result[$col][$lang] = "";
            }
        }

        // Fill the result array with actual translations
        foreach ($values as $value) {
            if (isset($result[$value->attribute][$value->language])) {
                $result[$value->attribute][$value->language] = $value->value;
            }
        }
        return collect($result);
    }

    /**
     * Sets the translations value for a relation.
     *
     * @param string $relation The relation name.
     * @param mixed $value The value to set for the relation.
     * @return $this
     */
    public function setRelation($relation, $value)
    {
        if ($relation == "translations") {
            $value = $this->getTranslationValue(['translations' => $value], 'translations');
            $this->setTranslationsValue($value);
        }
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Automatically loads translations relationship when the model is queried.
     */
    protected static function booted()
    {
        static::addGlobalScope('translations', function (Builder $builder) {
            $builder->with('translations');
        });
    }

    /**
     * Sets the translation attributes and original values from a given collection.
     *
     * @param \Illuminate\Support\Collection $relationValue The collection of translation values.
     */
    public function setTranslationsValue(Collection $relationValue)
    {
        foreach ($relationValue as $key => $value) {
            $this->translation_attributes[$key] = $value;
            $this->translation_original[$key] = $value;
        }
    }

    /**
     * Hooks into the model's boot process to set up model events for handling translations.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete translations when the model is deleted
        self::deleted(function ($model) {
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($model))) {
                self::forceDeleted(function ($model) {
                    $model->translations()->delete();
                });
            } else {
                $model->translations()->delete();
            }
        });

        // Handle saving translations when the model is being saved
        self::saving(function ($model) {
            $updated = false;
            foreach ($model->translation_attributes as $attribute => $translations) {
                if (!isset($model->translation_original[$attribute])) {
                    // Insert new translations
                    foreach ($translations as $lang => $value) {
                        $model->translations_to_insert[] = [
                            'translatable_type' => $model::class,
                            'language'  => $lang,
                            'attribute' => $attribute,
                            'value'     => $value ?? "",
                            'created_at' => \Carbon\Carbon::now(),
                            'updated_at' => \Carbon\Carbon::now(),
                        ];
                    }
                    $updated = true;
                } elseif ($model->translation_original[$attribute] != $translations) {
                    // Update existing translations
                    foreach ($translations as $lang => $value) {
                        $model->translations_to_update[] = [
                            'translatable_id' => $model->id,
                            'translatable_type' => $model::class,
                            'language'  => $lang,
                            'attribute' => $attribute,
                            'value'     => $value ?? "",
                        ];
                    }
                    $model->translation_changes[$attribute] = $translations;
                    $updated = true;
                }
                $model->translation_original[$attribute] = $translations;
            }
            if ($updated && $model->usesTimestamps()) {
                $model->updateTimestamps();
            }
        });

        // Insert or update translations after the model is saved
        self::saved(function ($model) {
            // Set the translatable_id for new translations
            for ($i = 0; $i < count($model->translations_to_insert); $i++) {
                $model->translations_to_insert[$i]['translatable_id'] = $model->id;
            }
            if (count($model->translations_to_insert)) {
                Translation::insert($model->translations_to_insert);
            }
            if (count($model->translations_to_update)) {
                Translation::upsert(
                    $model->translations_to_update,
                    ['translatable_id', 'translatable_type', 'language', 'attribute'],
                    ['value']
                );
            }
            if (count($model->translations_to_insert) + count($model->translations_to_update)) {
                // Refresh the translations relation if there were changes
                $model->unsetRelation('translations');
                $model->translations;
            }
            $model->translations_to_insert = [];
            $model->translations_to_update = [];
        });
    }

    /**
     * Overrides the default Eloquent builder to use the custom TranslationsBuilder.
     *
     * @param \Illuminate\Database\Query\Builder $query The query builder instance.
     * @return TranslationsBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new TranslationsBuilder($query);
    }

    /**
     * Separates translatable attributes from other attributes when filling the model.
     *
     * @param array $attributes The attributes to fill the model with.
     * @return $this
     */
    public function fill(array $attributes)
    {
        $translatable = $this->translatable;
        $translatable_attributes = [];
        $other_attributes = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $translatable)) {
                $translatable_attributes[$key] = $value;
            } else {
                $other_attributes[$key] = $value;
            }
        }

        foreach ($translatable_attributes as $key => $value) {
            $this->$key = $value;
        }

        return parent::fill($other_attributes);
    }

    /**
     * Discards changes made to the translatable attributes, reverting them to their original values.
     *
     * @return $this
     */
    public function discardChanges()
    {
        [$this->translation_attributes, $this->translation_changes] = [$this->translation_original, []];

        return parent::discardChanges();
    }

    /**
     * Identifies which translatable attributes have been modified.
     *
     * @return array The dirty translatable attributes.
     */
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

    /**
     * Scope a query to include translations for a given attribute and value in a specific language.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute The attribute to search within translations.
     * @param mixed $value The value to match within translations.
     * @param string|null $language The language code to filter translations by.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereTranslation($query, $attribute, $value, $language = null)
    {
        return $query->whereHas('translations', function ($query) use ($attribute, $value, $language) {
            $query->where('attribute', $attribute)
                ->where('value', 'LIKE', '%' . $value . '%');
            if (!is_null($language)) {
                $query->where('language', $language);
            }
        });
    }

    /**
     * Scope a query to include translations for a given attribute and value in a specific language using OR logic.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $attribute The attribute to search within translations.
     * @param mixed $value The value to match within translations.
     * @param string|null $language The language code to filter translations by.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrWhereTranslation($query, $attribute, $value, $language = null)
    {
        return $query->orWhereHas('translations', function ($query) use ($attribute, $value, $language) {
            $query->where(function ($q) use ($attribute, $value, $language) {
                $q->where('attribute', $attribute)
                    ->where('value', 'LIKE', '%' . $value . '%');

                if (!is_null($language)) {
                    $q->where('language', $language);
                }
            });
        });
    }
}
