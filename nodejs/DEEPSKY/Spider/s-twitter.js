
/**
*
* PulseAI
* DEEPSKY v1.0
*
* This script downloads Twitter posts containing certain keywords, and saves them to S3 for separate analysis with AWS Athena or Apache Drill
*
*/

var config      = require('dotenv').config().parsed;
var keywords    = config.TARGET_TERMS.split(',');
var Twitter     = require('twitter');
var AWS         = require('aws-sdk');
var uuidV1      = require('uuid/v1');
var level       = require('level');
var path        = require('path');

var S3          = new AWS.S3({
    accessKeyId     : config.S3_KEYID,
    secretAccessKey : config.S3_ACCESSKEY
});

var client = new Twitter({
    consumer_key    : config.TWITTER_CONSUMER_KEY,
    consumer_secret : config.TWITTER_CONSUMER_SECRET,
    bearer_token    : config.TWITTER_BEARER_TOKEN
});


/**
*
* Create LevelDB connection
*
*/
var dbPath  = path.join(__dirname, 'deepsky-spider');
var db      = level(dbPath);


/**
*
* Save a JSON object to S3
*
*/

var numSaved    = 0;
var numNotSaved = 0;

var saveJsonToS3 = function(json) {

    if (!json || json.hasOwnProperty('id_str') === false) {
        return;
    }

    /* S3 new file parameters */
    var newFileParams = {
        Bucket      : config.S3_BUCKET,
        Key         : 'twitter/' + json.id_str,
        Body        : JSON.stringify(json),
        ContentType : 'application/json'
    }

    /* Check if this item has already been saved to S3 */
    db.get('reddit-' + json.id_str, function(err, value) {

        if (err && err.notFound) {
            save();
        } else {
            numNotSaved++;
        }

    });

    /* Save item to S3 */
    var save = function () {

        S3.putObject(newFileParams, function(err, data) {
            // console.log('S3 Error', JSON.stringify(err) + ' ' + JSON.stringify(data));
            numSaved++;
        });

        db.put('reddit-' + json.id_str, '1', function(e, v) {
            e && console.log(e);
        });

    };

};


/**
*
* Process the fetched Tweets
*
*/
var process = function(tweets) {

    for (var tweet in tweets.statuses) {
        tweet = tweets.statuses[tweet];
        saveJsonToS3(tweet);
    }

    console.log(numSaved + ' posts processed, ' + numNotSaved + ' posts already in storage');

};


/**
*
* Fetch Tweets
*
*/

var batchId = 'twitter-' + uuidV1();

console.log(batchId + ' - Starting');

for (var k in keywords) {

    var word = keywords[k];

    client.get('search/tweets', {q: word, result_type: 'recent', count: 250}, function(error, tweets, response) {
        process(tweets);
    });

}
