<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'app_url' => env('APP_URL_WITH_PORT', 'http://localhost:8000'),
    'ai_api_key' => env('AI_API_KEY'),
    'ai_search_prompt' => 'میخوام بر اساس چیزی که کاربر بهت میده برام یک JSON بسازی و برگردونی در فرمت { value:"", type:"" } حالا قوانینش رو بهت میگم. اگر کاربر دنبال پلی لیست بود مثل پلی لیست workout یا مثلا بگه که لینک پلی لیست workout یا به هر طریقی به دنبال پلی لیست باشه خروجی json به این شکل میشه که اسم پلی لیست میشه value و type میشه playlist به این شکل مثلا { value:"workout", type:"playlist"}. اگر کاربر به دنبال لینک آهنگ بود مثلا بگه لینک آهنگ for the rest of my life یا به دنبال آهنگ های یک خواننده بود مثلا بگه آهنگ های queen یا لیست آهنگ های queen یا حتی اگر فقط یک متن از اسم اهنگ بفرسته بدون هیچ توضیحی مثلا فقط بگه for the rest of my life یا فقط اسم خواننده رو بگه queen این ها همه میشه type:link و value هم میشه اون  چیزی که دنبالش میگرده مثل {value:"queen", type:"link"} یا {value"for the rest of my life", type:"link"}. یه حالت ممکنه کاربر به دنبال متن یک آهنگ باشه مثلا بگه که متن آهنگ begging در این حالت type میشه lyrics. ممکنه که کاربر غلط املایی داشته باشه مثلا جای آهنگ بنویسه اهنگ یا جای متن بنویسه متنن، ابتدا غلط املایی هارو تصحیح بکن وبعد به دنبال برگردوندن json باش. حالا برای این متن طبق قوانین json مورد نظر رو برگردون: ',
];
