<?php

namespace Mosab\Translation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Mosab\Translation\Database\TranslatableModel;

class TranslationsLanguage extends TranslatableModel
{
    use HasFactory;

    protected $fillable= [
        'code'
    ];

    protected $translatable = [
        'title',
    ];
}
