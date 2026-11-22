<?php

return [

    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Signature algorithm
    |--------------------------------------------------------------------------
    |
    | Cloudinary's default is SHA-1. Accounts that have switched to SHA-256
    | must set CLOUDINARY_SIGNATURE_ALGORITHM=sha256 so the signature we hand
    | to the browser matches what Cloudinary expects.
    |
    */
    'signature_algorithm' => env('CLOUDINARY_SIGNATURE_ALGORITHM', 'sha1'),

    /*
    |--------------------------------------------------------------------------
    | Upload folders
    |--------------------------------------------------------------------------
    |
    | One canonical folder per media-owning model. The same map is used to
    | validate upload signatures, stored public ids and the orphan purge
    | command, so a client cannot smuggle an asset into an unrelated folder.
    |
    */
    'folders' => [
        'destination' => 'jijel/destinations',
        'business' => 'jijel/businesses',
        'listing' => 'jijel/listings',
        'event' => 'jijel/events',
        'profile' => 'jijel/profiles',
        'post' => 'jijel/posts',
    ],

];
