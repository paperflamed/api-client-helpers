<?php

namespace Wizz\ApiClientHelpers\Middleware;

use Closure;
use Log;

class BlockUrlsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {     
        $from_url = $request->server('HTTP_REFERER');
        $utm_host = $request->input('utm_host', '');
        $utm_referrer = $request->input('utm_referrer', '');

        if(!$utm_host && !$from_url && !$utm_referrer) return $next($request);

        $referrer_has_gov = strpos($from_url, '.gov') !== false;
        $utm_host_has_gov = strpos($utm_host, '.gov') !== false;

        if($referrer_has_gov || $utm_host_has_gov) return redirect("https://google.com");
    
        $list_of_urls_to_block = conf('list_of_urls_to_block') ? conf('list_of_urls_to_block') : [];

        foreach ($list_of_urls_to_block as $url => $destination) {
            
            $host = $utm_host && strpos($utm_host, $url) !== false ? $utm_host : false;
            $host = !$host && $utm_referrer && strpos($utm_referrer, $url) !== false ? $utm_referrer : $host;
            $host = !$host && $from_url && strpos($from_url, $url) !== false ? $from_url : $host;

            if ($host) {
                $s = 'Blocking visitor for utm from ' . $host . '.';
                $s = $request->has('rt') ? $s . ' Link code was ' . $request->input('rt') : $s;
                // Log::info($s);
                return redirect($destination);
            }
        }
        return $next($request);
    }
}
