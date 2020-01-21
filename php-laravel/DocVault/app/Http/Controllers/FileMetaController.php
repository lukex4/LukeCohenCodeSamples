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
use App\Meta;

/* Et al. */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

class FileMetaController extends BaseController {


    /**
    *
    * Lists all available metaKeys for the given File (dvKey)
    *
    */
    public function list(string $dvKey = NULL) {

        try {

            if (!$dvKey) {
                throw new \Exception('Please provide a valid dvKey');
            }

            $meta = Meta::where('dvkey', $dvKey)->get();

            foreach($meta as $m => $x) {
                unset($meta[$m]['id']);
                unset($meta[$m]['added_timestamp']);
            }

            return response()->json($meta);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Retrieves the metaData for a given dvKey+metaKey combination
    *
    */
    public function get(string $dvKey = NULL, string $metaKey = NULL) {

        try {

            if (!$dvKey || !$metaKey) {
                throw new \Exception('Please provide a valid dvKey and metaKey');
            }

            $meta = Meta::where(array('dvkey' => $dvKey, 'metakey' => $metaKey))->first();

            if ($meta) {
                unset($meta['id']);
                unset($meta['added_timestamp']);
            }

            return response()->json(array(
                'metadata'  => $meta
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
    * Sets the metaData for a given dvKey+metaKey combination
    *
    * NOTE This will overwrite any existing metaData for this dvKey+metaKey combination
    *
    */
    public function set(Request $request) {

        try {

            $req        = $request->all();

            $dvKey      = $req['dvKey'] ?? NULL;
            $metaKey    = $req['metaKey'] ?? NULL;
            $newData    = $req['newMetaData'] ?? NULL;

            if (!$dvKey || !$metaKey || !$newData) {
                throw new \Exception('Please provide a valid dvKey, metaKey, and newMetaData');
            }

            if (!Helpers::isValidMetaKey($metaKey)) {
                throw new \Exception('Invalid metaKey');
            }

            $meta = Meta::where(array('dvkey' => $dvKey, 'metakey' => $metaKey))->first();

            /* If metaKey already exists for this dvKey, update existing record */
            if ($meta) {

                $meta->metadata = $newData;
                $meta->save();

            } else {

                /* Create new meta record where one doesn't already exist */
                $newMeta = new Meta;

                $newMeta->dvkey     = $dvKey;
                $newMeta->metakey   = $metaKey;
                $newMeta->metadata  = $newData;

                $newMeta->save();

            }

            return response()->json(array(
                'response'      => 'Metadata created/updated for ' . $dvKey . ':' . $metaKey
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
    * Deletes the entire meta record for the dvKey+metaKey combination
    *
    */
    public function delete(Request $request) {

        try {

            $req        = $request->all();

            $dvKey      = $req['dvKey'] ?? NULL;
            $metaKey    = $req['metaKey'] ?? NULL;

            if (!$dvKey || !$metaKey) {
                throw new \Exception('Please provide a valid dvKey, metaKey, and newMetaData');
            }

            if (!Helpers::isValidMetaKey($metaKey)) {
                throw new \Exception('Invalid metaKey');
            }

            $meta = Meta::where(array('dvkey' => $dvKey, 'metakey' => $metaKey))->first();

            if (!$meta) {
                throw new \Exception('Meta does not exist');
            }

            $meta->delete();

            return response()->json(array(
                'response'      => 'Metadata deleted for ' . $dvKey . ':' . $metaKey
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
    * Lists all metaData in the system (DEVELOPMENT ONLY)
    *
    */
    public function listAll() {

        try {

            $meta = Meta::all();

            return response()->json($meta);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


}

?>
