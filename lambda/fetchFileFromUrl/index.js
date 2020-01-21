
/*

LEGALZOOM/DOCVAULT 0.1
Downloads a remote file and saves it in an AWS-S3 bucket

*/

/* Required packages */
require('dotenv').config();

var fs          = require('fs');
var request     = require('request');
var aws         = require('aws-sdk');
var filetype    = require('file-type');

var s3          = new aws.S3({
  accessKeyId       : process.env.S3_KEY_ID,
  secretAccessKey   : process.env.S3_ACCESS_KEY
});

/* DocVault base URL */
var dvBase =  process.env.LZ_DOCVAULT_BASEURL;


exports.handler = (event, context, callback) => {

    console.log('event', event);

    /* Parse the event */
    var theEvent                = event.Records[0];

    var queueItemBody           = theEvent.body;
    var queueItemAttributes     = theEvent.messageAttributes;

    var fileUrl                 = queueItemAttributes.fileUrl.stringValue;
    var dvKey                   = queueItemAttributes.toDvKey.stringValue;


    /* Prepare the response object */
    var theRequest = {
        "fileUrl"   : fileUrl,
        "dvKey"     : dvKey
    };

    console.log('theRequest', theRequest);

    /* S3 transfer success */
    var s3Success = function(successfulRequest) {

        notifyDocVault(dvKey, 'complete', successfulRequest.theFileType, successfulRequest.theFileName);

        callback(null, {
            statusCode: 200,
            body: JSON.stringify(successfulRequest),
            headers: {'Content-Type': 'application/json'}
        });

    };


    /* S3 transfer fail */
    var s3Fail = function(failedRequest) {

        notifyDocVault(dvKey, 'failed');

        callback(null, {
            statusCode: 200,
            body: JSON.stringify(failedRequest),
            headers: {'Content-Type': 'application/json'}
        });

    };


    /* Send the file to S3 */
    var s3Despatch = function(fileBuffer, fileKey) {

        /* Extract the mime-type of the file */
        var filetypeMime = filetype(fileBuffer);

        if (filetypeMime) {
            filetypeExt     = filetypeMime.ext;
            filetypeMime    = filetypeMime.mime;

            if (filenameOrig == 'originalname_invalid') {
                filenameOrig = filenameOrig + '.' + filetypeExt;
                fileKey = fileKey + '.' + filetypeExt;
            }

        } else {
            filetypeMime = 'unknown';
        }

        /* Despatch the upload to S3 */
        s3.upload({
            Bucket: process.env.S3_BUCKET_NAME,
            Key: fileKey,
            Body: fileBuffer
        }, function(err, data, fileBuffer) {

            if (err) {
                theRequest.uploadStatus = 'fail';
                theRequest.errorDetail = JSON.stringify(err);
                s3Fail(theRequest);
            } else {
                theRequest.uploadStatus = 'success';
                theRequest.theFileType = filetypeMime;
                theRequest.fileKey = fileKey;
                theRequest.theFileName = filenameOrig;
                s3Success(theRequest);
            }

        });

    };


    /* Fetch file remote file into a Buffer */
    var remoteFileFetch = function(fileUrl, s3Key) {

        request.get(fileUrl, {
            url: fileUrl,
            encoding: null
        }, function(error, res, body) {

            if (error) {
                console.log('error', error);
            } else {
                notifyDocVault(dvKey, 'processing');
                s3Despatch(body, s3Key);
            }

        })

    };


    /* Notify DocVault-core of the outcome of the transfer */
    var notifyDocVault = function(dvKey, uploadStatus, fileTypeMime, fileName) {

        /* Send update to DocVault */
        var notifyUrl = dvBase + '/file/ingress/newstatus';

        var updateObj = {
            'ingress'   : 'URL',
            'dvkey'     : dvKey,
            'filename'  : fileName,
            'mimetype'  : fileTypeMime,
            'status'    : 'complete'
        };

        request.post({
            url: notifyUrl,
            body: updateObj,
            json: true
        }, function(error, res) {

            if (error) {
                console.log('error', error);
            }

        });

    };


    /* Checks validity of a given filename */
    var filenameInvalid = function(filename) {

        var badchars = ['?', '&', '=', ';', ',', ':'];
        var fail = false;

        for (var c in badchars) {
            (filename.includes(badchars[c])) && (fail = true);
        }

        return fail;

    };


    /* Start */
    var filenameOrig;
    var s3Key;
    var theFileType;

    var start = function() {

        /* Extract the filename from the URL */
        filenameOrig = fileUrl.substring(fileUrl.lastIndexOf('/')+1);

        /* If filename has query string remnants, etc. give it a placeholder name */
        (filenameInvalid(filenameOrig)) && (filenameOrig = 'originalname_invalid');

        /* Set the ultimate S3 key for this entry */
        s3Key = dvKey + '/' + filenameOrig;

        /* Trigger fetch */
        remoteFileFetch(fileUrl, s3Key);

    };

    start();


};
