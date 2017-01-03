<?php

namespace Wizz\ApiClientHelpers\Middleware;

use Closure;

class BrowserVersionMiddleware
{
	/**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/configs/api_configs.php' => config_path('api_configs.php'),
        ]);
        //
    }

	/**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
    	$is_old_browser = false;
        $browser = $request->server('HTTP_USER_AGENT');
        foreach (config('api_configs.old_browsers') as $key => $value) {
            $pos = strpos($browser, $key);
            if ($pos)
            {
                $version = substr($browser, $pos + strlen($key) + 1, 3);
                if (intval($version) <= $value)
                    $is_old_browser = true;
                break;
            }
        }
        if ($is_old_browser)
        	return response(view('api-client-helpers::browser_warning'), 410);
        return $next($request);
    }
}