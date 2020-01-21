<?php

namespace App\Http\Controllers;

/* Laravel-Lumen */
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

/* DocVault Controllers */
use App\Http\Controllers\ControllerHelpers as Helpers;
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

class FileManageController extends BaseController {

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

    }


    public static function logEntries($noOfEntries) {

        try {

            Helpers::fetchLogEntries($noOfEntries);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Lists all files in the database  NOT FOR PRODUCTION
    *
    */
    public function filesAll() {
        return response()->json(File::all());
    }


    /** Generate QR code for a given File */
    public static function fileGenQR(String $fileKey) {

        try {

            $file = File::where(array('docvaultkey' => $fileKey, 'deleted' => 0))->first();

            if (!$file) {
                \Log::error('Request for QR code failed :' . $fileKey);
                throw new \Exception('File not found');
            }

            $QR         = new BaconQrCodeGenerator();

            $newCode    = $QR->size(250)->generate($fileKey);

            $response = new Response(
                'Content',
                Response::HTTP_OK,
                array('content-type' => 'image/svg+xml')
            );

            $response->headers->set('Content-Type', 'image/svg+xml');
            $response->setContent($newCode);
            $response->send();

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /** Lists all available versions of a File */
    public function listFileVersions(String $dvKey) {

        try {

            /** Check dvKey valid and File exists for key */
            if (!$dvKey || strlen($dvKey)==0) {
                throw new \Exception('Please provide a valid dvKey');
            }

            $file = File::where(array('docvaultkey' => $dvKey, 'deleted' => 0))->first();

            if (!$file) {
                throw new \Exception('No file exists with the provided dvKey');
            }

            /** Query File table for versions of this File */
            $fileVersions = File::where('deleted', 0)
                                ->where('docvaultkey', 'like', '' . $dvKey . '-%')
                                ->get();

            if ($fileVersions->isEmpty()) {

                /** If there are no versions apart from v1, notify the caller */
                return response()->json(array(
                    'dvKey'             => $dvKey,
                    'highest_version'   => 1
                ));

            }

            /** Create array of versions with just the information needed */
            $versionRefs = array();

            foreach ($fileVersions as $v) {

                array_push($versionRefs, array(
                    'version_no'        => $v->version_no,
                    'filename'          => $v->filename,
                    'added_timestamp'   => $v->added_timestamp
                ));

            }

            $versions = array(
                'dvKey'             => $dvKey,
                'highest_version'   => $file->highest_version,
                'versions'          => $versionRefs
            );

            return response()->json($versions);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Returns a File object for a given File DocVault key
    *
    */
    public function fileByKey($fileKey, $versionNo = NULL) {

        try {

            if (!isset($fileKey) || strlen($fileKey) == 0) {
                throw new \Exception('Please provide a valid dvKey');
            }

            /** Check if a version number has been specified */
            if ($versionNo && is_numeric($versionNo)) {
                $fileKey = $fileKey . '-' . $versionNo;
            }

            $file = File::where(array('docvaultkey' => $fileKey, 'deleted' => 0))->first();

            if (!$file) {
                \Log::error('Request for key failed : ' . $fileKey);
                throw new \Exception('File not found');
            } else {

                /** Remove superfluous fields from the File */
                unset($file['id']);
                unset($file['deleted']);
                unset($file['added_timestamp']);
                unset($file['api_productkey']);
                unset($file['deferred']);
                unset($file['deferred_key']);
                unset($file['next_token']);

                $file['dvKey'] = $file['docvaultkey'];
                unset($file['docvaultkey']);

                return response()->json(array(
                    'file'  => $file
                ));

            }

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Returns a list of files for a given userId
    *
    */
    public function filesByUserId(int $userId, string $tag = NULL) {

        try {

            if (!isset($userId) || $userId == 0) {
                throw new \Exception('Please provide a valid userId');
            }

            if (!$tag) {
              $userFiles = File::where(array('userid' => $userId, 'deleted' => 0))->get();
            } else {
              $userFiles = File::where(array('userid' => $userId, 'deleted' => 0))
                ->where('tags', 'like', '%' . $tag . '%')
                ->get();
            }

            foreach($userFiles as $file => $x) {
                unset($userFiles[$file]['id']);
                unset($userFiles[$file]['added_timestamp']);
                unset($userFiles[$file]['deleted']);
                unset($userFiles[$file]['api_productkey']);
                unset($userFiles[$file]['deferred']);
                unset($userFiles[$file]['deferred_key']);
                unset($userFiles[$file]['next_token']);

                $userFiles[$file]['dvKey'] = $userFiles[$file]['docvaultkey'];
                unset($userFiles[$file]['docvaultkey']);

            }

            return response()->json($userFiles);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Serves a Micro File
    *
    */
    public function microServe(string $dvKey) {

        try {

            if (!isset($dvKey)) {
                throw new \Exception('Please provide a valid dvKey');
            }

            $file = File::where(array('docvaultkey' => $dvKey, 'microfile' => TRUE))->first();

            if (!$file) {
                throw new \Exception('No File/Microfile found for dvKey provided');
            }

            $microfile = Microfile::where('id', $file->microfile_id)->first();

            $fileBase64 = unserialize($microfile->filebase);
            $filetype   = $file->mimetype;

            /* Set the appropriate Content-Type headers */
            header('Content-Type: ' . $filetype);

            /* Output the File contents */
            return base64_decode($fileBase64);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }



    /**
    *
    * Search for Files
    *
    */
    public function fileSearch(string $searchTerm, int $userId = NULL) {

      try {

          if (!isset($searchTerm) || strlen($searchTerm) < 3) {
              throw new \Exception('Invalid search term');
          }

          $searchTerm = trim(substr(urldecode($searchTerm), 0, 256));

          /** Search Files for matching pattern */
          if ($userId) {

              $files = File::where('userid', $userId)
                        ->where('filename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('mimetype', 'like', '%' . $searchTerm . '%')
                        ->where('deleted', 0)
                        ->get();

          } else {

              $files = File::where('filename', 'like', '%' . $searchTerm . '%')
                        ->orWhere('mimetype', 'like', '%' . $searchTerm . '%')
                        ->where('deleted', 0)
                        ->get();

          }

          /** Fetch fullpath results for Files */
          $fileResults = array();

          foreach($files as $file) {

              if (strlen($file->inpath)>0) {
                  $path = Path::where(array('pathident' => $file->inpath))->first();
                  $file->inpath_full = $path->fullpath;
              }

              if ($file->deleted == 0) {
                  array_push($fileResults, $file);
              }

          }

          /** Search Paths for matching pattern */
          if ($userId) {

              $paths = Path::where('userid', $userId)
                        ->where('foldername', 'like', '%' . $searchTerm . '%')
                        ->get();

          } else {

              $paths = Path::where('foldername', 'like', '%' . $searchTerm . '%')
                        ->get();

          }

          /** Respond */
          return response()->json(array(
              'search'  => array(
                  'query'      => $searchTerm,
                  'userId'     => $userId ?? '',
              ),
              'results' => array(
                  'files'      => $fileResults,
                  'paths'      => $paths
              )
          ));


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
    * Creates a download URL for a given file
    *
    */
    public function createReleaseUrl($fileId) {

        try {

            if (!isset($fileId) || strlen($fileId) == 0) {
                throw new \Exception('Please provide a valid dvKey');
            }

            $file = File::where(array('docvaultkey' => $fileId, 'deleted' => 0))->first();

            if (!$file) {
                \Log::error('Request for file failed :' . $fileId);
                throw new \Exception('File not found');
            }

            if (isset($file->ingress_status) && $file->ingress_status <> 'complete') {
                throw new \Exception('File not yet uploaded or upload has failed');
            }

            /** Does this File have a subsequent version (after 0)? If so, switch to the latest version */
            if ($file->highest_version > 0) {
                $fileId = $fileId . '-' . $file->highest_version;
                $file = File::where(array('docvaultkey' => $fileId, 'deleted' => 0))->first();
            }

            /** Create unique Credential and Signature to authenticate the S3 download URL */
            $s3Client = S3Client::factory([
                'credentials'   => new \Aws\Common\Credentials\Credentials(
                    $this->s3UserID, $this->s3UserKey
                ),
                'region'        => 'us-west-1',
                'version'       => 'latest'
            ]);

            $releaseUrl = (string)$s3Client->getObjectUrl(
                $this->s3bucketName, $fileId . '/' . urldecode($file->filename), '+5 minutes', ['PathStyle' => true]
            );

            /** Provide a 'virusWarn' flag in the output if this File has failed virus checks */
            if ($file->viruscheck_clean === 0) {
                $virusWarn = TRUE;
            } else {
                $virusWarn = FALSE;
            }

            $docVaultUrl = array(
                'dvKey'       => $fileId . '/' . $file->filename,
                'fileUrl'     => $releaseUrl,
                'virusWarn'   => $virusWarn
            );

            /** Log */
            Helpers::logEvent('File release requested for ' . $fileId);

            return response()->json($docVaultUrl);

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
    * Mark File as deleted
    *
    */
    public function markFileDeleted(Request $request) {

        try {

            $req        = $request->all();
            $fileKey    = $req['dvKey'];

            if (strlen($fileKey) == 0) {
                throw new \Exception('Invalid request parameters');
            }

            /** Retrieve File */
            $file = File::where('docvaultkey', $fileKey)->first();

            if (!$file) {
                throw new \Exception('File not found');
            } else {

                // At some point, clarify what authentication/permission is required to delete a specific file

                /** Mark File deleted */
                $file->deleted = 1;
                $file->save();

                /** Respond */
                return response()->json(array(
                    'response'  => 'File ' . $fileKey . ' deleted'
                ));

            }

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
    * Delete multiple Files
    *
    */
    public function deleteMultiple(Request $request) {

        try {

            $req        = $request->all();
            $fileKeys   = $req['filesToDelete'];

            if (strlen($fileKeys) == 0) {
                throw new \Exception('Invalid request parameters');
            }

            /** Create array from fileKeys */
            $filesToDelete = explode(",", $fileKeys);
            $filesToDelete = array_slice($filesToDelete, 0, 25);

            foreach($filesToDelete as $toDelete) {

                $file = File::where('docvaultkey', trim($toDelete))->first();

                $file->deleted = 1;
                $file->save();

            }

        } catch (\Exception $ex) {

          return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
          ), 400);

        }

    }


    /**
    *
    * Rename a File
    *
    */
    public function fileRename(Request $request) {

        try {

            $req = $request->all();

            $dvKey          = $req['dvKey'];
            $filenameNew    = $req['filenameNew'];

            /** Validate new filename */
            if (strlen($filenameNew)==0) {
                throw new \Exception('Please provide a value for filenameNew');
            }

            if (strlen($filenameNew)>256) {
                throw new \Exception('filenameNew exceeds the max. character limit');
            }

            if (substr($filenameNew, 0, 1)==' ') {
                throw new \Exception('filenameNew cannot begin with a SPACE');
            }

            if (substr($filenameNew, -1)==' ') {
                throw new \Exception('filenameNew cannot end with a SPACE');
            }

            if (strpos($filenameNew, '/') > 0) {
                throw new \Exception('Filename cannot contain /');
            }

            if (strpos($filenameNew, '\\') > 0) {
                throw new \Exception('Filename cannot contain \\');
            }

            if (strpos($filenameNew, '?') > 0) {
                throw new \Exception('Filename cannot contain ?');
            }

            if (strpos($filenameNew, '%') > 0) {
                throw new \Exception('Filename cannot contain %');
            }

            if (strpos($filenameNew, ':') > 0) {
                throw new \Exception('Filename cannot contain :');
            }

            if (strpos($filenameNew, '"') > 0) {
                throw new \Exception('Filename cannot contain "');
            }

            if (strpos($filenameNew, '\'') > 0) {
                throw new \Exception('Filename cannot contain \'');
            }

            if (strpos($filenameNew, '<') > 0) {
                throw new \Exception('Filename cannot contain <');
            }

            if (strpos($filenameNew, '>') > 0) {
                throw new \Exception('Filename cannot contain >');
            }

            /** Load File record */
            $file = File::where(array('docvaultkey' => $dvKey, 'deleted' => 0))->first();

            if (!$file) {
                throw new \Exception('Cannot find file for dvKey provided');
            }

            $filenameOrig = $file->filename;

            /** Create S3 client instance and load S3 attached to this File */
            $s3Client = S3Client::factory([
                'credentials'   => new \Aws\Common\Credentials\Credentials(
                    $this->s3UserID, $this->s3UserKey
                ),
                'region'        => 'us-west-1',
                'version'       => 'latest'
            ]);

            /** Copy the original object (because S3 objects can't be renamed via this SDK) */
            $sourceBucket   = $this->s3bucketName;
            $sourceKey      = $dvKey . '/' . $filenameOrig;
            $targetKey      = $dvKey . '/' . $filenameNew;

            $s3Client->copyObject(array(
                'Bucket'     => $this->s3bucketName,
                'Key'        => $targetKey,
                'CopySource' => "{$sourceBucket}/{$sourceKey}",
            ));

            /** Now we delete the old item */
            $result = $s3Client->deleteObject(array(
                'Bucket'    => $sourceBucket,
                'Key'       => $sourceKey
            ));

            /** Update the DV record */
            $file->filename = $filenameNew;
            $file->save();

            /** End */
            return response()->json(array(
                'dvKey'     => $dvKey,
                'response'  => 'filenameUpdateComplete'
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
    * Replaces a file's description with the newDescription provided in the request body
    *
    */
    public function fileSetDescription(Request $request) {

        try {

            $update = $request->all();

            /** Check input */
            if (!isset($update) || !isset($update['dvKey']) || !isset($update['newDescription'])) {
                throw new \Exception('Invalid request parameters');
            } else {
                $dvKey = $update['dvKey'];
            }

            /** Is this for a specific version? */
            $vers = '';

            if (isset($update['versionNo'])) {
                $vers = '-' . $update['versionNo'];
            }

            $file = File::where(array('docvaultkey' => $dvKey . $vers, 'deleted' => 0))->first();

            if (!$file) {
                throw new \Exception('Error retrieving file for given dvKey');
            }

            /** If there's a version later than zero, ascertain the latest number */
            $highest_version = $file->highest_version;

            if ($highest_version > 0) {
                $file = File::where(array('docvaultkey' => $dvKey . '-' . $highest_version, 'deleted' => 0))->first();
            }

            $file->description = $update['newDescription'];
            $file->save();

            /** Log */
            Helpers::logEvent('Description (' . $update['newDescription'] . ') set for ' . $dvKey);

            return response()->json(array(
                'dvkey'     => $dvKey,
                'response'  => 'descriptionUpdateComplete'
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
    * Replaces a file's tags with the tagsList provided in the request body
    *
    * Tags are stored in a comma-delimited string
    * NOTE: Comma-delimited tags will be replaced with a Tags model in v1.1
    *
    */
    public function fileSetTags(Request $request) {

        try {

            $update = $request->all();

            $dvKey = '';

            if (isset($update) && isset($update['dvKey'])) {
                $dvKey = $update['dvKey'];
            } else {
                throw new \Exception('Invalid request parameters');
            }

            /** Is this for a specific version? */
            $vers = '';

            if (isset($update['versionNo'])) {
                $vers = '-' . $update['versionNo'];
            }

            /** Get the File */
            $file = File::where(array('docvaultkey' => $dvKey . $vers, 'deleted' => 0))->first();

            if (!$file) {
                throw new \Exception('Error retrieving file for given dvKey');
            }

            /** If there's a version later than zero, ascertain the latest number */
            $highest_version = $file->highest_version;

            if ($highest_version > 0) {
                $file = File::where(array('docvaultkey' => $dvKey . '-' . $highest_version, 'deleted' => 0))->first();
            }

            $file->tags = $update['newTags'];
            $file->save();

            /** Log */
            Helpers::logEvent('Tags (' . $update['newTags'] . ') set for ' . $update['dvKey']);

            return response()->json(array(
                'dvkey'     => $dvKey,
                'response'  => 'tagUpdateComplete'
            ));

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
    * Attaches a File to a Path
    *
    * 'targetpath' is the LZFS identifier for a Path
    *
    */
    public function fileToPath(Request $request) {

        try {

            $fileToPath = Helpers::fileToPathObject($request);
            $dvKey      = $fileToPath['dvKey'] ?? null;

            /** Check if File and Path both exist */
            if (Helpers::fileExists($dvKey) === FALSE) {
                throw new \Exception('File does not exist');
            }

            if (Helpers::pathExists($fileToPath['targetpath'], $fileToPath['userId']) === FALSE) {
                throw new \Exception('Target path does not exist');
            }

            /** Check the provided userId owns this file */
            if (Helpers::fileOwnerId($dvKey) <> $fileToPath['userId']) {
                throw new \Exception('Insufficient file ownership and/or other privileges required to move the file');
            }

            /** Check this File isn't already in the target Path */
            if (Helpers::fileInPath($dvKey, $fileToPath['targetpath']) === true) {
                throw new \Exception('File is already in the target path');
            }

            /** Request is valid: join the File to the Path **/

            /** Remove any existing links between the File and other Paths */
            $file = File::where('docvaultkey', $dvKey)->first();
            $filename = $file->filename;

            /** Is there already a File with the same filename, belonging to the same User, in the destination path? If so, mark it */
            // $fileExists = File::where(array(
            //     'filename'  => $filename,
            //     'userid'    => $fileToPath['userId'],
            //     'inpath'    => $file->inpath
            // ))->first();

            // if ($fileExists) {
            //     $filename = $filename . ' copy-' . substr(bin2hex(random_bytes(4)), 4);
            // }

            /** Update File record */
            $file->filename = $filename;
            $file->inpath = $fileToPath['targetpath'];
            $file->save();

            /** Log */
            Helpers::logEvent($dvKey . ' moved to Path ' . $fileToPath['targetpath']);

            /** Respond */
            return response()->json(array(
                'response'  => 'File moved successfully'
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
    * Removes a File from a Path (if the link exists)
    *
    */
    private function filePathRemove($fileDvKey, $pathIdent) {

        try {

            $file = File::where(array('docvaultkey' => $fileDvKey, 'inpath' => $pathIdent))->first();

            if (!$file) {
                throw new \Exception('File not found in Path provided');
            }

            $file->inpath = '';
            $file->save();

            Helpers::logEvent($fileDvKey . ' removed from Path ' . $fullPath);
        } catch (\Exception $ex) {
            Helpers::dieWithException($ex);
        }

    }


}

?>
