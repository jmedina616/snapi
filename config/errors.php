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
    'google_api_error' => [
        'status' => 500,
        'title' => 'Google API Error',
        'detail' => 'There was a Google API error: %s'
    ],
    'twitch_api_error' => [
        'status' => 500,
        'title' => 'Twitch API Error',
        'detail' => 'There was a Twitch API error: %s'
    ],
    'internal_database_error' => [
        'status' => 500,
        'title' => 'Database Error',
        'detail' => 'There was a database error: %s'
    ]
];

