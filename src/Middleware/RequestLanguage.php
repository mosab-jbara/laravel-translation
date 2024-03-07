<?php

namespace Mosab\Translation\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequestLanguage
{
    public static $all_languages = [];
    public static $language = 'en';

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->headers->get('accept-language') ?? 'en';
        if (!in_array($lang, RequestLanguage::$all_languages))
            $lang = 'en';
        RequestLanguage::$language = $lang;

        //@desc: To make the App Language Same the Translate language to be able to use lang files
        app()->setLocale($lang);

        return $next($request);
    }
}
