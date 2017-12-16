<?php

namespace Wizz\ApiClientHelpers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Cache;
use Wizz\ApiClientHelpers\Helpers\CurlRequest;
use Wizz\ApiClientHelpers\Helpers\CookieHelper;
use Wizz\ApiClientHelpers\Helpers\Validator;

class ACHController extends Controller
{
    /*

    Setting up our error message for the client.

    */
    public function __construct()
    {
        $one = "Sorry, looks like something went wrong. ";

        $two = (conf('support_email')) ? "Please contact us at <a href='mailto:".conf('support_email')."'>".conf('support_email')."</a>" : "Please contact us via email";

        $three = ' for further assistance.';

        $this->error_message = $one.$two.$three;
        $this->security_code = config('api_configs.security_code');
        $this->redirect_code = config('api_configs.not_found_redirect_code', 301);
        $this->redirect_mode = config('api_configs.not_found_redirect_mode');

        $this->version = "1.2";

    }

    /*

    The actual function for handling frontend repo requests.

    */
    public function frontend_repo(Request $req)
    {
        // cut tracking hits from here
        // $input = array_merge($input, $conf);
        // TODO add ability to manually change current domain
        $slug = $req->path();
        if(!Validator::validate_frontend_config()) return $this->error_message;
        $ck = CK($slug);
        if (should_we_cache($ck)) return CookieHelper::insertToken(Cache::get($ck));

        $multilingual = $this->checkMultilingual($req);
        if ($multilingual['redirect'])
        {
            return redirect($multilingual['redirect_path']);
        }
        $query = $multilingual['query'];

        $this->trackingHits();

        try {
            $front = conf('frontend_repo_url');
            // if(config('api_configs.multidomain_mode_dev') || config('api_configs.multidomain_mode')) {
            //     $slug = !strlen($slug) ? $slug : '/';
            // }

            // $url = ($slug == '/') ? $front : $front.$slug;
            $query = [];
            // $domain = $req->url();
            // cut shit from here
           
            $url = $front.$slug. '?' . http_build_query(array_merge($req->all(), $query));
            $page = file_get_contents($url, false, stream_context_create(CookieHelper::arrContextOptions()));

            $http_code = array_get($http_response_header, 0, 'HTTP/1.1 200 OK');
            // what is this?
            if(strpos($http_code, '238') > -1)
            {
                // code 238 is used for our internal communication between frontend repo and client site,
                // so that we do not ignore errors (410 is an error);
                if($this->redirect_mode === "view")
                {
                    return response(view('api-client-helpers::not_found'), $this->redirect_code);
                }
                else //if($this->redirect_mode === "http")
                { // changed this to else, so that we use http redirect by default even if nothing is specified
                    return redirect()->to('/', $this->redirect_code);
                }
            }

            if (should_we_cache()) Cache::put($ck, $page, conf('cache_frontend_for'));
            return CookieHelper::insertToken($page);

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
        if(request()->input('code') !== $this->security_code) return ['result' => 'no access'];;
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
    public function proxy(Request $request)
    {
        $r = new CurlRequest($request);
        $r->execute();
        CookieHelper::setCookiesFromCurlResponse($r->headers['cookies']);

        if ($r->redirect_status) return redirect()->to(array_get($r->headers, 'location'));
    
        if(strpos('q'.$r->content_type, 'text/html') && strpos('q'.$r->body, 'Whoops,'))
        
        return response()->json([
            'status' => 400,
            'errors' => [$this->error_message],
            'alerts' => []
        ]);

        if (strpos('q'.$r->content_type, 'text/html') || strpos('q'.$r->content_type, 'text/plain')) return $r->body;
        if ($r->content_type == 'application/json') return response()->json(json_decode($r->body));
        if ($r->content_type == 'application/xml') return (new \SimpleXMLElement($r->body))->asXML();
        return response($r->body)
            ->header('Content-Type', $r->content_type)
            ->header('Content-Disposition', array_get($r->headers, 'content-disposition'));
    }

    /*

    Our redirector to api functionality.

    */
    public function redirect($slug, Request $request)
    {
// TODO needs fix to work in multi client mode
        if(!Validator::validate_redirect_config()) return $this->error_message;

        return redirect()->to(conf('secret_url').'/'.$slug.'?'.http_build_query($request->all()));
    }

    /*

    Little helper to see the state of affairs in browser.

    */
    public function check()
    {
        if(request()->input('code') !== $this->security_code) return;

        return [
            'frontend_repo' => Validator::is_ok('validate_frontend_config'),
            'redirect' => Validator::is_ok('validate_redirect_config'),
            'caching' => Validator::is_ok('should_we_cache'),
            'version' => $this->version
        ];
    }

    //store hit and write hit_id in cookie
    public function trackingHits()
    {
        if (!config('api_configs.tracking_hits'))
        {
            return;
        }
        $data = [
            'rt' => array_get($input, 'rt', null),
            'app_id' => config('api_configs.client_id')
        ];
        $url = config('api_configs.secret_url') . '/hits';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);

        $hit_id = 0;
        if ($response)
        {
            $hit_id = $response->data->id;
        }

        return \Cookie::queue('hit_id', $res->id, time()+60*60*24*30, '/');
    }

    public function checkMultilingual($request)
    {
        if (!config('api_configs.is_multilingual'))
        {
            return [
                'redirect' => false,
                'query' => []
            ];
        }

        //getting language from url
        $main_language = env('MAIN_LANGUAGE') ? env('MAIN_LANGUAGE') : 'en';
        $requested_language = $request->segment(0);
        $requested_language = in_array($requested_language, config('api_configs.languages')) ? $requested_language : $main_language;

        //if user tries to change language via switcher rewrite user_language cookie
        if ($request->input('change_lang'))
        {
            setcookie('user_language', $request->input('change_lang'), time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $request->input('change_lang');
            if ($requested_language !== $request->input('change_lang'))
            {
                $redirect_path = $request->input('change_lang') == $main_language ? '/' : '/' . $request->input('change_lang') . '/ ';
                return [
                    'redirect' => true,
                    'redirect_path' => $redirect_path
                ];
            }
        }

        //rewriting user_language on home page
        if ($request->get('l') == $main_language)
        {
            setcookie('user_language', $main_language, time() + 60 * 30, '/');
        }

        //if user_language cookie not found getting it from Accept-Language header
        if (!array_key_exists("user_language", $_COOKIE))
        {
            $user_language = substr(locale_accept_from_http($request->header('accept-language')), 0, 2);
            $user_language = in_array($user_language, config('api_configs.languages')) ? $user_language : $main_language;
            setcookie('user_language', $user_language, time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $user_language;
        }

        //if user_language differs from requested language then redirecting on user_language page
        if ($request->path() == '/' && $request->get('l') !== $main_language && $requested_language !== $_COOKIE['user_language'])
        {
            return [
                'redirect' => true,
                'redirect_path' => $_COOKIE['user_language'] == $main_language ? '/' : '/' . $_COOKIE['user_language'] . '/ '
            ];
        }

        return [
            'redirect' => false,
            'query' => [
                'lang' => $requested_language,
                'main_language' => env('MAIN_LANGUAGE')
            ]
        ];
    }

}