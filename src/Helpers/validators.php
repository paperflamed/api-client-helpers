<?php 


/*

Validating that all our configs necessary for frontend repo are in place.

*/
function validate_frontend_config()
{
    if(! env('frontend_repo_url')) return false;

    if(substr(env('frontend_repo_url'), -1) != '/') return false;

    return true;
}

/*

Validating that all our configs necessary for redirect are in place.

*/
function validate_redirect_config()
{
    if(! env('secret_url')) return false;

    return true;
}

function is_ok($func)
{
    return ($func()) ? 'OK' : 'OFF';
}