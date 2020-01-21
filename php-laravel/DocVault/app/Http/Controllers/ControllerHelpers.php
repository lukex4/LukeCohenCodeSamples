<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;
use App\Http\Controllers\DocVault as DocVault;

/* DocVault Models */
use App\File;
use App\Path;
use App\FilePath;
use App\EventLog;

class ControllerHelpers extends BaseController {


    /**
    *
    * Records a logging event
    *
    * Event types:
    *
    */
    public static function logEvent(string $eventDescription): bool {

        try {

            $newLogEvent = new EventLog;
            $newLogEvent->event = $eventDescription;
            $newLogEvent->save();

            \Log::info($eventDescription);

            return true;

        } catch (\Exception $ex) {

        }

    }


    /**
    *
    * Fetches and returns log entries
    *
    */
    public static function fetchLogEntries(int $noOfEntries) {

        try {

            $newEventLog = new EventLog;
            dd($newEventLog);

            $entries = EventLog::all()->take($noOfEntries);

            return response()->json(array(
                'logentries'    => $entries
            ));

        } catch (\Exception $ex) {

        }

    }


    public static function apiProductKeyHeader(Request $request) {

        if (isset($request->headers->all()['x-api-product-key'])) {
            return $request->headers->all()['x-api-product-key'][0];
        } else {
            return false;
        }

    }


    /**
    *
    * Checks if key is a valid metaKey
    *
    */
    public static function isValidMetaKey(string $metaKey): bool {

        if (!$metaKey) {
            return FALSE;
        }

        if (strlen($metaKey) > 256) {
            return FALSE;
        }

        $allowedChars = array('-', '_');

        if(!ctype_alnum(str_replace($allowedChars, '', $metaKey))) {
            return FALSE;
        } else {
            return TRUE;
        }

    }


    /**
    *
    * Checks if a given file exists, by dvKey
    *
    */
    public static function fileExists($fileDvKey): bool {

        try {

            if (!File::where(array('docvaultkey' => $fileDvKey, 'deleted' => 0))->first()) {
                return false;
            } else {
                return true;
            }

        } catch (\Exception $ex) {

        }

    }


    /**
    *
    * Checks if a Path exists
    *
    */
    public static function pathExists($pathIdent, $userId): bool {

        try {

            if (!Path::where(array('pathident' => $pathIdent, 'userid' => $userId))->first()) {
                return false;
            } else {
                return true;
            }

        } catch (\Exception $ex) {

        }

    }


    /**
    *
    * Returns the owner (userId) of a File
    *
    */
    public static function fileOwnerId($fileDvKey) {

        try {

            $file = File::where('docvaultkey', $fileDvKey)->first();

            if (!$file) {
                throw new \Exception('File does not exist');
            } else {
                return $file->userid;
            }

        } catch (\Exception $ex) {
            Helpers::dieWithException($ex);
        }

    }


    /**
    *
    * Returns the owner (userId) of a Path
    *
    */
    public static function pathOwnerId($pathIdent) {

        try {

            $path = Path::where('pathident', $pathIdent)->first();

            if (!$path) {
                throw new \Exception('Path does not exist');
            } else {
                return $path->userid;
            }

        } catch (\Exception $ex) {
            Helpers::dieWithException($ex);
        }

    }


    /**
    *
    * Check if a File is in a given Path
    *
    */
    public static function fileInPath($fileDvKey, $pathIdent): bool {

        try {

            if (!File::where(array('docvaultkey' => $fileDvKey, 'inpath' => $pathIdent))->first()) {
                return false;
            } else {
                return true;
            }

        } catch (\Exception $ex) {

        }

    }


    /**
    *
    * Converts incoming request JSON into PHP object/array
    *
    */
    private static function requestToObj($request) {

        try {
            return json_decode($request->getContent(), true);
        } catch(Exception $ex) {

        }

    }


    /**
    *
    * Returns a pathEdit obj given a $request
    *
    */
    public static function pathEditObject($request) {

        try {
            return self::requestToObj($request);
        } catch (Exception $ex) {

        }

    }


    /**
    *
    * Returns a fileToPath obj given a $request
    *
    */
    public static function fileToPathObject($request) {

        try {
            return self::requestToObj($request);
        } catch (Exception $ex) {

        }

    }


    /**
    *
    * Returns a pathCreate obj given a $request
    *
    */
    public static function pathCreateObject($request) {

        try {

            $obj = self::requestToObj($request);

            return array(
                'userId'        => $obj['userId'],
                'newfullpath'   => trim((string)$obj['newfullpath'])
            );

        } catch(Exception $ex) {

        }

    }


    /**
    *
    * Returns a s3Request array given a $request
    *
    */
    public static function s3Request($request) {

        try {

            $obj = self::requestToObj($request);
            return $obj;

        } catch(Exception $ex) {

        }

    }


    /**
    *
    * Returns a fromUrlBatchRequest array given a $request
    *
    */
    public static function fromUrlBatchRequest($request) {

      try {

        $obj = self::requestToObj($request);

        return $obj;

      } catch (Exception $ex) {

      }

    }


    /**
    *
    * Returns a statusUpdatePost array given a $request
    *
    */
    public static function statusUpdate($request) {

        try {

            $obj = self::requestToObj($request);

            $newObj = array(
                'ingress'     => (string)$obj['ingress'] ?? '',
                'dvkey'       => (string)$obj['dvkey'] ?? '',
                'filename'    => (string)$obj['filename'] ?? '',
                'mimetype'    => (string)$obj['mimetype'] ?? '',
                'status'      => (string)$obj['status'] ?? ''
            );

            dd($newObj);

            return $newObj;

        } catch (Exception $ex) {

        }

    }


    /**
    *
    * Returns a captureFromUrlRequest array given a $request
    *
    */
    public static function fromUrlRequest($request) {

        try {

            $obj = self::requestToObj($request);

            $newObj = array(
                'toUserId'      => (string)$obj['toUserId'],
                'fileUrl'       => (string)$obj['fileUrl'],
                'tags'          => (string)$obj['tags'] ?? ''
            );

            return $newObj;

        } catch (Exception $ex) {

        }

    }

    /**
    *
    * Returns a tagsUpdate array given a $request
    *
    */
    public static function tagsUpdateObject($request) {

        try {

            $obj = self::requestToObj($request);

            $newObj = array(
                'dvKey'     => (string)$obj['dvKey'],
                'tags'      => (string)$obj['tags']
            );

            return $newObj;

        } catch (Exception $ex) {

        }

    }


    /**
    *
    * Returns a new Path ident
    *
    */
    public static function generatePathIdent($pathName, $userId) {

        $rand = bin2hex(random_bytes(8));

        $toCrc = "uid:" . $userId . ",pathName:" . $pathName . substr($rand, -8);
        $crcSignature = hash('crc32', $toCrc);

        $ident = "dvp-" . $crcSignature . "-" . substr($rand, 0, 8);
        $ident = strtoupper($ident);

        return $ident;

    }


    /**
    *
    * Returns a new dv file key
    *
    */
    public static function generateFileKey() {

        /**
        *
        * To create collision-proof file keys, we generate a cryptographically secure string using random_bytes, and a CRC hash of the current Unix time.
        *
        * The parts are then joined as follows:
        * "dv-" - first 6 chars of random_bytes - second 6 chars of random_bytes - crc hash of current unix time.
        *
        * An example of the key output can be seen by calling API_BASE/file/keygen
        *
        */
        $partLength = 6;

        $rand = bin2hex(random_bytes(($partLength*2)));

        $part1 = substr($rand, 0, $partLength);
        $part2 = substr($rand, $partLength, $partLength);

        $timeHash = hash('crc32', time());

        $key = "dvf-" . $part1 . "-" . $part2 . "-" . $timeHash;
        $key = strtoupper($key);

        return $key;

    }


    /**
    *
    * Check if a Path is valid
    *
    */
    public static function isValidPath($pathToCheck) {

        /** Assume the path is valid, and let the checks change that assumption */
        $pathValid = TRUE;

        /** Break up the path into component parts */
        $pathParts = explode('/', $pathToCheck);


        /**
        *
        * A valid path must start with a /
        *
        */
        if (!substr($pathToCheck, 0, 1) == '/') {
            $pathValid = FALSE;
        }


        /**
        *
        * A valid Path can only contain a-z, 0-9, _, - and / as a delimiter
        *
        */
        $allowedChars = array('-', '_', '/');

        if(!ctype_alnum(str_replace($allowedChars, '', $pathToCheck))) {
            $pathValid = FALSE;
        }


        /**
        *
        * Check the encoding on each part of the Path
        * - Only UTF-8 characters are permitted
        *
        */
        foreach($pathParts as $part) {
            if (mb_detect_encoding($part, 'UTF-8', TRUE) === FALSE) {
                $pathValid = FALSE;
            }
        }


        /**
        *
        * Check the length of each part of the Path
        * - Each part can be up to 256 characters long
        *
        */
        foreach ($pathParts as $part) {
            if (strlen($part)>256) {
                $pathValid = FALSE;
            }
        }


        /** Finished */
        return $pathValid;

    }


    /**
    *
    * Crypto functions for signing things for AWS
    *
    */
    public static function hmacsha1($key, $data) {

        $blocksize = 64;
        $hashfunc = 'sha1';
        if(strlen($key) > $blocksize)
            $key = pack('H*', $hashfunc($key));
        $key = str_pad($key, $blocksize, chr(0x00));
        $ipad = str_repeat(chr(0x36), $blocksize);
        $opad = str_repeat(chr(0x5c), $blocksize);
        $hmac = pack('H*', $hashfunc(($key ^ $opad).pack('H*', $hashfunc(($key ^ $ipad).$data))));
        return bin2hex($hmac);

    }

    public static function hex2b64($str) {

        $raw = '';
        for($i=0; $i < strlen($str); $i+=2) {
            $raw .= chr(hexdec(substr($str, $i, 2)));
        }
        return base64_encode($raw);

    }


    /**
    *
    * Returns a JSON object with CORS-friendly HTTP headers
    *
    */
    public static function corsJsonReturn($array) {

        return response()->json($array)->withHeaders([
            'Access-Control-Allow-Origin'   => '*',
            'Access-Control-Allow-Methods'  => '*',
            'Access-Control-Allow-Headers'  => 'Accept, Content-Type'
        ]);

    }


    /**
    *
    * Returns a simple JSON block with one key ('response') with a string message
    *
    */
    public static function dieWithMessage($messageText) {

        $responseJson = array(
            'response'      => $messageText
        );

        return response()->json($responseJson);

    }

    /**
    *
    * Ends the execution and returns a well-formed JSON object with an error
    *
    */
    public static function dieWithException($exception) {
        // dd($exception->getMessage());

        // return $exception->getMessage();

        return response()->json($responseJson = array(
            'requestStatus'     => 'Request failed',
            'reason'            => $exception->getMessage()
        ),400)->send();

    }

}
