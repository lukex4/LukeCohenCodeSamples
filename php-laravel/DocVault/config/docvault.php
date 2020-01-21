<?php

return [
    'lambda_trigger_ingress'    => env('LAMBDA_TRIGGER_INGRESS', ''),
    's3_user_id'                => env('S3_USER_ID',''),
    's3_user_key'               => env('S3_USER_KEY',''),
    's3_url'                    => env('S3_URL',''),
    's3_bucket_name'            => env('S3_BUCKET_NAME',''),
    'x_gateway_key'             => env('X_GATEWAY_KEY','test'),
    'lz_docvault_baseurl'       => env('LZ_DOC_VAULT_BASEURL','test'),
    'lz_docvault_version'       => env('LZ_DOC_VAULT_VERSION','v1'),
    'appsync_endpoint'          => env('APPSYNC_ENDPOINT'),
    'appsync_apikey'            => env('APPSYNC_APIKEY')
];

?>
