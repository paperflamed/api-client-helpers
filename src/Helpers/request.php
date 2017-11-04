<?php 
use Symfony\Component\HttpFoundation\File\UploadedFile;
use \Illuminate\Http\Request;

function form_data_array_for_request(){
    $data = (session('addition')) ? session('addition') : [];
    $data = array_merge(request()->all(), $addition);
    $data['ip'] = request()->ip();
    $data['app_id'] = config('api_configs.client_id');
    return $data;
}

function form_query_string_for_request(){

}

function apiRequestProxy(Request $request)
{
    $path = $request->path();
    // what is the purpose of this check here
    $path = strpos($path, '/') === 0 ? $path : '/'.$path;
    $requestString = str_replace(config('api_configs.url'), '', $path);
    // get method from request
    $method = $request->method();
    // TODO mb use $_COOKIE here not $request->cookie()
    $cookie_string = getCookieStringFromArray($request->cookie());
    $data = form_data_array_for_request();

    $query = config('api_configs.secret_url').$requestString;
    $query .= ($method == "GET") ? '?'.http_build_query($data) : '';
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $query); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true); 
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); 
    curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    if (in_array($method, ["PUT", "POST", "DELETE"])) {
        
        if (array_get($data, 'files')) {
            $data['files'] = prepare_files_for_curl($data);
        }

        $data = ($method == "POST") ? array_sign($data) : http_build_query($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function getFilenameFromHeader($contentDisposition)
{
    if (!$contentDisposition) {
        return false;
    }

    preg_match('/filename="(.*)"/', $contentDisposition, $filename);
    $filename = clear_string_from_shit($filename[1]);
    return $filename;
}

// TODO bad function
function getPathFromHeaderOrRoute($contentDisposition, $slug)
{
    if ($contentDisposition) {
        preg_match('/filename="(.*)"/', $contentDisposition, $filename);
        $filename = clear_string_from_shit($filename[1]);
        $path = public_path().'/files/'.$filename;
        return $path;
    }
    $chunks = explode('/', $slug);
    $filename = array_get($chunks, count($chunks) - 1);
    if ($filename) 
    {
        $filename = clear_string_from_shit($filename);
        $path = public_path().'/documents/'.$filename;
        return response()->download($path);
    }

}

function prepare_files_for_curl(array $data, $file_field = 'files')
{
    $files = array_pull($data, $file_field);
    $files = array_sign($files);
    foreach ($files as $key => $file) 
    {
        if (is_object($file) && $file instanceof UploadedFile) 
        {
            $tmp_name = $file->getRealPath();
            $name = $file->getClientOriginalName();
            $type = $file->getMimeType();
            $files[$key] = new CURLFile($tmp_name, $type, $name);
        } 
    }
    return $files;
}

    /*

    Function to see if we should be caching response from frontend repo.

    If $slug is passed, it will also check whether this $slug is already in cache;

    */
function should_we_cache($slug = false)
{
    if(env('use_cache_frontend') === false) return false;

    if(request()->input('cache') === 'false') return false;

    if(!app()->environment('production')) return false;

    if($slug && !Cache::has($slug)) return false;

    return true;
}


function request_string_contains_redirect(){
    return preg_match('/^HTTP\/\d\.\d\s+(301|302)/', $string);
}
