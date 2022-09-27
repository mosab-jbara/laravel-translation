<?php

use Mosab\Translation\Middleware\RequestLanguage;

if (! function_exists('translation_rule')) {
    function translation_rule(array $languages=null)
    {
        return 'required_array_keys:'.implode(',',$languages??RequestLanguage::$all_languages);
    }
}
