<?php

namespace App\Http\Controllers;

/* Laravel-Lumen */
use DB;
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

/* DocVaultNaturalSearch class */
use App\Classes\DvNatural;

/* DocVaultAnalyse class */
use App\Classes\DocVaultAnalyse;

/* Et al. */
use Aws\S3\S3Client;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleSoftwareIO\QrCode\QrCodeServiceProvider;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleSoftwareIO\QrCode\BaconQrCodeGenerator;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

class NlpController extends BaseController {

    /* AWS S3 Client */
    private $s3Client;

    /** Some arrays for NLP class use */
    private $searchableDoctypes     = [];
    private $searchableDoctypeSubs  = [];
    private $searchableAttributes   = [];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {

        $this->searchableDoctypes       = ['contract', 'email', 'document'];
        $this->searchableDoctypeSubs    = ['nda', 'employment'];
        $this->searchableAttributes     = ['salary review'];

        /** S3 Bucket */
        $this->s3url                    = config('docvault.s3_url');
        $this->s3bucketName             = config('docvault.s3_bucket_name');

        /* S3 User */
        $this->s3UserID                 = config('docvault.s3_user_id');
        $this->s3UserKey                = config('docvault.s3_user_key');

        $this->s3Client         = \Aws\S3\S3Client::factory(array(
            'version'   => 'latest',
            'region'    => 'us-west-1',
            'credentials' => array(
                'key'    => env('S3_USER_ID'),
                'secret' => env('S3_USER_KEY'),
            )
        ));

    }


    public function test() {

        self::generateTags('DVF-38E400-F5C18E-DAB2149C');

        // $dvnl = new \App\Classes\DvNatural\DocVaultNaturalSearch();
        //
        // $ignoreWords            = ['title'];
        //
        // $query = 'nda with david neal about stuff and things';
        //
        // $dvnlResult             = $dvnl->Parse($query, $this->searchableDoctypes, $this->searchableDoctypeSubs, $this->searchableAttributes);
        //
        // $res = array(
        //     'documentTypes'     => $dvnlResult['DocumentTypes'],
        //     'documentSubTypes'  => $dvnlResult['DocumentSubTypes'],
        //     'possibleParties'   => $dvnlResult['Parties'],
        //     'possibleEntities'  => $dvnlResult['PossibleEntities'],
        //     'operators'         => $dvnlResult['Operators'],
        //     'purpose'           => $dvnlResult['PossiblePurpose']
        // );
        //
        // return response()->json($res);

    }


    public function buildSqlQuery(array $docTypes, array $docSubTypes, array $dates, array $attributes, array $parties, array $inTitle): string {

        $lookupParts = array();

        $query = 'SELECT * FROM documents WHERE';

        foreach($docTypes as $docType) {
            array_push($lookupParts, "(tags LIKE '%doctype:" . formatQueryWord($docType) . "%')");
        }

        foreach($docSubTypes as $docSubType) {
            array_push($lookupParts, "(tags LIKE '%doctype_sub:" . formatQueryWord($docSubType) . "%')");
        }

        $timestamps = array();

        foreach($dates as $date) {

            if (!isset($date['is_year'])) {
                array_push($timestamps, intval($date['date_timestamp_from']));
                array_push($timestamps, intval($date['date_timestamp_to']));
            }

        }

        foreach($attributes as $attribute) {
            array_push($lookupParts, "(tags LIKE '%attribute:" . formatQueryWord($attribute) . "%')");
        }

        foreach($parties as $party) {

            if (in_array($party, array('ME', 'MY', 'MYSELF'))) {
                array_push($lookupParts, "(tags LIKE '%party:creator%')");
            } else {
                array_push($lookupParts, "(tags LIKE '%party:%:" . formatQueryWord($party) . "%')");
            }

        }

        foreach($inTitle as $title) {

            if (strlen($title)>0) {
                array_push($lookupParts, "(tags LIKE '%title:%" . formatQueryWord($title) . "%')");
            }

        }

        if (count($lookupParts)>0) {

            foreach($lookupParts as $p) {
                $query .= "\n " . $p . " AND";
            }

        }

        if (substr(strtoupper($query), -4) == ' AND') {
            $query = substr($query, 0, -4);
        }

        return $query;

    }




    /**
    *
    * DocVaultAnalyse Helper Functions
    * (they help 'interpret' the results of DVA)
    *
    */
    /* Calculates the weighting score deduction for possible entities */
    private function calcScore(string $possEnt, $dvA): int {

        /* Generate weighting 'score' for each possible entity. Points start at 100, 20 are deducted for each stopword contained, 10 are deducted for each noun */
        $entityParts = explode(' ', $possEnt);

        $score = 100;

        /* If the possible entity is only one word long, reduce 20 points */
        if (count($entityParts) == 1) {
            $score = $score-20;
        }

        /* If the entity contains a legal term, reduce 10 points */
        if ($dvA->containsLegalTerm($possEnt) === TRUE) {
            $score = $score-10;
        }

        /* Is the entity just a legal term plural, or does it contain much besides? */
        if ($dvA->isLegalTerm(substr($possEnt, 0, -1)) === TRUE) {
            $score = $score-30;
        }

        /* Does the entity contain a common company affix? */
        if (count($entityParts) > 1 && $dvA->containsCompanyAffix($possEnt) === TRUE) {
            $score = $score+20;
        }

        /* Does the entity start 'if'? */
        if ($entityParts[0] == 'if') {
            $score = $score-50;
        }

        /* Does the entity start 'The', and follow just with a legal term? */
        if ($entityParts[0] == 'the' && $dvA->isLegalTerm($entityParts[1])) {
            $score = $score-30;
        }

        /* If it's a two-part entity, are both words the same? */
        if ($entityParts[0] == $entityParts[1]) {
            $score = $score-30;
        }

        /* If it's a two-part entity and one word is a noun */
        // if (count($entityParts) == 2) {
        //     if ($dvA->isNoun($entityParts[0]) || $dvA->isNoun($entityParts[1])) {
        //         $score = $score-20;
        //     }
        // }

        /* If it's a single-word entity, and the word is a verb, deduct */
        if (count($entityParts) == 1) {
            if ($dvA->isVerb($entityParts[0])) {
                $score = $score-10;
            }
        }

        /* If it's a single word, and that word exists as the first one in a proven (80+) entity, add points here */
        // ...
        // ...
        // ...

        foreach($entityParts as $ep) {

            if ($dvA->isStopword($ep)) {
                $score = $score-20;
            }

            if ($dvA->isNoun($ep)) {
                $score = $score-10;
            }

        }

        return $score;

    }


    /**
    *
    * ENTITIES
    *
    */
    private function fetchEntities(array $wordMap, $dvA): array {

        $entities   = array();

        $caps       = $dvA->capitalisedEntities($wordMap);
        $entities   = $dvA->contextualiseEntities($caps);

        $entities   = array_filter($entities, function($e) use ($dvA) {

            if (!$e['legal'] && !$e['jobtitle'] && !$e['stopword'] && !$e['place'] && !$e['month']) {

                if (self::calcScore($e['word'], $dvA) > 79) {
                    return true;
                }

            }

        });

        return array_unique($entities);

    }

    /**
    *
    * PEOPLE
    *
    */
    private function fetchPeople(array $wordMap, $dvA): array {

        $names = array();

        $nameSet = $dvA->names($wordMap);

        foreach($nameSet as $name) {

            $nameText = '';

            switch($name['process']) {

                case 'spsNameMatches':
                    $nameText = ucfirst($name['word']) . ' ' . ucfirst($name['r1']) . ' ' . ucfirst($name['r2']);
                break;

                case 'psNameMatches':
                    $nameText = ucfirst($name['word']) . ' ' . ucfirst($name ['r1']);
                break;

                case 'ssNameMatches':
                    $nameText = ucfirst($name['word']) . ' ' . ucfirst($name ['r1']);
                break;

                case 'sPiSNameMatches':
                    $nameText = ucfirst($name['word']) . ' ' . ucfirst($name['r1']) . ' ' . ucfirst($name['r2']);
                break;

            }

            array_push($names, $nameText);

        }

        $names = array_unique($names);

        return $names;

    }


    /**
    *
    * EMAILS
    *
    */
    private function fetchEmails(string $text, $dvA): array {
        return $dvA->extractEmails($text);
    }


    /**
    *
    * PLACES
    *
    */
    private function fetchPlaces(array $wordMap, $dvA): array {

        $p = array();

        $places = array_filter($wordMap, function($word) use ($dvA) {
            if ($word['place'] && !$word['stopword']) {
                return true;
            }
        });

        foreach($places as $place) {
            if (!$dvA->isNoun($place['word'])) {
                array_push($p, $place['word']);
            }
        }

        return array_unique($p);

    }

    /**
    *
    * DATES
    *
    */
    private function fetchDates(array $wordMap, $dvA): array {

        $dates = array();

        foreach($dvA->dates($wordMap) as $date) {
            array_push($dates, $date['date']);
        }

        $dates = array_unique($dates);

        return $dates;

    }

    /**
    *
    * ROLES/JOB TITLES
    *
    */
    private function fetchRoles(string $text, $dvA): array {
        return array();
    }

    /**
    *
    * LEGAL TERMS
    *
    */
    private function fetchLegalTerms(array $wordMap, $dvA): array {
        return $dvA->bagOfWords($dvA->legalSingleTerms($wordMap)) ?? array();
    }

    private function fetchLegalExtended(array $wordMap, $dvA): array {

        $legalExtended = array();

        $legalExtended = $dvA->legalExtended($wordMap);
        $legalExtended = $dvA->bagOfWordsUnfiltered($legalExtended);

        return $legalExtended;

    }

    /**
    *
    * MONETARY FIGURES
    *
    */
    private function fetchFigures(array $wordMap, $dvA): array {
        return $dvA->figures($wordMap) ?? array();
    }

    /**
    *
    * URLS
    *
    */
    private function fetchUrls(array $wordMap, $dvA): array {
        return $dvA->urls($wordMap) ?? array();
    }


    /**
    *
    * Filter legal term lists against each other
    *
    */
    private function filterLegalTerms(array $terms, array $termsExt): array {

        $t = array();

        $termsFlat      = array();
        $termsFlatExt   = array();

        foreach($terms as $term => $count) {
            array_push($termsFlat, $term);
        }

        foreach($termsExt as $term => $count) {
            array_push($termsFlatExt, $term);
        }

        if (count($termsExt) === 0) {
            return $termsFlat;
        }

        foreach($termsFlatExt as $term) {

            /* Double */
            $doubleParts = explode(' ', $term);

            if (count($doubleParts) === 2) {

                $word1 = $doubleParts[0];
                $word2 = $doubleParts[1];

                $keys = array_keys($terms);

                $word1key       = array_search($word1, $keys);
                $word2key       = array_search($word2, $keys);

                $firstWord      = $keys[$word1key];
                $secondWord     = $keys[$word1key+1];

                $match = false;

                if ($firstWord == $word1 && $secondWord == $word2) {
                    $match = true;

                    unset($keys[$word1key]);
                    unset($keys[$word1key+1]);

                }

                foreach($keys as $k => $l) {
                    array_push($t, $l);
                }

            }


            /* Triple */
            $tripleParts = explode(' ', $term);

            if (count($tripleParts) === 3) {

                $word1 = $tripleParts[0];
                $word2 = $tripleParts[1];
                $word3 = $tripleParts[2];

                $keys = array_keys($terms);

                $word1key       = array_search($word1, $keys);
                $word2key       = array_search($word2, $keys);
                $word3key       = array_search($word3, $keys);

                $firstWord      = $keys[$word1key];
                $secondWord     = $keys[$word1key+1];
                $thirdWord      = $keys[$word1key+2];

                $match = false;

                if ($firstWord == $word1 && $secondWord == $word2 && $thirdWord == $word3) {
                    $match = true;

                    unset($keys[$word1key]);
                    unset($keys[$word1key+1]);
                    unset($keys[$word1key+2]);

                }

                foreach($keys as $k => $l) {
                    array_push($t, $l);
                }

            }

        }

        return array_unique($t);

    }


    private function prepTag(string $string) {

      /* Lower case */
      $string = mb_strtolower($string);

      /* Percent encode */
      $string = urlencode($string);

      /* Return */
      return $string;

    }



    /**
    *
    * Tags the given File with information retrieved by the DocVaultAnalyse class
    *
    */
    public function generateTags(string $dvKey = NULL) {

      try {

        $dvA = new \App\Classes\DocVaultAnalyse\DocVaultAnalyse();

        \Log::info($dvKey . ' - DocVaultAnalyse class initialised');

        $file = File::where(array('docvaultkey' => $dvKey))->first();

        if (!$file) {
          throw new \Exception('File doesn\'t exist');
        }


        /**
        *
        * This service will only attempt to autotag plain text, Microsoft Word, and PDF files
        *
        */

        /* Check file-type first */
        $file = File::where('docvaultkey', $dvKey)->first();

        if ($file->mimetype !== 'application/pdf') {
          throw new \Exception('auto-tagging is currently only available for files of type application/pdf');
        }


        $fileS3Key = $file->docvaultkey . '/' . $file->filename;

        /* Fetch file from S3 */
        $s3Result = $this->s3Client->getObject([
          'Bucket'                => $this->s3bucketName,
          'Key'                   => $fileS3Key,
          'ResponseContentType'   => 'text/plain'
        ]);

        $fileContents = $s3Result['Body'];

        dd($fileContents);

        \Log::info($dvKey . ' - received File body from S3');

        $dvA->setText($fileContents);


        /* Master arrays that hold the computed results of each sentence */
        $docEntities    = array();
        $docPeople      = array();
        $docEmails      = array();
        $docPlaces      = array();
        $docDates       = array();
        $docRoles       = array();
        $docLegalTerms  = array();
        $docFigures     = array();
        $docUrls        = array();


        /* Process the document analysis */
        \Log::info($dvKey . ' - starting analysis');

        $sentences = $dvA->analyseSentences();

        $x = 1;

        foreach($sentences as $s) {

            $sBlock     = $dvA->mapBlock($s['sentence']);
            $entities   = self::fetchEntities($sBlock, $dvA);
            $people     = self::fetchPeople($sBlock, $dvA);
            $emails     = self::fetchEmails($s['sentence'], $dvA);
            $places     = self::fetchPlaces($sBlock, $dvA);
            $dates      = self::fetchDates($sBlock, $dvA);
            $roles      = self::fetchRoles($s['sentence'], $dvA);
            $terms      = self::fetchLegalTerms($sBlock, $dvA);
            $termsExt   = self::fetchLegalExtended($sBlock, $dvA);

            $terms      = self::filterLegalTerms($terms, $termsExt);

            /* Filter human names out of entities */
            if (count($people)>0) {

                $entities = array_filter($entities, function($ent) {

                    foreach($people as $p) {

                        $contains = strpos($ent['word'], strtolower($p));

                        if ($contains === FALSE) {
                            return true;
                        }

                    }

                });

            }

            foreach($entities as $e) {
                array_push($docEntities, 'auto_entity:' .  self::prepTag(ucwords($e['word'])));
            }

            foreach($people as $p) {
                array_push($docPeople, 'auto_person:' . self::prepTag($p));
            }

            foreach($emails[0] as $e) {
                array_push($docEmails, $e);
            }

            foreach($places as $p) {
                array_push($docPlaces, 'auto_place' . self::prepTag(ucwords($p)));
            }

            foreach($dates as $d) {
                array_push($docDates, 'auto_date' . self::prepTag($d));
            }

            foreach($roles as $r) {

            }

            foreach($terms as $term) {
                array_push($docLegalTerms, $term);
            }

            foreach($termsExt as $key => $t) {
                array_push($docLegalTerms, $key);
            }

            $x++;

        }

        \Log::info($dvKey . ' - tag extraction complete');

        $docEntities    = array_unique($docEntities);
        $docPeople      = array_unique($docPeople);
        $docPlaces      = array_unique($docPlaces);
        $docLegalTerms  = $dvA->bagOfWords($docLegalTerms);

        $result = array(
          'entities'    => $docEntities,
          'people'      => $docPeople,
          'places'      => $docPlaces,
          'dates'       => $docDates
        );

        $resultStr = implode(',', $docEntities) . implode(',', $docPeople) . implode(',', $docPlaces) . implode(',', $docDates);

        \Log::info($dvKey . ' - result string: ' . $resultStr);

        return response()->json($result);


        /** Save these autotags to the File record */
        $file = File::where('docvaultkey', $dvKey)->first();

        if ($file) {

        }


      } catch (\Exception $ex) {

        return response()->json(array(
            'response'  => 'Request failed',
            'detail'    => $ex->getMessage()
        ), 400);

      }

    }



    private function prepForTag(string $string): string {
        return urlencode(strtolower($string)) ?? '';
    }


    /**
    *
    * Performs a Natural-Language search for NDA Files
    *
    */
    public function nlpFiles(string $queryText, int $userId = NULL) {

        try {

            if (!isset($queryText)) {
                throw new \Exception('Please provide a valid search term');
            }

            $queryText = urldecode($queryText);

            /** Initialise DocVault NLP Class */
            $dvNl = new \App\Classes\DvNatural\DocVaultNaturalSearch();

            $ignoreWords            = ['title'];

            $dvnlResult             = $dvNl->Parse($queryText, $this->searchableDoctypes, $this->searchableDoctypeSubs, $this->searchableAttributes);


            /** Parse the search term using DocVaultNaturalSearch */
            $doctypes       = $dvnlResult['DocumentTypes'];
            $doctypesSub    = $dvnlResult['DocumentSubTypes'];
            $purpose        = $dvnlResult['PossiblePurpose'];
            $parties        = $dvnlResult['Parties'];
            $dates          = $dvnlResult['Dates'];
            $datesRelative  = $dvnlResult['RelativeDates'];


            /** Does this query include the (debug) flag? */
            if (strpos($queryText, '(debug)') !== FALSE) {

              $debugResponse = array(
                'querytext'       => $queryText,
                'doctypes'        => $doctypes,
                'doctypes_sub'    => $doctypesSub,
                'purpose'         => $purpose,
                'parties'         => $parties,
                'dates'           => $dates,
                'dates_relative'  => $datesRelative
              );

            }


            $a = array();

            foreach($parties as $p) {
                $p = strtolower($p);
                array_push($a, $p);
            }

            $parties = $a;


            /** Generate tags based on the NLP result */
            $tags = array();

            foreach($doctypes as $dt) {
                array_push($tags, 'doctype:' . self::prepForTag($dt));
            }

            foreach($doctypesSub as $dt) {
                array_push($tags, 'doctype_sub:' . self::prepForTag($dt));
            }

            if (count($purpose)>0) {
                $p = implode(' ', $purpose);
                array_push($tags, 'purpose:' . self::prepForTag($dt));
            }

            foreach($parties as $p) {
                array_push($tags, 'party:' . self::prepForTag($p));
            }


            /** Generate SQL to load batch of possible matches based on these tags */
            $sql = 'SELECT * FROM storedfiles WHERE ';

            foreach($tags as $tag) {
                $sql .= '(tags LIKE \'%' . $tag . '%\') AND ';
            }

            if (substr($sql, -5) == ' AND ') {
              $sql = substr($sql, 0, -5);
            }

            if ($userId !== NULL && is_numeric($userId)) {
                $sql .= ' AND userid = ' . $userId;
            }

            $sql .= ' AND deleted = 0';


            /** If debug add SQL to the debug info object */
            if (strpos($queryText, '(debug)') !== FALSE) {

              $debugResponse['query']   = $sql;

              return response()->json(
                $debugResponse
              );

            }


            $files = DB::select(DB::raw($sql));


            /** Filter out any files that aren't in relevant date ranges */
            if (count($datesRelative)>0) {

                $filesWithDateTags = array();

                /** Extract any Files from results that have a 'date_' tag */
                foreach($files as $file) {

                    $hasDateTag = FALSE;

                    foreach(explode(',', $file->tags) as $tag) {
                        if (strpos($tag, 'date_') !== FALSE) {
                            $hasDateTag = TRUE;
                        }
                    }

                    if ($hasDateTag === TRUE) {
                        array_push($filesWithDateTags, $file);
                    }

                }

                /** Check the dates in the 'date_' tags are in the ranges given by NLP analysis */
                $files = array_filter($files, function($f) use ($datesRelative) {

                    $relTags = array_filter(explode(',', $f->tags), function($tag) {
                        return (stripos($tag, 'date_') === false) ? false : true;
                    });

                    array_walk($relTags, function(&$value, &$key) {
                        $value = substr($value, (strpos($value, ':')+1));
                    });

                    foreach($datesRelative as $dr) {
                        foreach(array_values($relTags) as $t) {
                            return (($t > $dr[1]['range_low'] && $t < $dr[1]['range_high']) === true) ? true : false;
                        }
                    }

                });

            }

            /** Return */
            return response()->json($files);

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


    /**
    *
    * Performs a Natural-Language search for Files
    *
    */
    public function filesSearch(string $searchTerm, $userId = NULL) {

        try {

            if (!isset($searchTerm)) {
                throw new \Exception('Please provide a valid search term');
            }

            if (isset($userId) && !is_numeric($userId)) {
                throw new \Exception('Please provide a valid userId');
            }

            /** Parse the search term using DocVaultNaturalSearch */
            // ...

            /** Look up files matching the parse entities */
            // ...

            /** Return */
            // ...

        } catch (\Exception $ex) {

            return response()->json(array(
                'response'  => 'Request failed',
                'detail'    => $ex->getMessage()
            ), 400);

        }

    }


}
