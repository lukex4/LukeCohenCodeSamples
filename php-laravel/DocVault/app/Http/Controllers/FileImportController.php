<?php

namespace App\Http\Controllers;

/* Laravel-Lumen */
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

/* DocVault Controllers */
use App\Http\Controllers\ControllerHelpers as Helpers;
use App\Http\Controllers\NlpController as NLP;
use App\Http\Controllers\DocVault as DocVault;

/* DocVault Models */
use App\File;
use App\Path;
use App\FilePath;
use App\EventLog;
use App\Microfile;

/* Et al. */
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleSoftwareIO\QrCode\QrCodeServiceProvider;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleSoftwareIO\QrCode\BaconQrCodeGenerator;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

class FileImportController extends BaseController {

    /* AWS S3 Client */
    private $s3Client;

    /* S3 bucket base URL */
    private $s3url;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

        /** Docvault Base URL */
        $this->baseurl          = config('docvault.lz_docvault_baseurl');

        /** S3 Bucket */
        $this->s3url            = config('docvault.s3_url');
        $this->s3bucketName     = config('docvault.s3_bucket_name');

        /* S3 User */
        $this->s3UserID         = config('docvault.s3_user_id');
        $this->s3UserKey        = config('docvault.s3_user_key');

        /** Lambda Trigger (Ingress) URL */
        $this->lambdaTriggerUrl = config('docvault.lambda_trigger_ingress');

        $this->s3Client         = \Aws\S3\S3Client::factory(array(
            'version'   => 'latest',
            'region'    => 'us-west-1',
            'credentials' => array(
                'key'    => env('S3_USER_ID'),
                'secret' => env('S3_USER_KEY'),
            )
        ));

    }


    /** Generates a DVF record for a file that will be uploaded later */
    public static function s3Defer(String $bindToIdent) {

        try {

            /* Is this File bound to a userId or an application (an offering, etc.) */
            if (!isset($bindToIdent) || strlen($bindToIdent) == 0) {
                throw new \Exception('Please provide a toUserId for the File you wish to import');
            }

            if (is_numeric($bindToIdent)) {
                $userId = $bindToIdent;
            }

            $application = $bindToIdent;

            /** Generate the bearer token so this deferred ingress can only be triggered by the consumer making the request now */
            $deferred_key       = bin2hex(random_bytes(64));

            /** Create the File record */
            $newFile = self::createFileRecord(array(
                'userid'        => $userId,
                'application'   => $bindToIdent ?? '',
                'deferred'      => 'true',
                'deferred_key'  => $deferred_key
            ));

            $newFileRef         = $newFile['newFileRef'];

            /** Output new dvKey and deferredToken */
            $response = array(
                'dvKey'         => $newFileRef,
                'deferredToken' => $deferred_key
            );

            return response()->json($response);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /** Generate the S3 signing keys for a deferred ingress */
    public function s3RequestDeferred(String $deferredDvKey, String $deferredToken) {

        try {

            $file = File::where(array('docvaultkey' => $deferredDvKey, 'deferred' => 'true', 'deferred_key' => $deferredToken, 'deleted' => 0))->first();

            if (!$file) {
                \Log::error('File not found: ' . $deferredDvKey);
                throw new \Exception('No deferred ingress exists for this dvKey and deferredToken');
            }

            /** Generate the upload 'policy' document in JSON */
            $policyExpiration   = date('Y-m-d\TH:i:s.Z\Z', (time()+43200));

            $uploadPolicy       = '{
                "expiration": "' . $policyExpiration . '",
                "conditions": [
                    {"bucket": "' . $this->s3bucketName . '"},
                    ["starts-with", "$key", "' . $file->docvaultkey . '/"],
                    {"acl": "public-read"}
                ]
            }';

            /** Encode the Policy */
            $uploadPolicyBase64 = base64_encode($uploadPolicy);

            /** Sign and encrypt the policy using the relevant AWS Secret Key */
            $uploadPolicyHash   = Helpers::hmacsha1($this->s3UserKey, $uploadPolicyBase64);
            $uploadPolicySigned = Helpers::hex2b64($uploadPolicyHash);

            /** Turn the completed, signed Policy into JSON and return it */
            $uploadPolicyToken = array(
                'policy'                => $uploadPolicyBase64,
                'signature'             => $uploadPolicySigned,
                'awsaccesskeyid'        => $this->s3UserID,
                'dvKey'                 => $file->docvaultkey,
                'expires'               => $policyExpiration,
                'note'                  => 'a field called \'key\' with \'' . $file->docvaultkey . '/NAME_OF_FILE\' should be included with the upload form submission'
            );

            /** Log */
            Helpers::logEvent('S3 POST (deferred) upload authorisation requested for dvKey ' . $deferredDvKey);

            /** Clear deferred data on the File record, so this request can't be made again */
            $file->deferred     = '';
            $file->deferred_key = '';

            $file->save();

            return response()->json($uploadPolicyToken);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Imports a micro File (maximum 512kb, publicly accessible, permanent URL, not bound to specific user).
    *
    * Micro files are stored as BLOBs in the database, and are served by DocVault-core. Providing a rapid, convenient way of importing and serving small, non-sensitive files.
    *
    */
    public function microIngress(Request $request) {

        try {

            $req = $request->all();

            if (count($req) == 0) {
                throw new \Exception('Invalid request parameters');
            }

            /** Extract the X-Api-Product-Key header */
            $apiProductKey = Helpers::apiProductKeyHeader($request);

            if (!$apiProductKey) {
                throw new \Exception('Missing X-Api-Product-Key header');
            }

            /*
            *
            * - create File container record
            *
            * - ascertain file mimetype
            *
            * - insert file binary into microfiles table
            *
            * - update File record with the ID of the microfiles record
            *
            * - return appropriate response
            *
            */

            /* Set the relevant File record variables */
            // $userId         = $req['toUserId'] ?? 0;
            // $application    = $req['applicationIdent'] ?? '';
            // $tags           = $req['tags'] ?? '';
            //
            // $newFile        = self::createFileRecord(array(
            //     'userid'            => $userId,
            //     'application'       => $application,
            //     'api_productkey'    => $apiProductKey,
            //     'tags'              => $tags
            // ));
            //
            // $newFileRef     = $newFile['newFileRef'];

            /* Get the Base64 file input */
            // $file           = $req['file'];

            /* Get mimetype and filesize of microfile */
            // $fileDecoded    = base64_decode($file);
            // $fileData       = getimagesizefromstring($fileDecoded);
            //
            // $filetype       = $fileData['mime'];
            // $filesize       = strlen($fileDecoded);

            /* Throw if larger than 512kb */
            // if ($filesize > 512000) {
            //     throw new \Exception('Microfile imports only support files up to 512kb');
            // }

            /* Create a Microfile record to store File contents */
            // $microfile = new Microfile;
            //
            // $microfile->filebase    = serialize($file);
            // $microfile->save();

            /* Update File record with the microfileId, filetype and filesize */
            // $newFile = File::where('docvaultkey', $newFileRef)->first();
            //
            // $newFile->microfile         = TRUE;
            // $newFile->mimetype          = $filetype;
            // $newFile->filesize          = $filesize;
            // $newFile->ingress_status    = 'complete';
            // $newFile->microfile_id      = $microfile->id;
            //
            // $newFile->save();



            /* Convert Base64 to binary, upload to S3, get URL */

            /* Set the relevant File record variables */
            $userId         = $req['toUserId'] ?? 0;
            $application    = $req['applicationIdent'] ?? '';
            $tags           = $req['tags'] ?? '';

            $newFile        = self::createFileRecord(array(
                'userid'            => $userId,
                'application'       => $application,
                'api_productkey'    => $apiProductKey,
                'tags'              => $tags
            ));

            $newFileRef     = $newFile['newFileRef'];

            /* Get the Base64 file input */
            $file           = $req['file'];

            /* Get mimetype and filesize of microfile */
            $fileDecoded    = base64_decode($file);
            $fileData       = getimagesizefromstring($fileDecoded);

            $filetype       = $fileData['mime'];
            $filesize       = strlen($fileDecoded);

            /* Throw if larger than 512kb */
            if ($filesize > 512000) {
                throw new \Exception('Microfile imports only support files up to 512kb');
            }

            /* Update File record with the microfileId, filetype, filesize */
            $newFile = File::where('docvaultkey', $newFileRef)->first();

            $newFile->microfile         = TRUE;
            $newFile->mimetype          = $filetype;
            $newFile->filesize          = $filesize;
            $newFile->ingress_status    = 'complete';

            $newFile->save();

            /* S3 upload */
            $i          = explode(",", $file);
            $image      = end($i);
            $imageData  = base64_decode($image);

            $upload     = $this->s3Client->upload($this->s3bucketName, $newFileRef, $imageData, 'public-read');

            /* Uploaded image URL */
            $newUrl     = $this->s3url . $newFileRef;

            /* Respond */
            return response()->json(array(
                'file_status'   => 'imported',
                'dvKey'         => $newFileRef,
                'url'           => $newUrl
            ));


        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /* TESTING function only */
    public function microAll() {

        $micros = Micro::all();

        return response()->json($micros);

    }


    /**
    *
    * Sends a GraphQL mutation query to AWS AppSync
    *
    */
    private function appSyncMutation($dvKey, $newStatus) {

        try {

            $guzzle = new Client();

            /** Prepare the POST call to AppSync */
            $headers = [
                'Content-Type'    => 'application/json',
                'authority'       => 'maxc6ibkz5fyfeprhodjsvicke.appsync-api.us-east-1.amazonaws.com',
                'x-api-key'       => config('docvault.appsync_apikey')
            ];

            $data = '{"query":"mutation Message {\n    message(dvKey: \"' . $dvKey . '\", status: \"' . $newStatus . '\") {\n        dvKey\n    \tstatus\n    }\n}","variables":null,"operationName":"Message"}';

            $options = [
                'body'      => $data,
                'headers'   => $headers,
            ];

            $appSyncEndpoint = config('docvault.appsync_endpoint');

            $result = $guzzle->post($appSyncEndpoint, $options);

            return true;

        } catch (\Exception $ex) {
            \Log::error($ex->getMessage() . ' - '. $ex->getFile() . ' : '.$ex->getLine());
            Helpers::dieWithException($ex);
        }

    }


    /**
    *
    * Updates File record once the actual ingress is complete (success or failure)
    *
    */
    public function ingressStatusUpdate(Request $request) {
        \Log::info($request);
        try {

            $update = $request->all();

            $file = File::where(array('docvaultkey' => $update['dvkey'], 'deleted' => 0))->first();

            if (!$file) {

              return response()->json(array(
                  'status'    => 'file not found'
              ),400);

            }

            switch($update['ingress']) {

                case 'AV':
                    $file->viruschecked             = $update['viruschecked'];
                    $file->viruscheck_clean         = $update['viruscheck_clean'];
                    $file->viruscheck_timestamp     = $update['viruscheck_timestamp'];
                    break;

                case 'S3':

                    $file->filename                 = $update['filename'];
                    $file->mimetype                 = $update['mimetype'];
                    $file->ingress_status           = $update['status'];
                    break;

                case 'URL':

                    $file->filename                 = $update['filename'];
                    $file->mimetype                 = $update['mimetype'];
                    $file->ingress_status           = $update['status'];
                    break;

            }

            $file->save();

            /** If this File is to be auto-tagged, trigger auto-tag */
            if ($file->to_autotag == TRUE) {
              \Log::info($file->docvaultkey . ' to_autotag true, triggering autotag');
              NLP::generateTags($file->docvaultkey);
            }

            /** Send AppSync mutation */
            if (isset($update['status'])) {
                self::appSyncMutation($update['dvkey'], $update['status']);
            }

            /** Log */
            Helpers::logEvent('File status changed for ' . $update['dvkey']);

            return response()->json(array(
                'status'    => 'update received'
            ),200);

        } catch (\Exception $ex) {
            Helpers::dieWithException($ex);
        }

    }


    /**
    *
    * Handles file ingress from a given URL
    *
    */
    public function captureFromUrl(Request $request) {

        try {

            /* Capture the request */
            $captureRequest = $request->all();

            if (count($captureRequest) == 0) {
                throw new \Exception('Invalid request parameters');
            }

            /** Extract the X-Api-Product-Key header */
            $apiProductKey = Helpers::apiProductKeyHeader($request);

            if (!$apiProductKey) {
                throw new \Exception('Missing X-Api-Product-Key header');
            }

            /** Extract the relevant request components */
            $fileUrl        = $captureRequest['fileUrl'];
            $userId         = $captureRequest['toUserId'];
            $application    = $captureRequest['applicationIdent'];

            $tag            = $captureRequest['tags'] ?? '';

            /* Check the fileUrl is RFC-compliant URL */
            if (!filter_var($fileUrl, FILTER_VALIDATE_URL)) {
                throw new \Exception('Invalid file URL');
            }

            /* Create new File record and extract new dvKey and bearerToken */
            $filename   = basename($fileUrl);

            $newFile = self::createFileRecord(array(
                'userid'            => $userId,
                'application'       => $application,
                'api_productkey'    => $apiProductKey,
                'tags'              => $tags
            ));

            $newFileRef         = $newFile['newFileRef'];
            $newFileBearerToken = $newFile['newFileBearerToken'];

            /** Trigger the Lambda fetchFileFromUrl function */
            $triggerBody = '{"fileUrl":"' . $fileUrl . '","dvKey":"' . $newFileRef . '","requestApiProductKey":"' . $apiProductKey . '"}';

            self::triggerLambdaUrlIngress($triggerBody, $this->lambdaTriggerUrl);
            \Log::info('Lambda trigger request');

            /** Log */
            Helpers::logEvent('Ingress-by-URL requested for URL ' . $fileUrl . ' (dvKey ' . $newFileRef . ')');

            /* Respond */
            return response()->json(array(
                'response'      => 'queuedForIngress',
                'dvKey'         => $newFileRef,
                'dvBearerToken' => $newFileBearerToken
            ));

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Prepares and fires HTTP request to trigger Lambda for file ingress
    *
    */
    private static function triggerLambdaUrlIngress($triggerPayload, $triggerUrl) {

      try {

        $guzzle = new Client();

        /** The following is a successful asynchronous POST call. This approach requires the use of Guzzle's Request->wait() function to be called, which triggers any Promises to complete early, thus ending the wait for a response */
        $lambdaReq = $guzzle->postAsync($triggerUrl, [
            'headers'       => ['X-Amz-Invocation-Type' => 'Event'],
            'http_errors'   => true,
            'body'          => $triggerPayload
        ])->then(

            function ($response) {
                /* Successful request, no need to do anything */
            }, function ($exception) {
                /* The request to DocVault-Lambda failed, throw */
                throw new \Exception($exception->getMessage());
            }

        );

        $lambdaReq->wait();

        return true;

      } catch (\Exception $ex) {
          \Log::error($ex->getMessage() . ' - '. $ex->getFile() . ' : '.$ex->getLine());
          Helpers::dieWithException($ex);
      }

    }


    /**
    *
    * Prepares DocVault for a direct upload (S3 POST)
    *
    * - Returns the encrypted signature, encoded JSON policy, and dvKey for the inbound File
    *
    */
    public function s3FormRequest(Request $request, $bindToIdent, string $tags = '') {

        try {

            /* Create a dvKey and record for the new file */
            $userId         = 0;
            $application    = '';
            $to_autotag     = $request->input('autotag', '0');

            /* Is this File set to be auto-tagged once upload is complete? */
            if ($to_autotag == '1') {
              $to_autotag = TRUE;
            } else {
              $to_autotag = FALSE;
            }

            /* Is this File bound to a userId or an application (an offering, etc.) */
            if (!isset($bindToIdent) || strlen($bindToIdent) == 0) {
                throw new \Exception('Please provide a toUserId for the File you wish to import');
            }

            if (is_numeric($bindToIdent)) {
                $userId = $bindToIdent;
            }

            /** Create the new File record and extract new dvKey and bearerToken */
            $newFile = self::createFileRecord(array(
                'userid'        => $userId,
                'tags'          => $tags,
                'application'   => $bindToIdent ?? '',
                'to_autotag'    => $to_autotag
            ));

            $newFileRef         = $newFile['newFileRef'];
            $newFileBearerToken = $newFile['newFileBearerToken'];

            /** Generate the upload 'policy' document in JSON */
            $policyExpiration   = date('Y-m-d\TH:i:s.Z\Z', (time()+43200));

            $uploadPolicy       = '{
                "expiration": "' . $policyExpiration . '",
                "conditions": [
                    {"bucket": "' . $this->s3bucketName . '"},
                    ["starts-with", "$key", "' . $newFileRef . '/"],
                    {"acl": "public-read"}
                ]
            }';

            /** Encode the Policy */
            $uploadPolicyBase64 = base64_encode($uploadPolicy);

            /** Sign and encrypt the policy using the relevant AWS Secret Key */
            $uploadPolicyHash   = Helpers::hmacsha1($this->s3UserKey, $uploadPolicyBase64);
            $uploadPolicySigned = Helpers::hex2b64($uploadPolicyHash);

            /** Turn the completed, signed Policy into JSON and return it */
            $uploadPolicyToken = array(
                'policy'                => $uploadPolicyBase64,
                'signature'             => $uploadPolicySigned,
                'awsaccesskeyid'        => $this->s3UserID,
                'dvKey'                 => $newFileRef,
                'dvBearerToken'         => $newFileBearerToken,
                'expires'               => $policyExpiration,
                'note'                  => 'a field called \'key\' with \'' . $newFileRef . '/NAME_OF_FILE\' should be included with the upload form submission'
            );

            /** Log */
            Helpers::logEvent('S3 POST upload authorisation requested for user/application ' . $bindToIdent);

            return response()->json($uploadPolicyToken);

        } catch (\Exception $ex) {

            \Log::error($ex->getMessage() . ' - '. $ex->getFile() . ' : '.$ex->getLine());

            return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Ingress-by-URL a new version of a File
    *
    */
    public function ingressVersionUrl(Request $request) {

        try {

            /** Input checking */
            $req = $request->all();

            if (!isset($req['dvKey'])) {
                throw new \Exception('Please provide a valid dvKey');
            }

            if (!isset($req['dvBearerToken'])) {
                throw new \Exception('Please provide a valid dvBearerToken');
            }

            if (!isset($req['fileUrl']) || !filter_var($req['fileUrl'], FILTER_VALIDATE_URL)) {
                throw new \Exception('Please provide a valid fileUrl');
            }

            $apiProductKey = Helpers::apiProductKeyHeader($request);

            if (!$apiProductKey) {
                throw new \Exception('Missing X-Api-Product-Key header');
            }

            $fileUrl        = $req['fileUrl'];
            $dvKeyOrig      = $req['dvKey'];
            $dvBearerToken  = $req['dvBearerToken'];
            $tags           = '';

            if (isset($req['tags'])) {
                $tags = $req['tags'];
            }

            /** Create new Version record */
            $fileNewVersion = self::createFileVersion($dvKeyOrig, $dvBearerToken, '', $apiProductKey);

            if (isset($fileNewVersion['error'])) {
                throw new \Exception($fileNewVersion['error']);
            }

            $newVersionNo       = $fileNewVersion['newVersionNumber'];
            $newVersionDvKey    = $fileNewVersion['newVersionDvKey'];
            $nextToken          = $fileNewVersion['nextToken'];

            /** Process Lambda trigger */
            $triggerBody = '{"fileUrl":"' . $fileUrl . '","dvKey":"' . $newVersionDvKey . '","requestApiProductKey":"' . $apiProductKey . '"}';

            self::triggerLambdaUrlIngress($triggerBody, $this->lambdaTriggerUrl);
            \Log::info('Lambda trigger request');

            /** Log */
            Helpers::logEvent('Ingress-by-URL requested for URL ' . $fileUrl . ' (dvKey ' . $newVersionDvKey . ')');

            /** Output the new next_token as dvBearerToken */
            return response()->json(array(
                'response'          => 'New version (' . $newVersionNo . ') queuedForIngress',
                'dvKey'             => $newVersionDvKey,
                'nextDvBearerToken' => $nextToken
            ));

        } catch (\Exception $ex) {

            return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    *
    *
    */
    public function ingressVersionS3(Request $request) {

        try {

            /** Input checks */
            $req = $request->all();

            if (!isset($req['dvKey'])) {
                throw new \Exception('Please provide a valid dvKey');
            }

            if (!isset($req['dvBearerToken'])) {
                throw new \Exception('Please provide a valid dvBearerToken');
            }

            $apiProductKey = Helpers::apiProductKeyHeader($request);

            if (!$apiProductKey) {
                throw new \Exception('Missing X-Api-Product-Key header');
            }

            $dvKeyOrig      = $req['dvKey'];
            $dvBearerToken  = $req['dvBearerToken'];
            $tags           = (isset($req['tags']) ? $req['tags'] : '');
            $description    = (isset($req['description']) ? $req['description'] : '');

            /** Load original File to ascertain description & tags */
            $fileOrig = File::where(array(
                'docvaultkey' => $dvKeyOrig, 'deleted' => 0
            ))->first();

            if (!$fileOrig) {
                throw new \Exception('Original File does not exist');
            }

            /** If File has a version higher than zero, load it */
            $fileHighestVersion = $fileOrig->highest_version;

            if ($fileHighestVersion > 0) {

                $fileOrig = File::where(array(
                    'docvaultkey' => $dvKeyOrig . '-' . $fileHighestVersion, 'deleted' => 0
                ))->first();

            }

            $tagsOrig           = $fileOrig->tags;
            $descriptionOrig    = $fileOrig->description;

            $tags               = ($tags == '' && strlen($tagsOrig) > 0 ? $tagsOrig : $tags);
            $description        = ($description = '' && strlen($descriptionOrig) > 0 ? $descriptionOrig : $description);

            /** Create new Version record */
            $filename = '';

            $fileNewVersion     = self::createFileVersion($dvKeyOrig, $dvBearerToken, $filename, $apiProductKey, $tags, $description);

            // $newVersionNo       = $fileNewVersion['newVersionNumber'];
            $newVersionDvKey    = $fileNewVersion['newVersionDvKey'];
            $nextToken          = $fileNewVersion['nextToken'];

            /** Generate the upload 'policy' document in JSON */
            $policyExpiration   = date('Y-m-d\TH:i:s.Z\Z', (time()+43200));

            $uploadPolicy       = '{
                "expiration": "' . $policyExpiration . '",
                "conditions": [
                    {"bucket": "' . $this->s3bucketName . '"},
                    ["starts-with", "$key", "' . $newVersionDvKey . '/"],
                    {"acl": "public-read"}
                ]
            }';

            /** Encode the Policy */
            $uploadPolicyBase64 = base64_encode($uploadPolicy);

            /** Sign and encrypt the policy using the relevant AWS Secret Key */
            $uploadPolicyHash   = Helpers::hmacsha1($this->s3UserKey, $uploadPolicyBase64);
            $uploadPolicySigned = Helpers::hex2b64($uploadPolicyHash);

            /** Turn the completed, signed Policy into JSON and return it */
            $uploadPolicyToken = array(
                'policy'                => $uploadPolicyBase64,
                'signature'             => $uploadPolicySigned,
                'awsaccesskeyid'        => $this->s3UserID,
                'dvKey'                 => $newVersionDvKey,
                'dvBearerToken'         => $nextToken,
                'expires'               => $policyExpiration,
                'note'                  => 'a field called \'key\' with \'' . $newVersionDvKey . '/NAME_OF_FILE\' should be included with the upload form submission'
            );

            /** Log */
            // Helpers::logEvent('S3 POST requested for ' . $newVersionDvKey . ' version ' . $newVersionNo);

            return response()->json($uploadPolicyToken);

        } catch (\Exception $ex) {

            return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Creates a new version of an existing File
    * returns the DVF-XXX-XXX-00 version dvKey and the next dvBearerToken
    *
    */
    private static function createFileVersion(string $dvKey, string $fileToken, string $filename, string $apiProductKey, string $tags, string $description): array {

        try {

            $fileOrig = File::where(array(
                'docvaultkey' => $dvKey, 'next_token' => $fileToken, 'deleted' => 0
            ))->first();

            if (!$fileOrig) {
                throw new \Exception('dvKey and dvBearerToken do not match');
            }

            /** Calculate next version number and new version dvKey */
            $nextVersion        = ($fileOrig->highest_version+1);
            $newVersionDvKey    = $dvKey . '-' . $nextVersion;

            /** Create new File object for new version record */
            $file = new File;

            $file->docvaultkey      = $newVersionDvKey;
            $file->userid           = $fileOrig->userid;
            $file->application      = $fileOrig->application;
            $file->api_productkey   = $apiProductKey;
            $file->filename         = $filename;
            $file->tags             = $tags;
            $file->description      = $description;
            $file->version_no       = $nextVersion;

            $file->save();

            /** Reset the next_token in the master File with a new token */
            $nextToken = bin2hex(random_bytes(64));
            $fileOrig->next_token      = $nextToken;

            /** Update the highest version in the master File */
            $fileOrig->highest_version  = $nextVersion;
            $fileOrig->save();

            return array(
                'newVersionNumber'      => $nextVersion,
                'newVersionDvKey'       => $newVersionDvKey,
                'nextToken'             => $nextToken
            );

        } catch (\Exception $ex) {

            return array(
                'error'     => $ex->getMessage()
            );

        }

    }


    /**
    *
    * Creates a new master (v1) File record
    *
    */
    private function createFileRecord(array $newFileObj): array {

        try {

            $newFileRef         = Helpers::generateFileKey();
            $newFileBearerToken = bin2hex(random_bytes(64));

            $file = new File;

            $file->docvaultkey  = $newFileRef;
            $file->version_no   = 1;
            $file->next_token   = $newFileBearerToken;

            if (isset($newFileObj['userid'])) {
                $file->userId           = $newFileObj['userid'];
            }

            if (isset($newFileObj['tags'])) {
                $file->tags             = $newFileObj['tags'];
            }

            if (isset($newFileObj['application'])) {
                $file->application      = $newFileObj['application'];
            }

            if (isset($newFileObj['api_productkey'])) {
                $file->api_productkey   = $newFileObj['api_productkey'];
            }

            if (isset($newFileObj['deferred'])) {
                $file->deferred         = $newFileObj['deferred'];
            }

            if (isset($newFileObj['deferred_key'])) {
                $file->deferred_key     = $newFileObj['deferred_key'];
            }

            if (isset($newFileObj['to_autotag'])) {
                $file->to_autotag       = $newFileObj['to_autotag'];
            }

            $file->save();

            return array(
                'newFileRef'            => $newFileRef,
                'newFileBearerToken'    => $newFileBearerToken
            );

        } catch (\Exception $ex) {

            return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
            ), 400);

        }

    }


}

?>
