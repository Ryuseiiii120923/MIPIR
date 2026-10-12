<?php

return [
    'enabled'  => env('DUMMY_LOGIN_ENABLED', false),
    'userid'   => (int) env('DUMMY_LOGIN_USERID', 999999),
    'password' => env('DUMMY_LOGIN_PASSWORD'),

    'attributes' => [
        'InspectorNo' => env('DUMMY_LOGIN_INSPECTOR_NO', 'DUMMY'),
        'Plant'       => env('DUMMY_LOGIN_PLANT'),
    ],
];