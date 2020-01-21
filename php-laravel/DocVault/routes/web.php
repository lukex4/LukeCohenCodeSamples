<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

/**
*
* DocVault API Routes
*
*/

$router->group(['prefix' => 'api/v1'], function() use ($router) {

    /**
    *
    * FILE
    *
    */

    $router->group(['prefix' => 'file'], function() use ($router) {

        /**
        *
        * File Import Routes
        *
        */
        $router->group(['prefix' => 'ingress'], function() use ($router) {

            /** Ingress from given URL */
            $router->post('/fromurl', 'FileImportController@captureFromUrl');

            /** Request signed form for S3 HTTP POST form */
            $router->get('/s3request/{bindToIdent}[/{tags}]', 'FileImportController@s3FormRequest');

            /** By-URL Ingress: new version */
            $router->post('/version/fromurl', 'FileImportController@ingressVersionUrl');

            /** S3-POST Ingress: new version */
            $router->post('/version/s3request', 'FileImportController@ingressVersionS3');

            /** Request DV-Key for future S3 ingress */
            $router->get('/s3-defer/{bindToIdent}', 'FileImportController@s3Defer');

            /** Request S3 tokens for a deferred request */
            $router->get('/s3-deferred/{deferredDvKey}/{deferredToken}', 'FileImportController@s3RequestDeferred');

            /** For 'micro' class file uploads (maximum 512kb, publicly accessible, permanent URL, not bound to specific user) */
            $router->post('/micro', 'FileImportController@microIngress');

            /** For status pingbacks from AWS Lambda */
            $router->post('/newstatus', 'FileImportController@ingressStatusUpdate');

        });


        /**
        *
        * File Management Routes
        *
        */

        /** Return a file object by docvaultkey */
        $router->get('/bydvkey/{fileKey}[/{versionNo}]', 'FileManageController@fileByKey');

        /** 'Release' a file by docvaultkey */
        $router->get('/release/{fileKey}', 'FileManageController@createReleaseUrl');

        /** List all versions of a File */
        $router->get('/versions/{dvKey}', 'FileManageController@listFileVersions');

        /** Return a QR code for a File, by dvKey */
        $router->get('/qr/{fileKey}', 'FileManageController@fileGenQR');

        /** List all files in the database (DEVELOPMENT ONLY) */
        $router->get('/list/all', 'FileManageController@filesAll');

        /** List all files by a given userId */
        $router->get('/list/userid/{userId}[/{tag}]', 'FileManageController@filesByUserId');

        /** Search filenames, tags */
        $router->get('/search/{searchTerm}[/{userId}]', 'FileManageController@fileSearch');

        /** Delete a file by docvaultkey */
        $router->delete('/delete', 'FileManageController@markFileDeleted');

        /** Delete multiple files (max 25) */
        $router->delete('/delete-multiple', 'FileManageController@deleteMultiple');

        /** Set a file's tags - used to add or remove tags */
        $router->post('/tags/set', 'FileManageController@fileSetTags');

        /** Set a file's description */
        $router->post('/description/set', 'FileManageController@fileSetDescription');

        /** Move a File to a Path */
        $router->post('/topath', 'FileManageController@fileToPath');

        /** Rename a file */
        $router->post('/rename', 'FileManageController@fileRename');

        /** Serve a Micro File */
        $router->get('/serve/{dvKey}', 'FileManageController@microServe');


        /**
        *
        * File Meta Routes
        *
        */
        $router->group(['prefix' => 'meta'], function() use ($router) {

            /** Ingress from given URL */
            $router->get('/list/{dvKey}', 'FileMetaController@list');

            /** Request signed form for S3 HTTP POST form */
            $router->get('/get/{dvKey}/{metaKey}', 'FileMetaController@get');

            /** By-URL Ingress: new version */
            $router->post('/set', 'FileMetaController@set');

            /** S3-POST Ingress: new version */
            $router->delete('/delete', 'FileMetaController@delete');

            /** Request DV-Key for future S3 ingress */
            $router->get('/listall', 'FileMetaController@listAll');

        });

    });


    /**
    *
    * Paths (folders)
    *
    */
    $router->group(['prefix' => 'path'], function() use ($router) {

        /** Create Path */
        $router->post('/create', 'PathManageController@pathCreate');

        /** Rename Path */
        $router->post('/edit', 'PathManageController@pathEdit');

        /** Move Path */
        $router->post('/move', 'PathManageController@pathMove');

        /** Delete Path */
        $router->delete('/delete', 'PathManageController@pathDelete');

        /** Set description for a Path */
        $router->post('/description/set', 'PathManageController@setPathDescription');

        /** Set tags for a Path */
        $router->post('/tags/set', 'PathManageController@setPathTags');

        /** Sets quicklink flag to TRUE for a given Path */
        $router->get('/quicklink/set/{pathIdent}', 'PathManageController@quicklinkSet');

        /** Sets quicklink flag to FALSE for a given Path */
        $router->get('/quicklink/unset/{pathIdent}', 'PathManageController@quicklinkUnset');

        /** Lists all quicklinks for a given userId */
        $router->get('/quicklink/list/{userId}', 'PathManageController@quicklinksList');

        /** List Paths */
        $router->get('/list/{userId}[/{basePoint}]', 'PathManageController@pathsList');

        /** List all Files and sub-Paths in a given Path */
        $router->get('/ls/{userId}[/{pathIdent}]', 'PathManageController@pathListFiles');

        /** List all Paths in the system  (DEVELOPMENT ONLY)*/
        $router->get('/all', 'PathManageController@pathsAll');

    });


    /**
    *
    * Natural-Language File Search
    *
    */
    $router->group(['prefix' => 'nlp'], function() use ($router) {

      /** Main Files NLP lookup endpoint */
      $router->get('/search/files/{queryText}[/{userId}]', 'NlpController@nlpFiles');

      /** Development endpoint */
      $router->get('/test', 'NlpController@test');

      /** Generates tags for the given File (by dvKey) */
      $router->get('/file/autotag/{dvKey}', 'NlpController@generateTags');

    });


});
