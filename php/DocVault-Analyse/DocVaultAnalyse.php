<?php

namespace App\Classes\DocVaultAnalyse;

require __DIR__.'/vendor/jwhennessey/phpinsight/autoload.php';

// Use PHPInsight\Sentiment;

/**
*
* Analyse documents, classify and extract relevant information
*
*/

class DocVaultAnalyse {

    /* The query */
    private $text                   = '';
    private $textParts              = array();
    private $textSentences          = array();
    private $dates                  = array();
    private $figures                = array();
    private $urls                   = array();

    /* The processed word map */
    private $wordMap                = array();

    /* Libraries' paths */
    private $stopwordsPath          = 'lib/stopwords.txt';
    private $nounsPath              = 'lib/nouns.txt';
    private $prenomsPath            = 'lib/prenames.csv';
    private $surnamesPath           = 'lib/surnames.txt';

    private $legaltermsPath         = 'lib/legalwords.txt';
    private $citiesPath             = 'lib/cities.csv';
    private $countriesPath          = 'lib/countries.txt';

    private $commonLawPath          = 'lib/terms_law.csv';
    private $financialTermsPath     = 'lib/terms_financial.csv';
    private $marketingTermsPath     = 'lib/terms_marketing.csv';
    private $technicalTermsPath     = 'lib/terms_technical.csv';
    private $businessTermsPath      = 'lib/terms_business.csv';

    private $jobTitlesPath          = 'lib/jobtitles.csv';
    private $verbsPath              = 'lib/verbs.csv';

    private $documentDefsPath       = 'lib/document-definitions.txt';

    /* Loaded libraries */
    private $stopwords              = array();
    private $nouns                  = array();
    private $prenoms                = array();
    private $surnames               = array();
    private $cities                 = array();
    private $countries              = array();

    private $commonLawTerms         = array();
    private $legalSingles           = array();
    private $legalDoubles           = array();
    private $legalTriples           = array();

    private $financialTerms         = array();
    private $financeSingles         = array();
    private $financeDoubles         = array();
    private $financeTriples         = array();

    private $technicalTerms         = array();
    private $technicalSingles       = array();
    private $technicalDoubles       = array();
    private $technicalTriples       = array();

    private $marketingTerms         = array();
    private $marketingSingles       = array();
    private $marketingDoubles       = array();
    private $marketingTriples       = array();

    private $businessTerms          = array();
    private $businessSingles        = array();
    private $businessDoubles        = array();
    private $businessTriples        = array();

    private $jobtitles              = array();
    private $verbs                  = array();

    private $documentDefinitions    = array();

    private $salutations            = ['mr', 'mrs', 'miss', 'ms', 'dr', 'prof', 'professor', 'lady', 'sir', 'lord', 'judge', 'dame', 'president', 'mr.', 'mrs.', 'miss.', 'ms.', 'dr.', 'prof.', 'professor.', 'lady.', 'sir.', 'lord.', 'judge.', 'dame.', 'president.'];

    private $months                 = ['january', 'jan', 'february', 'feb', 'march', 'mar', 'april', 'apr', 'may', 'june', 'jun', 'july', 'jul', 'august', 'aug', 'september', 'sep', 'sept', 'october', 'oct', 'november', 'nov', 'december', 'dec'];

    private $companyAffixes         = ['limited', 'ltd', 'llc', 'corp', 'corporation', 'incorporated', 'company', 'association', 'spa', 'foundation', 'fund', 'institute', 'club', 'society'];

    private $currencySymbols        = ['£', '$', '€'];
    private $currencyNouns          = ['pound', 'dollar', 'euro'];


    /**
    *
    * Constructor
    *
    */
    public function __construct() {

        self::loadStopwords();
        self::loadNouns();
        self::loadPrenoms();
        self::loadSurnames();
        self::loadCities();
        self::loadCountries();
        self::loadJobTitles();
        self::loadVerbs();

        self::loadCommonLawTerms();
        self::loadFinancialTerms();
        self::loadTechnicalTerms();
        self::loadMarketingTerms();
        self::loadBusinessTerms();

        self::loadDocumentDefinitions();

    }


    /* Remove punctuation and cast to lower case */
    public function cleanTextPart(string $textPart): string {

        $textPart = mb_strtolower($textPart);
        $textPart = str_replace("’", "'", $textPart);

        $replace = array(
            "‘" => "'",
            "’" => "'",
            "”" => '"',
            "“" => '"',
            "–" => "-",
            "—" => "-",
            "…" => "&#8230;",
        );

        foreach($replace as $k => $v) {
            $textPart = str_replace($k, $v, $textPart);
        }

        /* Is this a currency value? */
        if (strpos($textPart, '$') !== false || strpos($textPart, '£') !== false || strpos($textPart, '€') !== false) {
            $textPart = preg_replace('/[^\w\s\-£€$.]/', '', $textPart);
        }

        /* Remove punctuation */
        $textPart = preg_replace('/[^a-zA-Z0-9\s\\/.]/', '', $textPart);

        return $textPart;

    }


    /* Load a collection from a text file */
    public function collectionFromFile(string $filePath): array {

      $items = self::loadTerms(__DIR__ . '/' . $filePath, '/(\r\n|\n|\r)/');

      return array_merge($items['singles'], $items['doubles'], $items['triples']);

    }


    /* Loads stop-word list */
    private function loadStopwords(): array {

        $stopwords = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->stopwordsPath));
        $this->stopwords = array_map('strtolower', $stopwords);

        return $stopwords;

    }


    /* Load nouns */
    private function loadNouns(): array {

        $nouns = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->nounsPath));
        $this->nouns = array_map('strtolower', $nouns);

        return $nouns;

    }


    /* Load prenoms */
    private function loadPrenoms(): array {

        $prenoms = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->prenomsPath));
        $this->prenoms = array_unique(array_map('strtolower', $prenoms));

        return $prenoms;

    }


    /* Load surnames */
    private function loadSurnames(): array {

        $surnames = preg_split('/\s+/', file_get_contents(__DIR__ . '/' . $this->surnamesPath));
        $this->surnames = array_map('strtolower', $surnames);

        return $surnames;

    }


    /* Load cities */
    private function loadCities(): array {

        $cities = preg_split('/(\r\n|\n|\r)/', file_get_contents(__DIR__ . '/' . $this->citiesPath));
        $this->cities = array_map('strtolower', $cities);

        return $cities;

    }


    /* Load cities */
    private function loadCountries(): array {

        $countries = preg_split('/(\r\n|\n|\r)/', file_get_contents(__DIR__ . '/' . $this->countriesPath));
        $this->countries = array_map('strtolower', $countries);

        return $countries;

    }


    /**
    *
    * Load a terms dataset
    *
    */
    private function loadTerms(string $termsPath, string $splitRegex = '/(\r\n|\n|\r)/'): array {

      $terms = array();

      $theTerms = preg_split($splitRegex, file_get_contents($termsPath));
      $theTerms = array_map('strtolower', $theTerms);

      /* Extracts single-part terms */
      $termsSingles = array_filter($theTerms, function($term) {

          if (count(explode(' ', $term)) == 1) {
              return true;
          }

      });

      /* Extracts two-part terms */
      $termsDoubles = array_filter($theTerms, function($term) {

          if (count(explode(' ', $term)) == 2) {
              return true;
          }

      });

      /* Extracts three-part terms */
      $termsTriples = array_filter($theTerms, function($term) {

          if (count(explode(' ', $term)) == 3) {
              return true;
          }

      });

      $terms['singles'] = $termsSingles;
      $terms['doubles'] = $termsDoubles;
      $terms['triples'] = $termsTriples;

      return $terms;

    }


    /* Load common-law terms */
    private function loadCommonLawTerms(): array {

      $terms = self::loadTerms(__DIR__ . '/' . $this->commonLawPath, '/(\r\n|\n|\r)/');

      $this->commonLawTerms = array_merge($terms['singles'], $terms['doubles'], $terms['triples']);

      $this->legalSingles = $terms['singles'];
      $this->legalDoubles = $terms['doubles'];
      $this->legalTriples = $terms['triples'];

      return $terms;

    }


    /* Load finance terms */
    private function loadFinancialTerms(): array {

      $terms = self::loadTerms(__DIR__ . '/' . $this->financialTermsPath, '/(\r\n|\n|\r)/');

      $this->financialTerms = array_merge($terms['singles'], $terms['doubles'], $terms['triples']);

      $this->financeSingles = $terms['singles'];
      $this->financeDoubles = $terms['doubles'];
      $this->financeTriples = $terms['triples'];

      return $terms;

    }


    /* Load technical terms */
    private function loadTechnicalTerms(): array {

      $terms = self::loadTerms(__DIR__ . '/' . $this->technicalTermsPath, '/(\r\n|\n|\r)/');

      $this->technicalTerms = array_merge($terms['singles'], $terms['doubles'], $terms['triples']);

      $this->technicalSingles = $terms['singles'];
      $this->technicalDoubles = $terms['doubles'];
      $this->technicalTriples = $terms['triples'];

      return $terms;

    }


    /* Load marketing terms */
    private function loadMarketingTerms(): array {

      $terms = self::loadTerms(__DIR__ . '/' . $this->marketingTermsPath, '/(\r\n|\n|\r)/');

      $this->marketingTerms = array_merge($terms['singles'], $terms['doubles'], $terms['triples']);

      $this->marketingSingles = $terms['singles'];
      $this->marketingDoubles = $terms['doubles'];
      $this->marketingTriples = $terms['triples'];

      return $terms;

    }


    /* Load business terms */
    private function loadBusinessTerms(): array {

      $terms = self::loadTerms(__DIR__ . '/' . $this->businessTermsPath, '/(\r\n|\n|\r)/');

      $this->businessTerms = array_merge($terms['singles'], $terms['doubles'], $terms['triples']);

      $this->businessSingles = $terms['singles'];
      $this->businessDoubles = $terms['doubles'];
      $this->businessTriples = $terms['triples'];

      return $terms;

    }


    /* Loads job titles */
    private function loadJobTitles():array {

        $jobtitles = preg_split('/(\r\n|\n|\r)/', file_get_contents(__DIR__ . '/' . $this->jobTitlesPath));
        $this->jobtitles = array_map('strtolower', $jobtitles);

        return $jobtitles;

    }


    /* Loads verbs */
    private function loadVerbs():array {

        $verbs = preg_split('/(\r\n|\n|\r)/', file_get_contents(__DIR__ . '/' . $this->verbsPath));
        $this->verbs = array_map('strtolower', $verbs);

        return $verbs;

    }


    /* Load contract definitions */
    private function loadDocumentDefinitions(): array {

        $documentDefs = file_get_contents(__DIR__ . '/' . $this->documentDefsPath);
        $documentDefs = unserialize($documentDefs);

        $this->documentDefinitions = $documentDefs;

        return $documentDefs;

    }


    /* Remove stopwords from textParts */
    private function filterStopwords(array $words): array {

        $cleanWords = array_diff($words, $this->stopwords);

        return $cleanWords;

    }


    /**
    *
    * Checks given word against the stopwords list
    *
    */
    public function isStopword(string $word): bool {

        if (array_search(strtolower($word), $this->stopwords) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Checks if the given string contains a stopword
    *
    */
    public function containsStopword(string $text): bool {

        $text = strtolower($text);

        foreach($this->stopwords as $stop) {
            if (strlen($stop)>0) {

                if (preg_match("/\b" . $stop . "\b/i", $text)) {
                    return true;
                }

            }
        }

        return false;

    }


    /**
    *
    * Checks given word against the nouns list
    *
    */
    public function isNoun(string $word): bool {

        if (array_search($word, $this->nouns) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Checks if the given string contains a noun
    *
    */
    public function containsNoun(string $text): bool {

        $text = strtolower($text);

        foreach($this->nouns as $noun) {
            if (strlen($noun)>0) {

                if (preg_match("/\b" . $noun . "\b/i", $text)) {
                    return true;
                }

            }
        }

        return false;

    }


    /**
    *
    * Checks given word against the verbs list
    *
    */
    public function isVerb(string $word): bool {

        if (array_search(strtolower($word), $this->verbs) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Checks given word against the prenom list
    *
    */
    public function isPrenom(string $word): bool {

        if (array_search($word, $this->prenoms) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Checks given word against the prenom list
    *
    */
    public function isSurname(string $word): bool {

        if (array_search($word, $this->surnames) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a salutation?
    *
    */
    public function isSalutation(string $word): bool {

        if (array_search($word, $this->salutations) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a place name?
    *
    */
    public function isPlace(string $word): bool {

        $places = array_merge($this->countries, $this->cities);

        if (array_search($word, $places) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a month?
    *
    */
    public function isMonth(string $word):bool {

        if (array_search($word, $this->months) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a year?
    *
    */
    public function isYear(string $word):bool {

        if (strlen($word) == 4 && is_numeric($word) && ($word > 1800 && $word < 3000)) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a day reference
    *
    */
    public function isDay(string $word):bool {

        $word = preg_replace('/\D/', '', $word);

        if (is_numeric($word) && strlen($word) < 3) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a full date string?
    *
    */
    public function isDate(string $word):bool {

        if ($word == 'a') {
            return false;
        }

        if (date_parse($word)['error_count'] == 0) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a possible numeric date (x/x/x or x-x-x)?
    *
    */
    public function isNumericDate(string $word):bool {

        $word = str_replace('/', '-', $word);

        if (count(explode('-', $word))>1) {

            $parts = explode('-', $word);

            if (count($parts) === count(array_filter($parts, 'is_numeric'))) {
                return true;
            } else {
                return false;
            }

        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a possible numeric figure ($3000)?
    *
    */
    public function isNumericFigure(string $word):string {

        if (array_search(mb_substr($word, 0, 1), $this->currencySymbols) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Does the given string contain a common company affix?
    *
    */
    public function containsCompanyAffix(string $string): bool {

        foreach($this->companyAffixes as $affix) {

            if (strpos($string, $affix) !== FALSE) {
                return true;
            }

        }

        return false;

    }


    /**
    *
    * Does the given string contain a legal term
    *
    */
    // public function containsLegalTerm(string $string): bool {
    //
    //     foreach($this->commonLawTerms as $term) {
    //
    //         if (strlen($term)>0) {
    //
    //             if (preg_match("/\b" . preg_quote($term, '/') . "\b/i", $string)) {
    //                 return true;
    //             }
    //
    //         }
    //
    //     }
    //
    //     return false;
    //
    // }

    public function containsLegalTerm(string $string): bool {

        foreach($this->commonLawTerms as $term) {

            if (strpos($string, $term) !== FALSE) {
                return true;
            }

        }

        return false;

    }


    /**
    *
    * Is the given word a possible legal term
    *
    */
    public function isLegalTerm(string $word): bool {

        if (array_search(strtolower($word), $this->commonLawTerms) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Is the given word a possible job title
    *
    */
    public function isJobtitle(string $word):string {

        if (array_search($word, $this->jobtitles) !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    public function isEmail(string $word): bool {

        if (filter_var($word, FILTER_VALIDATE_EMAIL)) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Does the given text possible contain an address?
    *
    */
    public function possibleAddress(string $text):string {



    }


    /**
    *
    * Is the given word a possible URL?
    *
    */
    public function isUrl(string $word): bool {

        if (strpos($word, '://') !== FALSE) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Extracts words beginning with a capital letter
    *
    */
    public function caps(string $text): array {

        $text   = preg_replace("/[\pZ\pC]+/u", " ", $text);
        $words  = preg_split('/\s+/', $text);

        $caps   = array_filter($words, function($w) {

            if (ctype_upper(substr($w, 0, 1))) {
                return true;
            }

        });

        return $caps;

    }


    public function contextualiseEntities(array $entities): array {

        $e = array();

        foreach($entities as $entity) {

            $firstword  = $entity[0];
            $entity     = implode(' ', $entity);

            $entity = array(
                'word'                  => $entity,
                'firstword'             => $firstword,
                'legal'                 => self::isLegalTerm($entity),
                'jobtitle'              => self::isJobtitle($entity),
                'stopword'              => self::isStopword($entity),
                'contains_stopword'     => self::containsStopword($entity),
                'noun'                  => self::isNoun($entity),
                'contains_noun'         => self::containsNoun($entity),
                'place'                 => self::isPlace($entity),
                'month'                 => self::isMonth($entity)
            );

            array_push($e, $entity);

        }

        return $e;

    }


    private function uppercaseCount(array $array): int {

        $count = 0;

        foreach($array as $a) {
            if (ctype_upper($a)) {
                $count++;
            }
        }

        return $count;

    }


    /**
    *
    * Check if word contains punctuation
    *
    */
    private function containsPunctuation(string $text): bool {

        if (strpos($text, ',')) {
            return true;
        }

        if (strpos($text, '.')) {
            return true;
        }

        if (strpos($text, ';')) {
            return true;
        }

        if (strpos($text, ':')) {
            return true;
        }

        return false;

    }


    /**
    *
    * Attempts extraction of possible entities (organisations, company names, etc.) based on capitalisation
    *
    */
    public function capitalisedEntities(array $wordsMap): array {

        $e = array();
        $a = array();

        foreach($wordsMap as $word) {


            /* IF WORD CONTAINS PUNCTUATION, KILL IT */
            if (self::containsPunctuation($word['word_dirty']) === TRUE) {
                continue;
            }

            /* l U l = U wins */
            if (!$word['capitalised_l1'] && $word['capitalised'] && !$word['capitalised_r1']) {
                array_push($e, array(
                    $word['word']
                ));
            }

            /* l U U l = U U wins */
            if (!$word['capitalised_l1'] && $word['capitalised'] && $word['capitalised_r1'] && !$word['capitalised_r2']) {
                array_push($e, array(
                    $word['word'],
                    $word['r1']
                ));
            }

            if (($word['capitalised_l1'] && self::isStopword($word['l1'])) && $word['capitalised'] && $word['capitalised_r1'] && !$word['capitalised_r2']) {
                array_push($e, array(
                    $word['word'],
                    $word['r1']
                ));
            }

            /* l U U U l = U U U wins */
            if (!$word['capitalised_l1'] && $word['capitalised'] && $word['capitalised_r1'] && $word['capitalised_r2'] && !$word['capitalised_r3']) {
                array_push($e, array(
                    $word['word'],
                    $word['r1'],
                    $word['r2']
                ));
            }

            /* l U U U = U U U wins */
            if (!$word['capitalised_l1'] && $word['capitalised'] && $word['capitalised_r1'] && $word['capitalised_r2'] && $word['capitalised_r3']) {
                array_push($e, array(
                    $word['word'],
                    $word['r1'],
                    $word['r2'],
                    $word['r3']
                ));
            }

            /* l U 'of' U l = U 'of' U wins */
            if (!$word['capitalised_l1'] && $word['capitalised'] && $word['r1'] == 'of' && $word['capitalised_r2']) {
                array_push($e, array(
                    $word['word'],
                    'of',
                    $word['r2']
                ));
            }



        }

        /* Filter any entity matches that have one word uppercase and at least one other word that isn't */
        $e = array_filter($e, function($ent) {

            $ucaseCount = self::uppercaseCount($ent);

            if ($ucaseCount === 0) {
                return true;
            }

            if ($ucaseCount === count($ent)) {
                return true;
            }

        });

        return $e;

    }


    public function possibleEntities(array $words): array {

        $right = array_filter($words, function($word) {

            if ((self::isPrenom($word['word']) || self::isSurname($word['word'])) && $word['r1'] == 'and') {
                return true;
            }

        });

        $left = array_filter($words, function($word) {

            if ((self::isPrenom($word['word']) || self::isSurname($word['word'])) && $word['l1'] == 'and') {
                return true;
            }

        });

        $w = array();

        foreach($right as $r) {
            array_push($w, $r['r2']);
        }

        foreach($left as $l) {
            array_push($w, $l['l2']);
        }

        return $w;

    }


    /**
    *
    * Returns all loaded nouns
    *
    */
    public function nouns(): array {
      return $this->nouns;
    }


    /**
    *
    * Returns all loaded stopwords
    *
    */
    public function stopwords(): array {
      return $this->stopwords;
    }


    /**
    *
    * Returns all loaded verbs
    *
    */
    public function verbs(): array {
      return $this->verbs;
    }


    /* Remove any text parts that are single consonants or two consonants together, bar exceptions (i, a)  */
    public function filterGroupedConsonants(array $parts): array {

        $parts = array_filter($parts, function($p) {

            if (strlen($p) > 2) {
                return true;
            }

            if (strlen($p) == 1 && preg_match('/([bcdfghjklmnpqrstvwxyz]{1}+)/', $p) !== 1) {
                return true;
            }

            if (strlen($p) == 2 && preg_match('/([bcdfghjklmnpqrstvwxyz]{2}+)/', $p) !== 1) {
                return true;
            }

        });

        return $parts;

    }


    /**
    *
    * Breaks down a given block of text and runs various cleaning routines on it, returning an array of text parts
    *
    */
    public function cleanseTextBlock(string $text): array {

        /* Replace all sorts of whitespace with single 'normal' space */
        $text = preg_replace("/[\pZ\pC]+/u", " ", $text);
        $text = str_replace(':', ' ', $text);

        $this->text = $text;

        /* Ensure textParts is empty */
        $textParts = array();

        /* Explode the text and push each part - cleaned of punctuation and cast to lower case - into the textParts array */
        $parts = preg_split('/\s+/', $text);

        /* Clean each text-part */
        array_walk($parts, function(&$part, &$key) {
            $part = self::cleanTextPart($part);
        });

        /* Remove any text parts that are single consonants or two consonants together, bar exceptions (i, a)  */
        $parts = self::filterGroupedConsonants($parts);

        foreach($parts as $part) {
            array_push($textParts, $part);
        }

        return $textParts;

    }


    /**
    *
    * Prepares the text to be analysed
    *
    */
    public function setText(string $text): bool {

        if (strlen($text) == 0) {
            return false;
        }

        /* Extract sentences from the text and push those into the textSentences array */
        $sentences = explode("\n", $text);
        $this->textSentences = $sentences;

        $text = self::cleanseTextBlock($text);

        foreach($text as $part) {
            array_push($this->textParts, $part);
        }

        return true;

    }


    public function cleanText(string $text): string {
        return self::cleanTextPart($text);
    }


    /**
    *
    * Returns the entire text body
    *
    */
    public function text(): string {
        return $this->text;
    }


    /**
    *
    * Returns textParts with stopwords removed
    *
    */
    public function textMinusStopwords(array $words): array {
        return self::filterStopwords($words);
    }


    /**
    *
    * Analyses each sentence extracted from the text
    *
    */
    public function analyseSentences(): array {

        $s = array();

        foreach($this->textSentences as $sentence) {

            $sentenceParts = preg_split('/\s+/', $sentence);
            $sentenceParts = self::filterStopwords($sentenceParts);

            $cleanSp = array();

            foreach($sentenceParts as $sp) {
                array_push($cleanSp, self::cleanTextPart($sp));
            }

            $cleanSpText = '';

            foreach($cleanSp as $p) {
                $cleanSpText .= $p . ' ';
            }

            $sentenceMap = self::mapBlock($sentence);

            $a = array(
                'sentence'          => $sentence,
                'map'               => $sentenceMap,
                'bag_of_words'      => self::bagOfWords($cleanSp),
                'bag_of_legal'      => self::bagOfLegalTerms($cleanSp),
                'legal_double'      => self::legalDoubleTerms($sentenceMap),
                'legal_triple'      => self::legalTripleTerms($sentenceMap),
                'finance_terms'     => self::bagOfWords(self::financeTerms($sentenceMap))
            );

            array_push($s, $a);

        }

        return $s;

    }


    /**
    *
    * Analyses the set text
    *
    */
    public function analyseText(): array {

        $text = $this->text;

        $textParts = preg_split('/\s+/', $text);
        $textParts = self::filterStopwords($textParts);

        $cleanTp = array();

        foreach($textParts as $tp) {
            array_push($cleanTp, self::cleanTextPart($tp));
        }

        $cleanTpText = '';

        foreach($cleanTp as $p) {
            $cleanTpText .= $p . ' ';
        }

        $textMap = self::mapBlock($text);

        $a = array(
            'text'              => $text,
            'text_clean'        => self::cleanseTextBlock($text),
            'map'               => $textMap,
            'bag_of_words'      => self::bagOfWords($cleanTp),
            'bag_of_legal'      => self::bagOfLegalTerms($cleanTp),
            'legal_double'      => self::legalDoubleTerms($textMap),
            'legal_triple'      => self::legalTripleTerms($textMap),
            'finance_terms'     => self::bagOfWords(self::financeTerms($textMap))
        );

        return $a;

    }


    public function cities() {
        return $this->cities;
    }


    /**
    *
    * Returns a 'score' of a bag of words, by totalling up each word count figure in the provided bag of words
    *
    */
    public function scoreOfBow(array $bagOfWords): int {

      if (count($bagOfWords)>0) {

        $count        = count($bagOfWords);
        $pointsTotal  = 0;

        foreach($bagOfWords as $key => $val) {
          $pointsTotal = $pointsTotal + $val;
        }

        return $pointsTotal;

      } else {
        return 0;
      }

    }


    /**
    *
    * Returns a Bag of Words (BOW) of the given array, which is first filtered for stopwords
    *
    */
    public function bagOfWords(array $words): array {

        // $bow = array_count_values(self::filterStopwords($this->textParts));
        $bow = array_count_values(self::filterStopwords($words));
        arsort($bow);

        return $bow;

    }


    /**
    *
    * Returns a Bag of Words (BOW) of the given array
    *
    */
    public function bagOfWordsUnfiltered(array $words): array {

        $bow = array_count_values($words);
        arsort($bow);

        return $bow;

    }


    /**
    *
    * Returns a Bag of Words (BOW) of the given array
    *
    */
    public function bagOfLegalTerms(array $words): array {

        $legal = self::legalTerms($words);

        $wordsLegal = array();

        foreach($words as $word) {

            $word = self::cleanTextPart($word);

            if (array_search($word, $legal) !== FALSE) {
                array_push($wordsLegal, $word);
            }

        }

        $bolw = array_count_values($wordsLegal);
        arsort($bolw);

        return $bolw;

    }


    /**
    *
    * Extracts email addresses from strings
    *
    */
    public function extractEmails(string $text): array {

        $e = array();

        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $text, $e);

        return $e;

    }


    private function isCapitalised(string $text): bool {

        if (ctype_upper(substr($text, 0, 1))) {
            return TRUE;
        } else {
            return FALSE;
        }

    }


    private function isUppercase(string $text): bool {

        if (ctype_upper($text)) {
            return TRUE;
        } else {
            return FALSE;
        }

    }


    /**
    *
    * Generate a word map of a given block of text
    *
    */
    public function mapBlock(string $text): array {

        $wordMap    = array();
        $textParts  = array();

        $text       = preg_replace("/[\pZ\pC]+/u", " ", $text);

        $parts      = preg_split('/\s+/', trim($text));

        foreach($parts as $part) {
            array_push($textParts, $part);
        }

        /* Build the primary map, of every word */
        foreach($textParts as $key => $word) {

            if ($word && strlen($word)>0) {

                $thisWordMap = array();

                /* Is this first word in block? */
                if ($key == 0) {
                    $thisWordMap['firstword'] = TRUE;
                } else {
                    $thisWordMap['firstword'] = FALSE;
                }

                /* Get the word's neighbours to the left three steps and to the right three steps */
                $l1 = $textParts[$key-1] ?? '';
                $l2 = $textParts[$key-2] ?? '';
                $l3 = $textParts[$key-3] ?? '';

                $r1 = $textParts[$key+1] ?? '';
                $r2 = $textParts[$key+2] ?? '';
                $r3 = $textParts[$key+3] ?? '';

                /* This goes up here before the text part is cleaned (and cast to lower case) */
                $thisWordMap['capitalised']         = self::isCapitalised($word);
                $thisWordMap['uppercase']           = self::isUppercase($word);

                $thisWordMap['capitalised_l1']      = self::isCapitalised($l1);
                $thisWordMap['uppercase_l1']        = self::isUppercase($l1);

                $thisWordMap['capitalised_l2']      = self::isCapitalised($l2);
                $thisWordMap['uppercase_l2']        = self::isUppercase($l2);

                $thisWordMap['capitalised_l3']      = self::isCapitalised($l3);
                $thisWordMap['uppercase_l3']        = self::isUppercase($l3);

                $thisWordMap['capitalised_r1']      = self::isCapitalised($r1);
                $thisWordMap['uppercase_r1']        = self::isUppercase($r1);

                $thisWordMap['capitalised_r2']      = self::isCapitalised($r2);
                $thisWordMap['uppercase_r2']        = self::isUppercase($r2);

                $thisWordMap['capitalised_r3']      = self::isCapitalised($r3);
                $thisWordMap['uppercase_r3']        = self::isUppercase($r3);

                $word_dirty                         = $word;
                $word                               = self::cleanTextPart($word);

                $thisWordMap['l1']                  = self::cleanTextPart($l1);
                $thisWordMap['l2']                  = self::cleanTextPart($l2);
                $thisWordMap['l3']                  = self::cleanTextPart($l3);

                $thisWordMap['word']                = $word;
                $thisWordMap['word_dirty']          = $word_dirty;
                $thisWordMap['key']                 = $key;

                $thisWordMap['r1']                  = self::cleanTextPart($r1);
                $thisWordMap['r2']                  = self::cleanTextPart($r2);
                $thisWordMap['r3']                  = self::cleanTextPart($r3);

                /* Make some first-pass analysis on this word */
                $thisWordMap['prenom']              = self::isPrenom($word);
                $thisWordMap['surname']             = self::isSurname($word);
                $thisWordMap['stopword']            = self::isStopword($word);
                $thisWordMap['noun']                = self::isNoun($word);
                $thisWordMap['salutation']          = self::isSalutation($word);
                $thisWordMap['place']               = self::isPlace($word);
                $thisWordMap['year']                = self::isYear($word);
                $thisWordMap['month']               = self::isMonth($word);
                $thisWordMap['day']                 = self::isDay($word);
                $thisWordMap['numericdate']         = self::isNumericDate($word);
                $thisWordMap['numericfigure']       = self::isNumericFigure($word_dirty);
                $thisWordMap['url']                 = self::isUrl($word);
                $thisWordMap['email']               = self::isEmail($word);

                array_push($wordMap, $thisWordMap);

            }

        }

        return $wordMap;

    }


    /**
    *
    * Generates the primary word map array
    *
    */
    public function mapCreate(): bool {

        if (count($this->textParts) == 0) {
            return false;
        }

        $this->wordMap = array();

        /* Build the primary map, of every word */
        foreach($this->textParts as $key => $word) {

            $thisWordMap = array();

            $l1 = $this->textParts[$key-1] ?? '';
            $l2 = $this->textParts[$key-2] ?? '';
            $l3 = $this->textParts[$key-3] ?? '';

            $r1 = $this->textParts[$key+1] ?? '';
            $r2 = $this->textParts[$key+2] ?? '';
            $r3 = $this->textParts[$key+3] ?? '';

            $thisWordMap['l1'] = $l1;
            $thisWordMap['l2'] = $l2;
            $thisWordMap['l3'] = $l3;

            $thisWordMap['word'] = $word;
            $thisWordMap['key'] = $key;

            $thisWordMap['r1'] = $r1;
            $thisWordMap['r2'] = $r2;
            $thisWordMap['r3'] = $r3;

            $thisWordMap['prenom']          = self::isPrenom($word);
            $thisWordMap['surname']         = self::isSurname($word);
            $thisWordMap['stopword']        = self::isStopword($word);
            $thisWordMap['noun']            = self::isNoun($word);
            $thisWordMap['salutation']      = self::isSalutation($word);
            $thisWordMap['place']           = self::isPlace($word);
            $thisWordMap['year']            = self::isMonth($word);
            $thisWordMap['month']           = self::isMonth($word);
            $thisWordMap['day']             = self::isDay($word);
            $thisWordMap['numericdate']     = self::isNumericDate($word);
            $thisWordMap['numericfigure']   = self::isNumericFigure($word);
            $thisWordMap['url']             = self::isUrl($word);

            array_push($this->wordMap, $thisWordMap);

        }

        /* Remove stopwords that don't also fall in the prenom/surname lists */
        // $this->wordMap = array_filter($this->wordMap, function($var) {
        //
        //     if ($var['stopword'] === FALSE) {
        //         return true;
        //     } else {
        //
        //         if ($var['prenom'] === TRUE || $var['surname'] === TRUE) {
        //             return true;
        //         }
        //
        //     }
        //
        // });

        return true;

    }

    public function map(): array {
        return $this->wordMap;
    }


    /**
    *
    * Returns possible places
    *
    */
    public function places(string $text): array {

        $a = array();

        $textLower = strtolower($text);

        $places = array_merge($this->countries, $this->cities);

        foreach($places as $place) {

            if ($place) {

                if (strpos($textLower, $place) !== FALSE) {
                    array_push($a, ucwords($place));
                }

            }

        }

        return array_unique($a);

    }


    /**
    *
    * Returns all accounting terms
    *
    */
    public function accountingTermsFromText(string $text): array {

        $a          = array();

        $textLower  = strtolower($text);

        $places     = array_merge($this->countries, $this->cities);

        foreach($places as $place) {

            if ($place) {

                if (strpos($textLower, $place) !== FALSE) {
                    array_push($a, ucwords($place));
                }

            }

        }

        return array_unique($a);

    }


    /**
    *
    * Returns all combined (single, double, triple) finance terms
    *
    */
    public function financeTerms(array $words): array {

      $singles    = self::matchSingleTerms($words, $this->financeSingles);
      $doubles    = self::matchDoubleTerms($words, $this->financeDoubles);
      $triples    = self::matchTripleTerms($words, $this->financeTriples);

      return array_merge($singles, $doubles, $triples);

    }


    /**
    *
    * Returns all combined (single, double, triple) technical terms
    *
    */
    public function technicalTerms(array $words): array {

      $singles    = self::matchSingleTerms($words, $this->technicalSingles);
      $doubles    = self::matchDoubleTerms($words, $this->technicalDoubles);
      $triples    = self::matchTripleTerms($words, $this->technicalTriples);

      return array_merge($singles, $doubles, $triples);

    }


    /**
    *
    * Returns all combined (single, double, triple) marketing terms
    *
    */
    public function marketingTerms(array $words): array {

      $singles    = self::matchSingleTerms($words, $this->marketingSingles);
      $doubles    = self::matchDoubleTerms($words, $this->marketingDoubles);
      $triples    = self::matchTripleTerms($words, $this->marketingTriples);

      return array_merge($singles, $doubles, $triples);

    }


    /**
    *
    * Returns all combined (single, double, triple) business terms
    *
    */
    public function businessTerms(array $words): array {

      $singles    = self::matchSingleTerms($words, $this->businessSingles);
      $doubles    = self::matchDoubleTerms($words, $this->businessDoubles);
      $triples    = self::matchTripleTerms($words, $this->businessTriples);

      return array_merge($singles, $doubles, $triples);

    }


    public function allFinanceTerms(): array {

        $singles    = $this->financeSingles;
        $doubles    = $this->financeDoubles;
        $triples    = $this->financeTriples;

        $multis     = array_merge($doubles, $triples);

        return array_merge($singles, $multis);

    }


    public function allBusinessTerms(): array {

        $singles    = $this->businessSingles;
        $doubles    = $this->businessDoubles;
        $triples    = $this->businessTriples;

        return array_merge($singles, $doubles, $triples);

    }


    public function allLegalTerms(): array {

        $singles    = $this->legalSingles;
        $doubles    = $this->legalDoubles;
        $triples    = $this->legalTriples;

        return array_merge($singles, $doubles, $triples);

    }


    public function allTechnicalTerms(): array {

        $singles    = $this->technicalSingles;
        $doubles    = $this->technicalDoubles;
        $triples    = $this->technicalTriples;

        return array_merge($singles, $doubles, $triples);

    }


    /**
    *
    * Returns all legal terms that contain only one word
    *
    */
    public function matchSingleTerms(array $words, array $against): array {

        /* Filter singles against the given words array */
        $s_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'], $against) !== FALSE) {
                array_push($s_matches, $w['word']);
            }

        }

        return $s_matches;

    }


    /**
    *
    * Returns all legal terms that contain only one word
    *
    */
    public function legalSingleTerms(array $words): array {

        /* Filter singles against the given words array */
        $s_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'], $this->legalSingles) !== FALSE) {
                array_push($s_matches, $w['word']);
            }

        }

        return $s_matches;

    }


    /**
    *
    * Returns all terms that are in two parts 'A B' against an array of words
    *
    */
    public function matchDoubleTerms(array $words, array $against): array {

        /* Filter doubles against the given words array */
        $d_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'] . ' ' . $w['r1'], $against) !== FALSE) {
                array_push($d_matches, $w['word'] . ' ' . $w['r1']);
            }

        }

        return $d_matches;

    }


    /**
    *
    * Returns all legal terms that are in two parts 'A B'
    *
    */
    public function legalDoubleTerms(array $words): array {

        /* Filter doubles against the given words array */
        $d_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'] . ' ' . $w['r1'], $this->legalDoubles) !== FALSE) {
                array_push($d_matches, $w['word'] . ' ' . $w['r1']);
            }

        }

        return $d_matches;

    }


    /**
    *
    * Returns all terms that are in two parts 'A B C' against an array of words
    *
    */
    public function matchTripleTerms(array $words, array $against): array {

        /* Filter triples against the given words array */
        $t_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'] . ' ' . $w['r1'] . ' ' . $w['r2'], $against) !== FALSE) {
                array_push($t_matches, $w['word'] . ' ' . $w['r1'] . ' ' . $w['r2']);
            }

        }

        return $t_matches;

    }


    /**
    *
    * Returns all legal terms that are in three parts 'A B C'
    *
    */
    public function legalTripleTerms(array $words): array {

        /* Filter triples against the given words array */
        $t_matches = array();

        foreach($words as $w) {

            if (array_search($w['word'] . ' ' . $w['r1'] . ' ' . $w['r2'], $this->legalTriples) !== FALSE) {
                array_push($t_matches, $w['word'] . ' ' . $w['r1'] . ' ' . $w['r2']);
            }

        }

        return $t_matches;

    }


    /**
    *
    * Returns a combined array with legal single- double- and triple-terms
    *
    */
    public function legalExtended(array $words): array {

        $s = self::matchSingleTerms($words, $this->legalSingles);
        $d = self::matchDoubleTerms($words, $this->legalDoubles);
        $t = self::matchTripleTerms($words, $this->legalTriples);

        return array_merge($d, $t, $s);

    }


    /**
    *
    * Returns a combined array with single- double- and triple-terms
    *
    */
    public function collectionExtended(array $words, array $against): array {

        $s = self::matchSingleTerms($words, $against);
        $d = self::matchDoubleTerms($words, $against);
        $t = self::matchTripleTerms($words, $against);

        return array_merge($d, $t, $s);

    }


    /**
    *
    * Checks for a double-term conjugation in the array of words given
    * e.g. 'chief executive' - word.r1 equalling 'executive' and word.word equalling 'chief' would be a match
    *
    */
    public function checkDoubleTerm(string $word1, string $word2, array $words): array {

        $c = array_filter($words, function($word) use($word1, $word2) {

            if ($word['word'] == $word1 && $word['r1'] == $word2) {
                return true;
            }

        });

        return $c;

    }

    /**
    *
    * Checks for a triple-term conjugation in the array of words given
    * e.g. 'date of issuance' - word.r2 equalling 'issuance', word.r1 equalling 'of' and word.word equalling 'date' would be a match
    *
    * TODO Refactor this so it can check double, triple, and longer conjugations with a single function
    *
    */
    public function checkTripleTerm(string $word1, string $word2, string $word3, array $words): array {

        $c = array_filter($words, function($word) use($word1, $word2, $word3) {

            if ($word['word'] == $word1 && $word['r1'] == $word2 && $word['r2'] == $word3) {
                return true;
            }

        });

        return $c;

    }


    /**
    *
    * Returns possible legal terms
    *
    */
    public function legalTerms(array $words): array {
        return array_intersect($this->commonLawTerms, $words);
    }


    public function singleLegalTerms(array $words): array {
        return array_intersect($this->legalSingles, $words);
    }


    /**
    *
    * Returns possible legal terms from given text
    *
    */
    public function legalTermsFromText(string $text): array {

        $text = strtolower($text);
        $termsFound = array();

        foreach($this->commonLawTerms as $term) {

            if (strpos($text, $term) !== FALSE) {
                array_push($termsFound, $term);
            }

        }

        return $termsFound;

    }


    public function legalTermsExtended(): array {
        return $this->commonLegalExtended;
    }


    public function lawterms() {
        return $this->commonLawTerms;
    }


    /**
    *
    * Job titles
    *
    */
    public function jobTitles(string $text): array {

        $j = array();

        $textLower = strtolower($text);

        foreach($this->jobtitles as $jobtitle) {

            if (strpos($textLower, $jobtitle) !== FALSE) {
                array_push($j, $jobtitle);
            }

        }

        return array_unique($j);

    }


    /**
    *
    * Attempts human-name extraction from the set text
    *
    */

    /* Looks for the possible name pattern matching Prenom (P) Surname (S) (PS) */
    private function psNameMatches(array $wordMap): array {

        /* Seek possible first names */
        $possibleFirstNames = array_filter($wordMap, function($val) {

            if ($val['prenom']) {
                return true;
            }

        });

        /* For possibleFirstNames, check word r1 to see if it's could be a surname */
        $psMatches = array_filter($possibleFirstNames, function($val) {

            if (self::isSurname($val['r1'])) {

                /* Check both possible prenom and possible surname are not nouns */
                if ((!self::isNoun($val['word']) && self::isNoun($val['r1'])) || (self::isNoun($val['word']) && !self::isNoun($val['r1'])) || (!self::isNoun($val['word']) && !self::isNoun($val['r1']))) {

                    /* Check both possible prenom, possible surname are not stopwords */
                    if (!self::isStopword($val['word']) && !self::isStopword($val['r1'])) {
                        return true;
                    }

                }

            }

        });

        return $psMatches;

    }


    /* Looks for the possible name patterns matching Salutation (S) Prenom (P) Surname (S) (SPS) */
    private function spsNameMatches(array $wordMap): array {

        /* Seek salutations */
        $salutations = array_filter($wordMap, function($val) {

            if ($val['salutation']) {
                return true;
            }

        });

        /* Filter matches in $salutations for a succeeding first name and surname */
        $spsMatches = array_filter($salutations, function($val) {

            if (self::isPrenom($val['r1']) && self::isSurname($val['r2']) && !self::isSalutation($val['r2'])) {
                return true;
            }

        });

        return $spsMatches;

    }


    /* Looks for possible name patterns matching Salutation (S) and Surname (S) (SS) */
    private function ssNameMatches(array $wordMap):array {

        /* Seek salutations */
        $salutations = array_filter($wordMap, function($val) {

            if ($val['salutation']) {
                return true;
            }

        });

        /* Filter matches in $salutations for succeeding surname */
        $ssMatches = array_filter($salutations, function($val) {

            if (self::isSurname($val['r1']) && !self::isSalutation($val['r1'])) {
                return true;
            }

        });

        return $ssMatches;

    }


    /* Looks for possible name patterns matching Salutation (S) Prenom Initial (Pi) and Surname (S) */
    private function sPiSNameMatches(array $wordMap): array {

        /* Seek salutations */
        $salutations = array_filter($wordMap, function($val) {

            if ($val['salutation']) {
                return true;
            }

        });

        /* Filter matches in $salutations for succeeding initial and surname */
        $spisMatches = array_filter($salutations, function($val) {

            if (strlen($val['r1']) == 1 && self::isSurname($val['r2'])) {
                return true;
            }

        });

        return $spisMatches;

    }


    public function names(array $wordMap): array {

        $names = array();

        foreach(self::psNameMatches($wordMap) as $name) {
            array_push($names, array(
                'word'      => $name['word'],
                'r1'        => $name['r1'],
                'r2'        => $name['r2'],
                'r3'        => $name['r3'],
                'process'   => 'psNameMatches',
            ));
        }

        foreach(self::spsNameMatches($wordMap) as $name) {
            array_push($names, array(
                'word'      => $name['word'],
                'r1'        => $name['r1'],
                'r2'        => $name['r2'],
                'r3'        => $name['r3'],
                'process'   => 'spsNameMatches',
            ));
        }

        foreach(self::ssNameMatches($wordMap) as $name) {
            array_push($names, array(
                'word'      => $name['word'],
                'r1'        => $name['r1'],
                'r2'        => $name['r2'],
                'r3'        => $name['r3'],
                'process'   => 'ssNameMatches',
            ));
        }

        foreach(self::sPiSNameMatches($wordMap) as $name) {
            array_push($names, array(
                'word'      => $name['word'],
                'r1'        => $name['r1'],
                'r2'        => $name['r2'],
                'r3'        => $name['r3'],
                'process'   => 'sPiSNameMatches',
            ));
        }

        return $names;

    }


    /**
    *
    * Attempts date extraction from the set text
    *
    */
    public function dates(array $wordMap): array {

        $dates = array();

        /* English-language dates */
        foreach($wordMap as $mapItem) {

            $date = '';

            if ($mapItem['month']) {

                /* It's a month */
                $m = $mapItem['word'];
                $d = '';
                $y = '';

                /* Is l1/l2 a day? */
                if (self::isDay($mapItem['l1'])) {
                    $d = $mapItem['l1'];
                }

                if ($mapItem['l1'] == 'of') {
                    if (self::isDay($mapItem['l2'])) {
                        $d = $mapItem['l2'];
                    }
                }

                /* Is r1 a year? */
                if (self::isYear($mapItem['r1'])) {
                    $y = $mapItem['r1'];
                }

                if ($m && $y || $d && $m) {
                    $date = "$d $m $y";
                }

            }

            if ($mapItem['day']) {

                /* It's a day */
                $d = $mapItem['word'];
                $m = '';
                $y = '';

                /* Is l1/l2 a month? */
                if (self::isMonth($mapItem['l1'])) {
                    $m = $mapItem['l1'];
                }

                if ($mapItem['l1'] == 'the') {
                    if (self::isDay($mapItem['l2'])) {
                        $m = $mapItem['l2'];
                    }
                }

                /* Is r1 a year? */
                if (self::isYear($mapItem['r1'])) {
                    $y = $mapItem['r1'];
                }

                if ($d && $m) {
                    $date = "$d $m $y";
                }

            }

            if ($mapItem['numericdate']) {
                $date = $mapItem['word'];
            }

            if (strlen($date)>0) {

                array_push($dates, array(
                    'date'              => date('l jS \of F Y', strtotime($date)),
                    'date_text'         => $date,
                    'date_timestamp'    => strtotime($date)
                ));

            }

        }

        return $dates;

    }


    /**
    *
    * Attempts figures (money) extraction from the set text
    *
    */
    public function figures(array $wordMap): array {

        $figures = array();

        foreach($wordMap as $mapItem) {

            if ($mapItem['numericfigure']) {
                array_push($figures, $mapItem['word']);
            }

        }

        return $figures;

    }


    /**
    *
    * Attempts URL extraction from the set text
    *
    */
    public function urls(array $wordMap): array {

        $urls = array();

        foreach($wordMap as $mapItem) {

            if ($mapItem['url']) {
                array_push($urls, $mapItem['word']);
            }

        }

        return $urls;

    }


    /**
    *
    * Attempts brands extraction from the set text
    *
    */
    public function brands(): array {

    }


    /**
    *
    * Attempts address extraction from the set text
    *
    */
    public function addresses(): array {

    }


    /**
    *
    * Returns sentences without cleaning or removing stopwords
    *
    */
    public function sentences(): array {
        return $this->textSentences;
    }


    /**
    *
    * Returns clean sentences (cleansed and stopwords removed)
    *
    */
    public function sentencesClean(): array {

        $s = array();

        foreach($this->textSentences as $sentence) {

            $sentenceParts = preg_split('/\s+/', $sentence);
            $sentenceParts = self::filterStopwords($sentenceParts);

            $cleanSp = array();

            foreach($sentenceParts as $sp) {
                array_push($cleanSp, self::cleanTextPart($sp));
            }

            $cleanSpText = '';

            foreach($cleanSp as $p) {
                $cleanSpText .= $p . ' ';
            }

            array_push($s, array(
                'sentence'      => $cleanSpText
            ));

        }

        return $s;

    }


    /**
    *
    * Compiles the pre-processed textParts into a blob of text
    *
    */
    public function textPartsCompile(): string {

        $text = '';

        foreach($this->textParts as $textPart) {
            $text .= ' ' . $textPart;
        }

        return $text;

    }


    /**
    *
    * Returns the text parts minus stopwords
    *
    */
    public function textPartsFiltered(): array {
        return self::filterStopwords($this->textParts);
    }


    /**
    *
    * Returns the text parts
    *
    */
    public function textParts(): array {
        return $this->textParts;
    }


    /**
    *
    * Returns the cleansed text
    *
    */
    public function textClean(): string {

        $text = '';

        foreach(self::textPartsFiltered() as $textPart) {
            $text .= ' ' . $textPart;
        }

        return trim($text);

    }


    /**
    *
    * Returns the sentiment of the given text
    *
    */
    public function sentiment(string $string) {

      $sentiment = new \PHPInsight\Sentiment();

      $scores   = $sentiment->score($string);
      $class    = $sentiment->categorise($string);

      $res = array(
        $scores, $class
      );

      return $class;

    }


    /**
    *
    * Checks if body text contains given word
    *
    */
    private function textContains(string $text, string $needle): bool {

        $needle = preg_quote($needle, '/');

        if (preg_match("/\b" . $needle . "\b/i", $text)) {
            return true;
        } else {
            return false;
        }

    }


    /**
    *
    * Check provided text against document definitions
    *
    */
    public function possibleDoctypes(): array {

        $text       = self::textClean();
        $matches    = array();

        foreach ($this->documentDefinitions as $key => $dd) {

            $m = array();

            foreach ($dd as $term) {

                if (self::textContains($text, $term) === TRUE) {
                    array_push($m, $term);
                }

            }

            $matches[$key] = $m;

        }

        return $matches;

    }


}

?>
