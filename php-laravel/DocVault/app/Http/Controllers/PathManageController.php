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

/* Et al. */
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleSoftwareIO\QrCode\QrCodeServiceProvider;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleSoftwareIO\QrCode\BaconQrCodeGenerator;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

class PathManageController extends BaseController {


    /**
    *
    * Returns all Paths in the system   NOT FOR PRODUCTION
    *
    */
    public function pathsAll() {
        return response()->json(Path::all());
    }


    /**
    *
    * Sets/updates the description of a Path
    *
    */
    public function setPathDescription(Request $request) {

        try {

            $req = $request->all();

            $pathIdent  = $req['pathIdent'];
            $pathDesc   = $req['newDescription'];

            $path = Path::where('pathident', $pathIdent)->first();

            if (!$path) {
                throw new \Exception('Path not found');
            }

            $path->description = $pathDesc;
            $path->save();

            return response()->json(array(
                'response'  => 'update successful'
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
    * Sets/updates the tags of a Path
    *
    */
    public function setPathTags(Request $request) {

        try {

            $req = $request->all();

            $pathIdent  = $req['pathIdent'];
            $pathTags   = $req['newTags'];

            $path = Path::where('pathident', $pathIdent)->first();

            if (!$path) {
                throw new \Exception('Path not found');
            }

            $path->tags = $pathTags;
            $path->save();

            return response()->json(array(
                'response'  => 'update successful'
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
    * Creates a new Path (pseudo-folder)
    *
    */
    public function pathCreate(Request $request) {

        try {

            $pathObj = Helpers::pathCreateObject($request);

            /** Remove a trailing slash if one has been added to the new path */
            $pathObj['newfullpath'] = preg_replace('{/$}', '', $pathObj['newfullpath']);

            /** Check the validity of the proposed new path */
            if (Helpers::isValidPath($pathObj['newfullpath']) === FALSE) {
                throw new \Exception('Invalid Path declaration. A Path must be UTF-8 encoded, each component part can be up to 256 characters long, and each component part can only contain a-z, 0-9, and _ and - characters');
            }

            /** Check if this path already exists for this user and exit if it does */
            $path = Path::where(array('userid' => $pathObj['userId'], 'fullpath' => $pathObj['newfullpath']))->first();

            if ($path) {
                throw new \Exception('Path already exists');
            }

            /** Recursively create the new path */
            $pathParts = explode('/', $pathObj['newfullpath']);
            $pathFolderName = $pathParts[count($pathParts)-1];

            /** The recursive bit - create a path for every part of the path provided */
            if (count($pathParts)==1) {

                /** This is a top-level folder, so no need for recursive creation */
                $newPath = new Path;

                $newPath->pathident     = Helpers::generatePathIdent($pathObj['newfullpath'], $pathObj['userId']);
                $newPath->foldername    = $pathFolderName;
                $newPath->fullpath      = $pathObj['newfullpath'];
                $newPath->userid        = $pathObj['userId'];

                $newPath->save();

            } else {

                /** Creating recursive folder, so create a full Path for each component part */
                $thisPathParts = $pathParts;

                foreach($pathParts as $pathPart) {

                    array_pop($thisPathParts);
                    $thisPath = implode('/', $thisPathParts);
                    $thisPath = preg_replace('{/$}', '', $thisPath);

                    $thisPathFolderParts    = explode('/', $thisPath);
                    $thisPathFolderName     = $thisPathFolderParts[count($thisPathFolderParts)-1];

                    /** If this part already exists as a Path, move on */
                    if (Path::where(array('userid' => $pathObj['userId'], 'fullpath' => $thisPath))->first()) {
                        continue;
                    }

                    /** Create a new Path for this part */
                    if (strlen($thisPath)>0) {

                        $newPath = new Path;

                        $newPath->pathident     = Helpers::generatePathIdent($thisPath, $pathObj['userId']);
                        $newPath->foldername    = $thisPathFolderName;
                        $newPath->fullpath      = $thisPath;
                        $newPath->userid        = $pathObj['userId'];

                        $newPath->save();

                    }

                }

            }

            $newPath = new Path;

            $newPath->pathident     = Helpers::generatePathIdent($pathObj['newfullpath'], $pathObj['userId']);
            $newPath->foldername    = $pathFolderName;
            $newPath->fullpath      = $pathObj['newfullpath'];
            $newPath->userid        = $pathObj['userId'];

            $newPath->save();


            /** Log */
            Helpers::logEvent('Path ' . $pathObj['newfullpath'] . ' created for userId ' . $pathObj['userId']);


            /** Respond */
            return response()->json(array(
                'response'  => 'path successfully created',
                'details'   => 'new path: ' . $pathObj['newfullpath']
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
    * Move a Path
    *
    */
    public function pathMove(Request $request) {

        try {

            $req = $request->all();

            /** Check all required inputs */
            if (!isset($req['pathIdent'])) {
                throw new \Exception('Please provide a valid pathIdent');
            } else {
                $pathIdent      = $req['pathIdent'];
            }

            if (!isset($req['destPathIdent'])) {
                throw new \Exception('Please provide a valid destPathIdent');
            } else {
                $destPathIdent  = $req['destPathIdent'];
            }

            /** Is this Path moving to 'root' of Path tree? */
            $toRoot = FALSE;

            if ($destPathIdent == '~') {
                $toRoot = TRUE;
            }

            if ($toRoot === FALSE) {

                /** Extract owner IDs of Path and destPath */
                $pathOwnerId = Helpers::pathOwnerId($pathIdent);
                $destOwnerId = Helpers::pathOwnerId($destPathIdent);

                /** Check not moving Path into itself (!!) */
                if ($pathIdent == $destPathIdent) {
                    throw new \Exception('You can\'t move a Path into itself');
                }

                /** Check owner of pathIdent matches owner of destPathIdent */
                if ($pathOwnerId <> $destOwnerId) {
                    throw new \Exception('The destination Path has a different owner to the Path');
                }

            }

            /** Extract foldername of Path */
            $path               = Path::where(array('pathident' => $pathIdent))->first();
            $pathFolderName     = $path->foldername;
            $pathFull           = $path->fullpath;

            if ($toRoot === TRUE) {
                $newFullPath    = '/' . $pathFolderName;
            } else {
                /** Extract fullpath of destPath and generate new fullpath for Path */
                $destPath       = Path::where(array('pathident' => $destPathIdent))->first();
                $destFullPath   = $destPath->fullpath;
                $newFullPath    = $destFullPath . '/' . $pathFolderName;
            }

            /** Save updated fullpath for Path */
            $path->fullpath = $newFullPath;
            $path->save();

            /** Update any other Paths containing this Path */
            $oldPaths = Path::where(array('fullpath' => $pathFull))->get();

            $oldPaths = Path::where('fullpath', 'like', $pathFull . '/%')->get();

            foreach ($oldPaths as $oldPath) {
                $oldPath->fullpath = str_replace($pathFull, $newFullPath, $oldPath->fullpath);
                $oldPath->save();
            }

            /** Done */
            return response()->json(array(
                'response'          => 'Path moved',
                'new fullpath'      => $newFullPath
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
    * Rename a Path
    *
    */
    public function pathRename(Request $request) {

        try {

            $req = $request->all();

            /** Check all required inputs */
            if (!isset($req['pathIdent'])) {
                throw new \Exception('Please provide a valid pathIdent');
            } else {
                $pathIdent      = $req['pathIdent'];
            }

            if (!isset($req['pathNewName'])) {
                throw new \Exception('Please provide a valid pathNewName');
            } else {
                $pathNewName    = $req['pathNewName'];
            }

            /** Check new Path name is valid */
            if (Helpers::isValid('/' . $pathNewName)) {
                throw new \Exception('Please provide a valid pathNewName');
            }

            /** Load Path model */
            $path = Path::where(array('pathident' => $pathIdent))->first();

            $path->foldername   = $pathNewName;
            $path->save();

            /** Done */
            return response()->json(array(
                'response'          => 'Path renamed',
                'Path new name'     => $pathNewName
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
    * Deletes a given Path
    *
    */
    public function pathDelete(Request $request) {

        try {

            $req = $request->all();

            /** Check all required inputs */
            if (!isset($req['pathIdent'])) {
                throw new \Exception('Please provide a valid pathIdent');
            } else {
                $pathIdent      = $req['pathIdent'];
            }

            /** Delete Path record */
            $path = Path::where(array('pathident' => $pathIdent))->first();
            $path->delete();

            /** Remove 'inpath' from any Files in this Path */
            File::where('inpath', '=', $pathIdent)->update(['inpath' => '']);

            /**
            * Delete any Files in this Path (?)
            **/

            /** Done */
            return response()->json(array(
                'response'          => 'Path deleted'
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
    * Edits a given Path
    *
    */
    public function pathEdit(Request $request) {

        try {

            // $editObj = Helpers::pathEditObject($request);

            $editObj = $request->all();

            /** Does the Path exist? */
            if (Helpers::pathExists($editObj['pathident'], $editObj['userid']) === FALSE) {
                throw new \Exception('No Path exists for the pathident provided');
            }

            /** Is new path valid? */
            if (Helpers::isValidPath($editObj['newpath']) === FALSE) {
                throw new \Exception('newpath is not a valid Path declaration');
            }

            /** Check new target Path doesn't already exist */
            $path = Path::where(array('fullpath' => $editObj['newpath'], 'userid' => $editObj['userid']))->first();

            if ($path) {
                throw new \Exception('New Path already exists');
            }

            /** Load the existing Path */
            $pathExisting = Path::where('pathident', $editObj['pathident'])->first();

            if (!$pathExisting) {
                throw new \Exception('Error loading existing Path');
            }

            $editObj['oldpath'] = $pathExisting->fullpath;

            /** Check the old Path and new Path have the same number of component parts */
            $oldPathParts = explode('/', $editObj['oldpath']);
            $newPathParts = explode('/', $editObj['newpath']);

            if (count($oldPathParts) <> count($newPathParts)) {
                throw new \Exception('newpath and oldpath have different numbers of parts - you can only edit the last part of your provided oldpath');
            }

            /** Strip trailing slashes from any of the provided Paths */
            $editObj['oldpath'] = preg_replace('/[^\w]/', '', $editObj['oldpath']);
            $editObj['newpath'] = preg_replace('/[^\w]/', '', $editObj['newpath']);

            /** Extract the folder name of the new Path */
            $newPathFolderName = $newPathParts[count($newPathParts)-1];

            /** Add leading / to newPathFolderName if the new destination has a single Path part */
            if (substr($newPathFolderName, 1) !== '/') {
                $newPathFolderName = '/' . $newPathFolderName;
            }

            /** Update the Path record */
            $pathExisting->fullpath     = $newPathFolderName;
            $pathExisting->foldername   = $newPathFolderName;

            $pathExisting->save();

            /** Update any other Paths containing this Path */
            $oldPaths = Path::where(array('fullpath' => $editObj['oldpath'], 'userid' => $editObj['userid']))->get();

            foreach ($oldPaths as $oldPath) {
                $oldPath->fullpath = str_replace($editObj['oldpath'], $editObj['newpath'], $oldPath->fullpath);
                $oldPath->save();
            }

            /** Return */
            return response()->json(array(
                'response'  => 'Path updated'
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
    * Delete a Path
    *
    */
    public function pathDeletePart(Request $request) {

        try {

          $req        = $request->all();
          $pathIdent  = $req['pathident'];

          /** Fetch the Path */


        } catch (\Exception $ex) {

          return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
          ), 400);

        }

    }


    /**
    *
    * Lists all files in a given path
    * - Path is UTF-8 Base64 encoded for URL compatibility
    *
    */
    public function pathListFiles(int $userId, $pathIdent = NULL) {

        try {

            /** Check if this Path exists for this User */
            if ($pathIdent) {
                $path = Path::where(array('pathident' => $pathIdent, 'userid' => $userId))->first();
            }

            if (!$userId) {
                throw new \Exception('Invalid userId');
            }

            /** Load Files attached to this Path */
            if (!$pathIdent) {
                $pathIdent = '';
            }

            $filesInPath = File::where(array(
                'inpath'    => $pathIdent,
                'deleted'   => 0,
                'userid'    => $userId
            ))->get();

            /** Extract this Path string */
            if (isset($path)) {
                $pathString = $path->fullpath;
            } else {
                $pathString = '';
            }

            /** Create the array to present the list of Files */
            $files = array();

            foreach($filesInPath as $aFile) {

                array_push($files, array(
                    'dvKey'             => (string)$aFile->docvaultkey,
                    'filename'          => (string)$aFile->filename,
                    'mimetype'          => (string)$aFile->mimetype,
                    'ingress_status'    => (string)$aFile->ingress_status,
                    'tags'              => (string)$aFile->tags,
                    'description'       => (string)$aFile->description,
                    'userid'            => $aFile->userid,
                    'created_at'        => (string)$aFile->created_at,
                    'updated_at'        => (string)$aFile->updated_at,
                    'inpath'            => (string)$aFile->inpath
                ));

            }

            /** Query Paths below this point */
            $paths = array();

            $subPaths = Path::where('userid', $userId)
                ->where('fullpath', 'like', $pathString . '/%')
                ->where('fullpath', 'NOT LIKE', $pathString . '/%/%')
                ->where('fullpath', 'NOT LIKE', '/%' . $pathString . '%/%')
                ->get();

            foreach($subPaths as $subPath) {
                array_push($paths, $subPath);
            }

            /** Respond */
            return response()->json(array(
                'path'      => $pathString,
                'files'     => $files,
                'subpaths'  => $paths
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
    * Flags given path as quicklink
    *
    */
    public function quicklinkSet(String $pathIdent) {

        try {

            if (!isset($pathIdent)) {
                throw new \Exception('Please provide a valid pathIdent');
            }

            $path = Path::where('pathident', $pathIdent)->first();

            if (!$path) {
                throw new \Exception('Path not found');
            }

            $path->is_quicklink = TRUE;
            $path->save();

            return response()->json(array(
                'response'  => 'Path/quicklink update successful'
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
    * Unflags given path as quicklink
    *
    */
    public function quicklinkUnset(String $pathIdent) {

        try {

            if (!isset($pathIdent)) {
                throw new \Exception('Please provide a valid pathIdent');
            }

            $path = Path::where('pathident', $pathIdent)->first();

            if (!$path) {
                throw new \Exception('Path not found');
            }

            $path->is_quicklink = FALSE;
            $path->save();

            return response()->json(array(
                'response'  => 'Path/quicklink update successful'
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
    * Lists all of a user's quicklinks
    *
    */
    public function quicklinksList(String $userId) {

        try {

            if (!isset($userId)) {
                throw new \Exception('Please provide a valid userId');
            }

            $quicklinks = Path::where('userid', $userId)
                ->where('is_quicklink', TRUE)
                ->get();

            if (!$quicklinks) {
                throw new \Exception('No quicklinks found');
            }

            return response()->json(array(
                'quicklinks'    => $quicklinks
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
    * Lists all paths for a given user with an optional base point
    *
    */
    public function pathsList(int $userId, string $basePoint) {

        try {

            if (!isset($userId) || $userId == 0) {
                throw new \Exception('Invalid userId');
            }

            /** Get 'fullpath' of basePoint */
            $basePointPath = '';

            if ($basePoint) {

                $basepointP = Path::where(array(
                    'pathident'     => $basePoint
                ))->first();

                if ($basepointP) {
                    $basePointPath = $basepointP['fullpath'];
                }

            }

            /** Fetch Paths for the given user and provided base point */
            if (strlen($basePointPath)>0) {

                $filePaths = Path::where('userid', $userId)
                    ->where('fullpath', 'like', $basePointPath . '/%')
                    ->orderBy('fullpath', 'asc')
                    ->get();

            } else {

                $filePaths = Path::where(array(
                    'userid'    => $userId
                ))->orderBy('fullpath', 'asc')->get();

            }

            /** Create the clean Paths array */
            $thePaths = array();

            foreach($filePaths as $path) {
                array_push($thePaths, array(
                    'pathident'         => $path['pathident'],
                    'foldername'        => $path['foldername'],
                    'fullpath'          => $path['fullpath'],
                    'description'       => $path['description'],
                    'tags'              => $path['tags'],
                    'is_quicklink'      => $path['is_quicklink'],
                    'created'           => date('Y-m-d H:i:s', strtotime($path['created_at']))
                ));
            }

            /** Return */
            return response()->json(array(
                'paths'     => $thePaths
            ));

        } catch (\Exception $ex) {

          return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
          ), 400);

        }

    }


}

?>
