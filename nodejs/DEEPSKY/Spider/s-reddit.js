
/**
*
* PulseAI
* DEEPSKY v1.0
*
* This script downloads Reddit posts containing certain keywords, and saves them to S3 for separate analysis with AWS Athena or Apache Drill
*
*/

var config      = require('dotenv').config().parsed;
var request     = require('request-promise');
var uuidV1      = require('uuid/v1');
var level       = require('level');
var path        = require('path');

var period      = 'day';
var subreddits  = config.TARGET_SUBREDDITS.split(',');
var keywords    = config.TARGET_TERMS.split(',');

var baseurl     = 'http://reddit.com/r/';
var urlmid      = '/search.json?q=';
var urlext      = '&restrict_sr=true&t=' + period + '&sort=new&limit=100';

var posts       = [];
var promises    = [];
var numSaved    = 0;
var numNotSaved = 0;

var AWS         = require('aws-sdk');

var S3          = new AWS.S3({
    accessKeyId     : config.S3_KEYID,
    secretAccessKey : config.S3_ACCESSKEY
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
var saveJsonToS3 = function(json) {

    if (!json || json.hasOwnProperty('permalink') === false) {
        return;
    }

    /* S3 new file parameters */
    var newFileParams = {
        Bucket      : config.S3_BUCKET,
        Key         : 'reddit/' + json.id,
        Body        : JSON.stringify(json),
        ContentType : 'application/json'
    }

    /* Check if this item has already been saved to S3 */
    db.get('twitter-' + json.id_str, function(err, value) {

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

        db.put('twitter-' + json.id_str, '1', function(e, v) {
            e && console.log(e);
        });

    };

};


/**
*
* Fetches the batch of JSON for a specific keyword/subreddit combination
*
*/
function fetchSubredditPosts(requestUrl) {

    return new Promise(function(resolve, reject) {

        request(requestUrl)
            .then(function(resp) {

                var thisPosts = JSON.parse(resp).data.children;
                var a = [];

                for (var thisPost in thisPosts) {
                    a.push(thisPosts[thisPost].data);
                }

                resolve(a);

            })
            .catch(function(err) {
                reject(err);
            });

    });

}


function doStart() {

    return new Promise(function(resolve, reject) {

        for (var subreddit of subreddits) {
            console.log(batchId + ' - Subreddit ' + subreddit + ', ' + keywords.length + ' target terms');

            for (var keyword of keywords) {

                var thisReqUrl = baseurl + subreddit + urlmid + keyword + urlext;

                promises.push(fetchSubredditPosts(thisReqUrl).then(function(r) {
                    posts.push(r);
                }));

            }

            resolve();

        }

    });

}


var batchId     = 'reddit-' + uuidV1();

console.log(batchId + ' - Starting, period ' + period);

doStart().then(function() {

    setTimeout(function() {

        Promise.all(promises).then(function(prom) {

            /** Save posts to S3 */
            for (var p in posts) {

                var ps = posts[p];

                for (var post in ps) {
                    saveJsonToS3(ps[post]);
                }

                console.log(batchId + ' - ' + ps.length + ' posts processed');

            }

            console.log(batchId + ' - Complete, ' + numSaved + ' posts saved, ' + numNotSaved + ' posts already in storage');

        });
    });

});
