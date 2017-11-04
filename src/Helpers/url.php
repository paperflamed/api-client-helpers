<?php

function splitUrlIntoSegments($url)
{
    $url_without_query_string = explode('?', $url)[0];
    return array_values(array_filter(explode('/', $url_without_query_string), function ($var) {
        return ($var) ? true : false;
    }));
}