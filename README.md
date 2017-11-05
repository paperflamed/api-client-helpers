# api-client-helpers

This is pack of helper class to make speedy api client work

# Instalation


Edit composer.json, add

"minimum-stability": "dev",

Install package.

composer require wizz/api-client-helpers


edit config/app.php, add

Wizz\ApiClientHelpers\ApiClientHelpersServiceProvider::class,

to providers array.


use php artisan vendor:publish to publish api_configs.php file.

create upload and documents folders in public directory

# Usage

it will just work

That's all. 

All routes with prefix api will be proxy redirected to secret_url from .env file.


What should be checked:
1. proxy all requests
2. proxy files of different structures
3. receive files of different types
4. receive 


write multiling docs


