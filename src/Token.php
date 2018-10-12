<?php
namespace Wizz\ApiClientHelpers;

use \Illuminate\Http\Request;
use \Cache;
use Wizz\ApiClientHelpers\Helpers\CacheHelper;

class Token
{
    protected $data = '';

    public $errors;

    public $cookies;

    public $request;

    private $form_params;

    private $bs_data_query;

    private function removeUnnesseseryKeys($string_array) {
      $banned_keys = [
        'pname',
        'rt',
        'utm_search_engine',
        'utm_host',
        'utm_referrer',
        'utm_keyword'
      ];

      foreach($banned_keys as $key) {
        unset($string_array[$key]);
      }

      return $string_array;
    }

    protected function getFromBootstrap()
    {
      $query = $this->bs_data_query;
      $form_params = $this->form_params;

      $id = $form_params['client_id'];
      $url = explode('?', $form_params['url']);

      $path = $url[0];
      $query_string = [];
      parse_str($url[1], $query_string);

      $always_cache = [
        '/place-new-order',
        '/place-new-order-2',
        '/place-new-order-3'
      ];

      $has_queries = count( $this->removeUnnesseseryKeys($query_string) );
      $in_always_cache_path = in_array($path, $always_cache);
      $should_skip_cache = $has_queries && !$in_always_cache_path;

      $unnessesery_routes = [
        '/prices',
        '/about',
        '/how-it-works',
        '/samples',
        '/frequently-asked-questions',
        '/contacts',
        '/essay-writing-service',
        '/grading-and-marking-service',
        '/dissertation-writing-service',
        '/resume-writing-service',
        '/place-new-order',
        '/place-new-order-2',
        '/place-new-order-3'
      ];

      $cache_key = in_array($path, $unnessesery_routes) ? 
        "bootstrap_data_for_client_id_{$id}"
       : "bootstrap_data_for_client_id_{$id}_path_{$path}";

      $this->data = CacheHelper::cacher($cache_key, function() use($query) {
        session(['addition' => request()->all()]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $query);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = explode("\r\n\r\n", $res);
        $headers = (count($data) == 3) ? $data[1] : $data[0];
        $res = (count($data) == 3) ? $data[2] : $data[1];
        setCookiesFromCurlResponse($headers);

        $output = json_decode($res);

        if (!is_object($output)) {
          $this->errors = $res;
          return false;
        }
        if (property_exists($output, 'errors') && count($output->errors) > 0) {
          $this->errors = $output->errors;
          return false;
        }

        return $output->data;
      }, 60*24*30, $should_skip_cache, true);

      return (boolean) $this->data;
    }

    public function getBootstrapData()
    {
        if (is_object($this->data)) {
            return $this->data->bootstrap;
        }

        return [];
    }

    public function init()
    {
      $this->setFormParams();
      $this->prepareQuery();
      return $this->getFromBootstrap();
    }

    private function setFormParams() {
      $path = request()->path() . '?' . http_build_query(request()->query());
      $this->form_params = [
          'grant_type' => CacheHelper::conf('grant_type'),
          'client_id' => CacheHelper::conf('client_id'),
          'client_secret' => CacheHelper::conf('client_secret'),
          'url' => $path ? "/$path" : '/',
      ];
      return $this->form_params;
    }

    private function prepareQuery()
    {
      $this->bs_data_query = CacheHelper::conf('secret_url').'/oauth/access_token'.'?'.http_build_query($this->form_params);
      return $this->bs_data_query;
    }
}
