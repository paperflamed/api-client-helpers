<?php 
namespace Wizz\ApiClientHelpers;
use \Illuminate\Http\Request;
use \Cache;

class Token
{
    // This is a singleton object which contains initial data and token
    protected $data = '';

    public $errors;

    public $cookies;

    public $request;

    // private static $_instance = null;

    // private function __construct() {}

    // protected function __clone() {}
    
    // static public function getInstance() 
    // {
    //     if(is_null(self::$_instance))
    //     {
    //         self::$_instance = new self();
    //     }

    //     return self::$_instance;
    // }

    protected function getFromBootstrap($query)
    {   
        $cache_key = 'bootstrap_data_from_api';
        if (config('api_configs.is_multilingual')) //if we have many languages then add user language to cache key
        {
            $query_array = [];
            parse_str(parse_url($query, PHP_URL_QUERY), $query_array);
            $cache_key .= '_' . $query_array['lang'];
        }
        // if (false) 
        if (Cache::has($cache_key)) 
        {
            $output = Cache::get($cache_key);

        } else
        {
            // $addition = array_get($_SERVER, 'QUERY_STRING', '');
            // $query .= ($addition) ? '&'.$addition : '';
            session(['addition' => request()->all()]);

            // $cookie_string = getCookieStringFromRequest(request());
            
            // session_write_close();
            $ch = curl_init(); 
            curl_setopt($ch, CURLOPT_URL, $query); 
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
            // curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
            $res = curl_exec($ch); 
            curl_close($ch);

            $data = explode("\r\n\r\n", $res);
            $headers = (count($data) == 3) ? $data[1] : $data[0];
            $res = (count($data) == 3) ? $data[2] : $data[1];
            $cookies = setCookiesFromCurlResponse($headers);

            $output = json_decode($res);

            Cache::put($cache_key, $output, 100);
            
        }
        if(!is_object($output))
        {
            $this->errors = $res;
            return 'false';
        }
        if(property_exists($output, 'errors') && count($output->errors) > 0)
        {
            $this->errors = $output->errors;
            return false;
        }

        $this->data = $output->data;

        return true;
    }
    
    public function getBootstrapData()
    {
        if (is_object($this->data)) 
        {
            return $this->data->bootstrap;
        }
        
        return [];
    }

    public function getToken()
    {
        if (is_object($this->data)) 
        {
            $access_token = $this->data->access_token;

            session(['access_token' => $access_token]);
            
            return $access_token;
        }

        return '';
    }

    public function init()
    {
        return $this->getFromBootstrap($this->prepareQuery());
    }

    private function prepareQuery()
    {
        $form_params = [
            'grant_type' => config('api_configs.grant_type'),
            'client_id' => config('api_configs.client_id'),
            'client_secret' => config('api_configs.client_secret'),
        ];
        $form_params = array_merge($form_params, $this->multilingualQuery());
        return config('api_configs.secret_url').'/oauth/access_token'.'?'.http_build_query($form_params);
    }

    public function multilingualQuery()
    {
        if (!config('api_configs.is_multilingual'))
        {
            return [];
        }
        $main_language = config('api_configs.main_language');
        //if user_language cookie not found getting it from Accept-Language header
        if (!array_key_exists("user_language", $_COOKIE))
        {
            $user_language = substr(locale_accept_from_http(request()->header('accept-language')), 0, 2);
            $user_language = in_array($user_language, config('api_configs.languages')) ? $user_language : $main_language;
            setcookie('user_language', $user_language, time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $user_language;
        }
        $change_language = request()->get('change_lang', null);
        if ($change_language)
        {
            $change_language = in_array($change_language, config('api_configs.languages')) ? $change_language : $main_language;
            setcookie('user_language', $change_language, time() + 60 * 30, '/');
            $_COOKIE['user_language'] = $change_language;
        }
        return [
            'lang' => $_COOKIE['user_language'],
            'main_language' => $main_language
        ];
    }
}
