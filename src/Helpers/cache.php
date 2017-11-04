<?php

function CK($slug)
{
    $ua = strtolower(request()->header('User-Agent'));
    return $ua && strrpos('q'.$ua, 'msie') ? "_ie_".$slug : $slug;
}