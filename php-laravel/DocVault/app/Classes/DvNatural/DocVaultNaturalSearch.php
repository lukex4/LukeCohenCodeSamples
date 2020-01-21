<?php

namespace App\Classes\DvNatural;

/**
*
* Extracts specific information from natural language document searches
*
*/

use DateTime;
use DateTimeZone;

class DocVaultNaturalSearch {

    /* The query */
    private $queryText              = '';
    private $queryParts             = array();

    /*  */
    private $namesetPath            = 'lib/surnames.txt';
    private $stopwordSetPath        = 'lib/stopwords.txt';
    private $firstnameSetPath       = 'lib/prenames.csv';
    private $nounSetPath            = 'lib/nouns.txt';

    /* The result of extraction attempts */
    public $extractedSoftSearch     = array();
    public $extractedDates          = array();
    public $extractedRelativeDates  = array();
    public $extractedMiscPossibles  = array();
    public $extractedOperators      = array();
    public $matchedDoctypes         = array();
    public $matchedDoctypeSubs      = array();
    public $partiesExtracted        = array();

    /* Date */
    private $months                 = array('JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER');

    /* Misc Extract */
    public $ignoreWords             = array('title');
    public $softInTitle;
    private $nounSet                = array();

    /* Documents */
    private $searchableDoctypes     = array();
    private $searchableDoctypeSubs  = array();

    /* Parties */
    private $nameSet;
    private $stopwordSet;
    private $firstnameSet;

    /* Pulse User Intents */
    private $operatorGroups         = array(
                                        'FIND_DOCUMENT'   => array(
                                          'find',
                                          'looking',
                                          'search',
                                          'searching',
                                          'where',
                                          'locate',
                                          'retrieve',
                                          'open'
                                        ),
                                        'CUSTOMER_CARE'   => array(
                                          'speak',
                                          'help',
                                          'person',
                                          'customer',
                                          'questions',
                                          'question'
                                        ),
                                        'START_DOCUMENT'  => array(
                                          'create',
                                          'need',
                                          'start',
                                          'want',
                                          'new'
                                        ),
                                        'ATTORNEY'        => array(
                                          'attorney',
                                          'lawyer',
                                          'solicitor'
                                        )
                                      );

    private $salutations            = ['MR', 'MRS', 'MISS', 'MS', 'DR', 'PROF', 'PROFESSOR', 'LADY', 'SIR', 'LORD', 'JUDGE', 'DAME', 'MR.', 'MRS.', 'MISS.', 'MS.', 'DR.', 'PROF.', 'PROFESSOR.', 'LADY.', 'SIR.', 'LORD.', 'JUDGE.', 'DAME.'];

    /* Operators */
    private $possibleOperators      = array('signed', 'created', 'about', 'containing', 'concerning', 'regarding', 'between', 'anything', 'all', 'with', 'expire', 'expires', 'expiring', 'find', 'looking', 'search', 'searching', 'where', 'locate', 'speak', 'help', 'person', 'customer', 'create', 'need', 'want', 'new', 'retrieve', 'open', 'question', 'questions', 'attorney', 'lawyer', 'solicitor');
    private $subjectDelimiters      = array('about', 'containing', 'concerning', 'regarding');

    /* Soft */
    private $searchableAttributes   = array();
    private $titleDelimeterOpen     = 'with';
    private $titleDelimeterClose    = 'in the title';


    /**
    *
    * Constructor
    *
    */
    public function __construct() {

        self::loadNameset();
        self::loadStopwordset();
        self::loadFirstNameset();
        self::loadNounSet();

    }


    /* Loads the nameset (surnames) from file */
    private function loadNameset(): array {

        $names = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->namesetPath));
        $this->nameSet = $names;

        return $names;

    }

    /* Loads the stopwordSet from file */
    private function loadStopwordset(): array {

        $stopwords = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->stopwordSetPath));
        $this->stopwordSet = array_map('strtoupper', $stopwords);

        return $stopwords;

    }

    /* Loads the firstnameSet from file */
    private function loadFirstNameset(): array {

        $names = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->firstnameSetPath));
        $this->firstnameSet = $names;

        return $names;

    }

    /* Loads the nounsSet from file */
    private function loadNounSet(): array {

        $nouns = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->nounSetPath));
        $this->nounSet = array_map('strtoupper', $nouns);

        return $this->nounSet;

    }


    /* Clean a query-part of punctuation characters, and 's */
    private function cleanQueryPart(string $queryPart): string {

        $queryPart = str_replace(array('?', '.', ',', ';', ':'), array('', '', '', '', ''), $queryPart);

        if (substr(strtoupper($queryPart), -2) == '\'S') {
            $queryPart = substr($queryPart, 0, -2);
        }

        return $queryPart;

    }


    /**
    *
    * Receives the input variables and sets internal workings up accordingly
    *
    */
    public function Parse(string $queryText, array $searchableDoctypes = ['contract', 'document'], array $searchableDoctypeSubs = ['nda', 'employment', 'disclosure'], array $searchableAttributes = []): array {

        $this->queryText    = $queryText;
        $this->queryParts   = explode(' ', $queryText);

        $this->searchableDoctypes       = $searchableDoctypes;
        $this->searchableDoctypeSubs    = $searchableDoctypeSubs;
        $this->searchableAttributes     = $searchableAttributes;

        /* Pluralise Document and Sub-Document types */
        $doctypesPlural     = array();
        $doctypesSubPlural  = array();

        foreach($this->searchableDoctypes as $searchableDoctype) {
            array_push($doctypesPlural, $searchableDoctype . 's');
        }

        foreach($this->searchableDoctypeSubs as $searchableDoctypeSub) {
            array_push($doctypesSubPlural, $searchableDoctypeSub . 's');
        }

        $this->searchableDoctypes       = array_merge($doctypesPlural, $this->searchableDoctypes);
        $this->searchableDoctypeSubs    = array_merge($doctypesSubPlural, $this->searchableDoctypeSubs);

        return array(
            'QueryText'         => $this->queryText,
            'DocumentTypes'     => self::DocumentTypes(),
            'DocumentSubTypes'  => self::DocumentSubTypes(),
            'Dates'             => self::Dates(),
            'RelativeDates'     => self::RelativeDates(),
            'Attributes'        => self::Attributes(),
            'Operators'         => self::Operators(),
            'Parties'           => self::Parties(),
            'InTitle'           => self::InTitle(),
            'PossibleEntities'  => self::PossibleEntities(),
            'PossiblePurpose'   => self::PossiblePurpose()
        );

    }


    /** 'SOFT' EXTRACTION, LOOKS AT TITLE-BASED SEARCHES, DOCUMENT ATTRIBUTE-BASED SEARCHES, ETC. **/


    /**
    *
    * Extract '[X] in the title' search parameters
    *
    */
    public function InTitle(): array {

        $search = array(
            'in_title'     => ''
        );

        /* Is this a relevant query? */
        if (strpos($this->queryText, $this->titleDelimeterClose) > 0) {

            $posOpen = (strpos($this->queryText, $this->titleDelimeterOpen)+strlen($this->titleDelimeterOpen));
            $posClose = strpos($this->queryText, $this->titleDelimeterClose);

            $posLength = $posClose-$posOpen;

            $extract = substr($this->queryText, $posOpen, $posLength);

            $search['in_title'] = trim($extract);

        }

        $this->extractedSearch = $search;

        return array_map('strtoupper', $this->extractedSearch);

    }


    /**
    *
    * Match possible document attributes
    *
    */
    public function Attributes(): array {

        $attributes = array();

        $query = strtolower($this->queryText);

        foreach($this->searchableAttributes as $attribute) {

            $attribute = strtolower($attribute);

            if (strpos($query, $attribute) !== FALSE || strpos($query, $attribute . 's') !== FALSE) {
                array_push($attributes, $attribute);
            }

        }

        $this->extractedAttributes = $attributes;

        return array_map('strtoupper', $this->extractedAttributes);

    }


    /** DATE EXTRACTION, EXTRACTS DATES, AS WELL AS 'RELATIVE' DATES LIKE 'NEXT MONTH' **/


    /**
    *
    * Extracts dates
    *
    */
    public function Dates(): array {

        $dates = array();

        foreach($this->queryParts as $key => $part) {

            $gotMonth   = false;
            $gotYear    = true;

            $m          = '';
            $y          = '';
            $date       = '';

            /* Is there a month? */
            if (in_array(strtoupper($part), $this->months)) {
                $gotMonth = true;
                $m = $part;

                /* Is there an aligning year? */
                if ($this->queryParts[$key+1] && strlen($this->queryParts[$key+1])==4 && is_numeric($this->queryParts[$key+1])) {

                    $gotYear = true;
                    $y = $this->queryParts[$key+1];

                } else {
                    $y = date('Y');
                }

                /* Compile the full date 'yyyy-mm-dd' */
                $date = $m . ' ' . $y;
                $date = date_parse($date);
                $date = $date['year'] . '-' . $date['month'] . '-' . $date['day'];

                /* Create a Unix timestamp range for this date (i.e. from 00:00 to 23:59) */
                $dtFrom   = $date . ' 00:00:00';
                $dtTo     = $date . ' 23:59:00';

                $dtFrom = new DateTime($dtFrom, new DateTimeZone('UTC'));
                $tsFrom = $dtFrom->format('U');

                $dtTo   = new DateTime($dtTo, new DateTimeZone('UTC'));
                $tsTo   = $dtTo->format('U');

                $date = array(
                    'date'                  => $date,
                    'date_timestamp_from'   => $tsFrom,
                    'date_timestamp_to'     => $tsTo
                );

                array_push($dates, $date);

            } else {

                /* Are there any years? */
                if (strlen($part)==4 && is_numeric($part)) {

                    /* Create Unix timestamp range for this year (YYYY-1-1 to YYYY-12-31) */
                    $dtFrom   = $part . '-1-1 00:00:00';
                    $dtTo     = $part . '-12-31 23:59:00';

                    $dtFrom = new DateTime($dtFrom, new DateTimeZone('UTC'));
                    $tsFrom = $dtFrom->format('U');

                    $dtTo   = new DateTime($dtTo, new DateTimeZone('UTC'));
                    $tsTo   = $dtTo->format('U');

                    $date = array(
                        'date'                  => $part,
                        'is_year'               => TRUE,
                        'date_timestamp_from'   => $tsFrom,
                        'date_timestamp_to'     => $tsTo
                    );

                    array_push($dates, $date);
                }

            }

        }

        $this->extractedDates = $dates;

        return $dates;

    }


    /**
    *
    * Extract 'relative' dates, e.g. "next month"
    *
    */
    public function RelativeDates():array {

        $dates = array();

        $possibleRelativeDates = array();

        $possibleRelativeDates['next year']     = ['00:00 first day of next year', '23:59 last day of next year'];
        $possibleRelativeDates['next month']    = ['00:00 first day of next month', '23:59 last day of next month'];
        $possibleRelativeDates['next week']     = ['00:00 first day of next week', '23:59 last day of next week'];
        $possibleRelativeDates['tomorrow']      = ['00:00 first day of tomorrow', '23:59 last day of tomorrow'];
        $possibleRelativeDates['today']         = ['00:00 first day of today', '23:59 last day of today'];
        $possibleRelativeDates['last year']     = ['00:00 first day of last year', '23:59 last day of last year'];
        $possibleRelativeDates['last month']    = ['00:00 first day of last month', '23:59 last day of last month'];
        $possibleRelativeDates['last week']     = ['00:00 first day of last week', '23:59 last day of last week'];
        $possibleRelativeDates['yesterday']     = ['00:00 yesterday', '23:59 yesterday'];
        $possibleRelativeDates['this week']     = ['00:00 first day of this week', '23:59 last day of this week'];

        $todayAliases = ['hour ago', 'hours ago', 'minute ago', 'minutes ago', 'earlier', 'just now'];


        $query = strtolower($this->queryText);

        foreach($todayAliases as $a) {
          if (strpos($query, $a) !== FALSE) {
            $query .= ' ' . 'today';
          }
        }


        foreach($possibleRelativeDates as $key => $prd) {

            if (strpos($query, $key) !== FALSE) {

                $item = array(
                    'range_low'     => strtotime($prd[0], time()),
                    'range_high'    => strtotime($prd[1], time())
                );

                array_push($dates, array(strtoupper($key), $item));

            }

        }

        $this->extractedRelativeDates = $dates;

        return $dates;

    }


    /** DOCUMENT REFERENCE EXTRACTION, E.G. WHEN AN NDA IS REFERRED TO **/

    /**
    *
    * Extracts 'major' document types (e.g. 'contract', 'deed')
    *
    */
    public function DocumentTypes(): array {

        $doctypesMatched            = array();

        foreach($this->queryParts as $searchQueryPart) {

            $searchQueryPart = self::cleanQueryPart(strtolower($searchQueryPart));

            if (in_array($searchQueryPart, $this->searchableDoctypes)) {

                if (substr($searchQueryPart, -1) == 's') {
                    $searchQueryPart = substr($searchQueryPart, 0, -1);
                }

                array_push($doctypesMatched, $searchQueryPart);
            }

        }

        $this->matchedDoctypes = $doctypesMatched;

        return array_map('strtoupper', $this->matchedDoctypes);

    }

    /**
    *
    * Extracts 'minor' document types (e.g. 'NDA', 'employment contract')
    *
    */
    public function DocumentSubTypes(): array {

        $subDoctypesMatched             = array();

        foreach($this->queryParts as $searchQueryPart) {

            $searchQueryPart = strtolower($searchQueryPart);

            if (in_array($searchQueryPart, $this->searchableDoctypeSubs)) {

                if (substr($searchQueryPart, -1) == 's') {
                    $searchQueryPart = substr($searchQueryPart, 0, -1);
                }

                array_push($subDoctypesMatched, $searchQueryPart);

            }

        }

        $this->matchedDoctypeSubs   = $subDoctypesMatched;

        return array_map('strtoupper', $this->matchedDoctypeSubs);

    }


    /** PARTIES EXTRACTION, ATTEMPTS TO EXTRACT POSSIBLE REFERENCES TO PEOPLE **/

    /**
    *
    * Extracts parties
    *
    */
    public function Parties(): array {

        $nameSet        = $this->nameSet;
        $stopwordSet    = $this->stopwordSet;
        $firstnameSet   = $this->firstnameSet;

        $namesFound = array();

        /* First extract known surnames, and the word preceding each match */
        foreach($this->queryParts as $key => $queryPart) {

            /* Remove any superfluous commas and periods, and capitalise */
            $queryPart = self::cleanQueryPart($queryPart);
            $queryPart = strtoupper($queryPart);

            /* Is this a surname, and if so, what word precedes it? */
            if (in_array($queryPart, $nameSet)) {

                $wordBefore     = $this->queryParts[$key-1] ?? '';
                $wordAfter      = $this->queryParts[$key+1] ?? '';

                $wordBefore     = strtoupper($wordBefore);
                $wordAfter      = strtoupper($wordAfter);

                array_push($namesFound, array(
                    'term'          => $queryPart,
                    'listpos'       => array_search($queryPart, $nameSet),
                    'key'           => $key,
                    'wordbefore'    => $wordBefore,
                    'wordafter'     => $wordAfter
                ));
            }

        }


        /* Check each 'wordbefore' against a Stop-Word list, known-first name list, salutation list, check each term against the stopword list to prevent false-positives */
        foreach($namesFound as $key => $nameFound) {

            $term           = strtoupper($nameFound['term']);

            $wordBefore     = $nameFound['wordbefore'];
            $wordBefore     = self::cleanQueryPart($wordBefore);

            // $wordBefore     = str_replace(array(',', '.'), array('', ''), $wordBefore);

            /* If wordbefore surname is a stopword, then it definitely isn't a first name */
            if (!in_array($wordBefore, $stopwordSet)) {
                $namesFound[$key]['wordbefore_possiblename'] = TRUE;
            }

            /* Is wordbefore a known first name? */
            if (in_array($wordBefore, $firstnameSet)) {
                $namesFound[$key]['wordbefore_isname'] = TRUE;
            }

            /* Is wordbefore a known salutation (Mr, Mrs, etc) */
            if (in_array($wordBefore, $this->salutations)) {
                $namesFound[$key]['wordbefore_salutation'] = TRUE;
            }

            /* Is the term in the stoplist */
            if (in_array($term, $stopwordSet)) {
                unset($namesFound[$key]);
            }

            /* Is the term in the nouns list */
            if (in_array($wordBefore, $this->nounSet)) {
                unset($namesFound[$key]);
            }

            /* In the case where the possible surname is a month (May, January), check if word-before is a year, and remove if so */
            if (strlen($wordBefore) == 4 && is_numeric($wordBefore)) {
                unset($namesFound[$key]);
            }

        }


        /* Process matches where wordbefore is a salutation and term is a first name */
        foreach($namesFound as $key => $nameFound) {

            $wordBefore     = $nameFound['wordbefore'];
            $wordAfter      = $nameFound['wordafter'];
            $term           = $nameFound['term'];

            /* If possiblename or isname has not been flagged true by this stage, they should be cut out of list of possibilities */
            if (!isset($nameFound['wordbefore_possiblename'])) {
                unset($namesFound[$key]);
                continue;
            }

            /* If wordbefore is a checked name, and ends with a comma, then we can assume this is a reverse-order name (e.g. SMITH, JOHN) */
            if (substr($wordBefore, -1) == ',') {
                $wordBefore = substr($wordBefore, 0, -1);
                $namesFound[$key]['fullname'] = $term . ' ' . $wordBefore;
                continue;
            }

            if (isset($nameFound['wordbefore_salutation'])) {
                $namesFound[$key]['fullname'] = $wordBefore . ' ' . $term . ' ' . $wordAfter;
            } else {
                $namesFound[$key]['fullname'] = $wordBefore . ' ' . $term;
            }

        }


        /* Create clean array of found names */
        $names = array();

        foreach($namesFound as $nameFound) {
            array_push($names, (string)$nameFound['fullname']);
        }


        /* Does query contain 'me'? */
        // foreach($this->queryParts as $key => $queryPart) {
        //
        //     $queryPart = self::cleanQueryPart($queryPart);
        //     $queryPart = strtoupper($queryPart);
        //
        //     if ($queryPart == 'ME') {
        //         array_push($names, 'ME');
        //     }
        //
        //     if ($queryPart == 'MYSELF') {
        //         array_push($names, 'MYSELF');
        //     }
        //
        //     if ($queryPart == 'MY') {
        //         array_push($names, 'MY');
        //     }
        //
        // }


        $this->partiesExtracted = $names;

        return $names;

    }


    /** OPERATORS EXTRACTION - LINGUISTIC OPERATORS THAT WE CAN GLEAN 'DIRECTION' OR 'INTENTION' FROM **/
    public function queryParts(): array {
      return self::queryPartsClean();
    }


    private function queryPartsClean(): array {
      return array_map('strtolower', $this->queryParts);
    }


    /**
    *
    * Extracts operators
    *
    */
    public function Operators(): array {

        $operators = array();

        foreach($this->possibleOperators as $possibleOperater) {

          $possibleOperater = strtolower($possibleOperater);

          if (in_array($possibleOperater, self::queryPartsClean())) {
              array_push($operators, $possibleOperater);
          }

        }

        $this->extractedOperators = $operators;

        return array_map('strtoupper', $operators);

    }


    /**
    *
    * Extract intent
    *
    */
    public function Intent() {

      $operatorMatches = array(
        'ATTORNEY'        => array(),
        'FIND_DOCUMENT'   => array(),
        'CUSTOMER_CARE'   => array(),
        'START_DOCUMENT'  => array()
      );

      foreach(self::Operators() as $operator) {
        $operator = strtolower($operator);

        foreach($this->operatorGroups as $key => $og) {

          foreach($og as $o) {
            if ($o == $operator) {
              array_push($operatorMatches[$key], $o);
            }
          }

        }

      }

      foreach($operatorMatches as $key => $oM) {

        if (count($oM) > 0) {
          return $key;
        }

      }

    }


    /** MISC EXTRACTION ATTEMPTS TO MATCH WORDS IN THE QUERY WITH POSSIBLE ENTITIES OTHER THAN THOSE IDENTIFIED IN OTHER PASSES **/

    /**
    *
    * Build the reservedEntities array - reserved entity is something that has already been surfaced in previous passes, and should not be considered an miscellaneous entity
    *
    */
    private function reservedEntities():array {

        $reserved = array();

        /* Merge doctypes and sub-doctypes into one array for convenience */
        $docTypesMerged = array_merge($this->searchableDoctypes, $this->searchableDoctypeSubs);
        $docTypesMerged = array_map('strtoupper', $docTypesMerged);

        /* Break any parties up into their component words */
        $partyParts = array();

        foreach($this->partiesExtracted as $party) {
            foreach(explode(' ', $party) as $p) {
                array_push($partyParts, $p);
                array_push($partyParts, $p . ',');
                array_push($partyParts, $p . '.');
            }
        }

        /*
        *
        * Not in doctypes
        * Not in parties
        * Not in operators
        * Not a day or month
        *
        */
        foreach($this->queryParts as $key => $queryPart) {

            $queryPart = strtoupper($queryPart);

            if (in_array($queryPart, $partyParts) || in_array($queryPart, $docTypesMerged) || in_array($queryPart, $this->extractedOperators) || strlen($queryPart)==4 && is_numeric($queryPart) || in_array($queryPart, $this->months)) {
                array_push($reserved, $queryPart);
            }

            $queryPart = strtolower($queryPart);

            if (in_array($queryPart, $partyParts) || in_array($queryPart, $docTypesMerged) || in_array($queryPart, $this->extractedOperators) || strlen($queryPart)==4 && is_numeric($queryPart) || in_array($queryPart, $this->months)) {
                array_push($reserved, $queryPart);
            }

        }

        /**
        *
        *
        *
        */

        return array_map('strtoupper', array_merge($reserved, $this->ignoreWords));

    }


    /**
    *
    * Extract '[X] in the title' search parameters
    *
    */
    public function PossibleEntities(): array {

        $possible = array();
        $reserved = $this->reservedEntities();

        foreach($this->queryParts as $key => $queryPart) {

            $queryPart = self::cleanQueryPart(strtoupper($queryPart));

            if (!in_array($queryPart, $reserved) && !in_array($queryPart, $this->stopwordSet)) {
                array_push($possible, $queryPart);
            }

        }

        $this->extractedMiscPossibles = $possible;

        return $possible;

    }


    /**
    *
    * Extract possible about/concerning/etc.
    *
    */
    public function PossiblePurpose(): array {

        $subject    = array();
        $hasSubject = FALSE;

        foreach($this->queryParts as $key => $queryPart) {

            $queryPart = self::cleanQueryPart(strtolower($queryPart));

            /* Detect the start of a possible subject */
            if (in_array($queryPart, $this->subjectDelimiters)) {
                if ($hasSubject === FALSE) {
                    $hasSubject = TRUE;
                }
            }

            /* Detect the end of a possible subject */
            if (in_array($queryPart, $this->possibleOperators)) {
                if ($hasSubject === TRUE && count($subject)>0) {
                    $hasSubject = FALSE;
                    break;
                }
            }

            /* Push each possible purpose piece to the array */
            if ($hasSubject === TRUE && !in_array($queryPart, $this->possibleOperators)) {
                array_push($subject, $queryPart);
            }

        }

        return $subject;

    }



}

?>
