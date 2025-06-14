<?php

namespace App\Providers;

//use App\Observers\MediaObserver;
use App\Observers\MediaObserver;
use App\Observers\UserObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        UrlGenerator::macro('alternateHasCorrectSignature', function (Request $request, $absolute = true, array $ignoreQuery = []) {
            $ignoreQuery[] = 'signature';

            $absoluteUrl = url($request->path());
            $url = $absolute ? $absoluteUrl : '/'.$request->path();

            $queryString = collect(explode('&', (string) $request->server->get('QUERY_STRING')))
                ->reject(fn ($parameter) => in_array(Str::before($parameter, '='), $ignoreQuery) || !Str::contains($parameter, '='))
                ->join('&');
            $original = rtrim($url.'?'.$queryString, '?');
            $signature = hash_hmac('sha256', $original, call_user_func($this->keyResolver)[0]);
            return hash_equals($signature, (string) $request->query('signature', ''));
        });

        UrlGenerator::macro('alternateHasValidSignature', function (Request $request, $absolute = true, array $ignoreQuery = []) {
            return URL::alternateHasCorrectSignature($request, $absolute, $ignoreQuery)
                && URL::signatureHasNotExpired($request);
        });

        Request::macro('hasValidSignature', function ($absolute = true, array $ignoreQuery = []) {
            return URL::alternateHasValidSignature($this, $absolute, $ignoreQuery);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::setUpdateRoute(function ($handle) {
            $path = config('app.path').'/livewire/update';
            return Route::post($path, $handle)->middleware('web');
        });
        Livewire::setScriptRoute(function ($handle) {
            $path = config('app.path').'/livewire/livewire.js';
            return Route::get($path, $handle)->middleware('web');
        });
        URL::forceRootUrl(config('app.url'));
        URL::forceScheme(config('app.scheme','http'));

        App::setLocale(env('APP_LOCALE', 'ru'));

        User::observe(UserObserver::class);
    }
}
