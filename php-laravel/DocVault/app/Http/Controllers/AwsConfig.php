<?php

return array(
    'includes' => array('_aws'),
    'services' => array(
        'default_settings' => array(
            'params' => array(
                'credentials' => array(
                    'key'    => env('S3_USER_ID'),
                    'secret' => env('S3_USER_KEY'),
                ),
                'region' => 'us-west-1'
            )
        )
    )
);

?>
