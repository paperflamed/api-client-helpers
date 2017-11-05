<?php

namespace Wizz\ApiClientHelpers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Cache;

class ACHController extends Controller
{
    /*

    Setting up our error message for the client.

    */
    public function __construct()
    {
        $one = "Sorry, looks like something went wrong. ";
        $two = (env('support_email')) ? "Please contact us at <a href='mailto:".env('support_email')."'>".env('support_email')."</a>" : "Please contact us via email";
        $three = ' for further assistance.';

        $this->error_message = $one.$two.$three;
        $this->security_code = config('api_configs.security_code');
        $this->redirect_code = config('api_configs.not_found_redirect_code', 301);
        $this->redirect_mode = config('api_configs.not_found_redirect_mode');
        $this->version = "1.2";

    }

    /*

<<<<<<< HEAD
=======
    Little helper for our check function.

    */
    protected function is_ok($func)
    {
        return ($this->$func()) ? 'OK' : 'OFF';
    }

    /*

    Validating that all our configs necessary for frontend repo are in place.

    */
    protected function validate_frontend_config()
    {
        if(! env('frontend_repo_url')) return false;

        if(substr(env('frontend_repo_url'), -1) != '/') return false;

        return true;
    }

    /*

    The actual function for handling frontend repo requests.

    */
    public function frontend_repo($slug, Request $req)
    {
        $input = request()->all();
        $input['domain'] = request()->root();
        $conf = $this->from_config();

        if ($conf['tracking_hits']){
            //store hit and write hit_id in cookie
            $hitsQuery = [
                'rt' => array_get($input, 'rt', null),
                'client_id' => $conf['client_id'],
            ];
            $query = env('secret_url') . '/hits/?' . http_build_query($hitsQuery);
            $res = file_get_contents($query);
            $res = json_decode($res)->data;
            \Cookie::queue('hit_id', $res->id, time()+60*60*24*30, '/');
        }

        $input = array_merge($input, $conf);
        if(array_key_exists('page', $input)) unset($input['page']);
        
        session(['addition' => $input]);
        if(!$this->validate_frontend_config()) return $this->error_message;
        
        $ck = CK($slug);
        if (should_we_cache($ck)) {
            return insertToken(Cache::get($ck));
        }

        try {
            $front = $conf['frontend_repo_url'];
            
            if(config('api_configs.multidomain_mode_dev') || config('api_configs.multidomain_mode')) {
                $slug = !strlen($slug) ? $slug : '/';
            }

            $url = ($slug == '/') ? $front : $front.$slug;
            $query = [];
            $domain = $req->url();

            if (array_search(parse_url($domain)['host'], $conf['multilingualSites']) !== false)
            {
                $url_segments = splitUrlIntoSegments($req->path());
                $main_language = $conf['main_language'] ? $conf['main_language'] : 'en';
                $language_from_url = array_get($url_segments, 0, $main_language);
                $language_from_url = gettype(array_search($language_from_url, $conf['languages'])) == 'integer' ? $language_from_url : $main_language;

                //if user tries to change language via switcher rewrite language_from_request cookie
                if ($req->input('change_lang'))
                {
                    setcookie('language_from_request', $req->input('change_lang'), time() + 60 * 30, '/');
                    $_COOKIE['language_from_request'] = $req->input('change_lang');
                    if ($language_from_url !== $req->input('change_lang'))
                    {
                        return redirect($req->input('change_lang') == $main_language ? '/' : '/' . $req->input('change_lang') . '/ ');
                    }
                }
                if ($req->get('l') == $main_language)
                {
                    setcookie('language_from_request', $main_language, time() + 60 * 30, '/');
                    $query = [
                        'lang' => $main_language,
                        'main_language' => $conf['main_language']
                    ];
                }
                if ($slug == '/' && $req->get('l') !== $main_language)
                {
                    if (!array_key_exists("language_from_request", $_COOKIE))
                    {
                        //setting language_from_request cookie from accept-language
                        $language_from_request = substr(locale_accept_from_http($req->header('accept-language')), 0, 2);
                        $language_from_request = gettype(array_search($language_from_request, $conf['languages'])) == 'boolean' ? $main_language : $language_from_request;
                        setcookie('language_from_request', $language_from_request, time() + 60 * 30, '/');
                        if ($language_from_url !== $language_from_request)
                        {
                            return redirect($language_from_request == $main_language ? '/' : '/' . $language_from_request . '/ ');
                        }
                    }
                    else {
                        if ($language_from_url !== $_COOKIE['language_from_request'])
                        {
                            return redirect($_COOKIE['language_from_request'] == $main_language ? '/' : '/' . $_COOKIE['language_from_request'] . '/ ');
                        }
                    }
                }
                $query = [
                    'lang' => $language_from_url,
                    'main_language' => $conf['main_language']
                ];
            }
            $url = $url . '?' . http_build_query(array_merge($req->all(), $query));
            $page = file_get_contents($url, false, stream_context_create(arrContextOptions()));

            $http_code = array_get($http_response_header, 0, 'HTTP/1.1 200 OK');

            if(strpos($http_code, '238') > -1)
            {
                // code 238 is used for our internal communication between frontend repo and client site,
                // so that we do not ignore errors (410 is an error);
                if($this->redirect_mode === "view")
                {
                    return response(view('api-client-helpers::not_found'), $this->redirect_code);
                }
                else { // changed this to else, so that we use http redirect by default even if nothing is specified
                    return redirect()->to('/', $this->redirect_code);
                }
            }
            if ($this->should_we_cache()) Cache::put($ck, $page, $conf['cache_frontend_for']);
            return insertToken($page);
        }
        catch (Exception $e)
        {
            // \Log::info($e);
            return $this->error_message;
        }
    }

    /*

    Function to clear all cache (e.g. when new frontend repo code is pushed).

    */
    public function clear_cache()
    {
        if(request()->input('code') !== $this->security_code) return ['result' => 'no access'];
        try {
            \Artisan::call('cache:clear');
            return ['result' => 'success'];
        } catch (Exception $e) {
            // \Log::info($e);
            return ['result' => 'error'];
        }
    }
    /*

    Prozy function for api by @wizz.

    */
    public function proxy($slug = '/')
    {
        $method = array_get($_SERVER, 'REQUEST_METHOD');
        $res = apiRequestProxy();
        setCookiesFromCurlResponse($res);
        $data = explode("\r\n\r\n", $res);
        $data2 = http_parse_headers($res);

        if(request_string_contains_redirect($data[0])){
            $headers = array_get(http_parse_headers($data[0]), 0);
            return redirect()->to(array_get($headers, 'location'));
        }

        // TODO: try to use only 1 data
        $headers = (count($data2) == 3) ? $data2[1] : $data2[0];
        $res = (count($data) == 3) ? $data[2] : $data[1];

        $content_type = array_get($headers, 'content-type');
        switch ($content_type) {
            case 'application/json':
                return response()->json(json_decode($res));
                break;
            case strrpos('q'.$content_type, 'text/html'):
                return $res;
                break;
            case 'text/plain':
                return $res;
                break;
            case "application/xml":
                return (new \SimpleXMLElement($res))->asXML();
                break;
            default:
                $shit = array_get($headers, 'cache-disposition', array_get($headers, 'content-disposition'));
                $path = getPathFromHeaderOrRoute($shit, $slug);
                file_put_contents($path, $res);
                //TODO return file without download
                return response()->download($path);
                break;
        }
        // TODO change to handle status

        if (array_get($headers, 'status') == 500 && !json_decode($res)) {
            return response()->json([
                'status' => 400,
                'errors' => [$this->error_message],
                'alerts' => []
            ]);
        }
    }

    /*

    Our redirector to api functionality.

    */
    public function redirect($slug, Request $request)
    {

        if(!validate_redirect_config()) return $this->error_message;

        return redirect()->to(env('secret_url').'/'.$slug.'?'.http_build_query($request->all()));
    }

    /*

    Little helper to see the state of affairs in browser.

    */
    public function check()
    {
        if(request()->input('code') !== $this->security_code) return;

        return [
            'frontend_repo' => is_ok('validate_frontend_config'),
            'redirect' => is_ok('validate_redirect_config'),
            'caching' => is_ok('should_we_cache'),
            'version' => $this->version
        ];
    }

    public function from_config() 
    {
        $uri_host = explode('/', request()->getRequestUri());
        $host = $uri_host[1];

        if (config('api_configs.multidomain_mode_dev') && $uri_host[1]) {
            $dom = config('api_configs.change_project.'.$host);
        } else {
            $has_multidomain = config('api_configs.multidomain_mode') && app()->environment('local'); 
            $host = !$has_multidomain ? request()->getHttpHost() : $host;
            $dom = preg_replace('|[^\d\w ]+|i', '-', $host);
        }
        $keys = [
            'client_id',
            'frontend_repo_url',
            'main_language',
            'multilingualSites',
            'languages',
            'tracking_hits',
            'cache_frontend_for'
        ];
        $has_domains = array_key_exists($dom, config('api_configs.domains')); 
        $str = $has_domains ? 'api_configs.domains.'.$dom.'.' : 'api_configs.';
        
        foreach ($keys as $key) {
            $conf[$key] = config($str.$key);
        }
        return $conf;
    }
}
