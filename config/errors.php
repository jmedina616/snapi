<?php

return [
    /*
      |—————————————————————————————————————
      | Default Errors
      |—————————————————————————————————————
     */
    'not_found' => [
        'status' => 404,
        'title' => 'Not Found',
        'detail' => 'The resource you were looking for was not found.'
    ],
    'method_not_allowed' => [
        'status' => 405,
        'title' => 'Method Not Allowed',
        'detail' => 'A request method is not supported for the requested resource.'
    ],
    'config_not_found' => [
        'status' => 404,
        'title' => 'Configuration Not Found',
        'detail' => 'Platform configurations were not found for account \'%s\'.'
    ],
    'account_not_found' => [
        'status' => 404,
        'title' => 'Account Not Found',
        'detail' => 'Account \'%s\' could not be found.'
    ],
    'action_not_found' => [
        'status' => 404,
        'title' => 'Action Not Found',
        'detail' => 'The requested action \'%s\' could not be found.'
    ],
    'platform_not_found' => [
        'status' => 404,
        'title' => 'Platform Not Found',
        'detail' => 'The requested platform \'%s\' could not be found.'
    ],
    'endpoint_not_found' => [
        'status' => 404,
        'title' => 'Endpoint Not Found',
        'detail' => 'The requested endpoint could not be found.'
    ],
    'not_authorized' => [
        'status' => 403,
        'title' => 'Not Authorized',
        'detail' => 'You do not have permission to make this request.'
    ],
    'service_not_authorized' => [
        'status' => 403,
        'title' => 'Service Not Authorized',
        'detail' => 'You do not have permission to access this service.'
    ],
    'socail_media_api_error' => [
        'status' => 500,
        'title' => 'Socail Media API Error',
        'detail' => 'There was a social media API error: %s'
    ],
    'internal_database_error' => [
        'status' => 500,
        'title' => 'Database Error',
        'detail' => 'There was a database error: %s'
    ]
];
