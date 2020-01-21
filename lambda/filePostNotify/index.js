
/*

LEGALZOOM/DOCVAULT 0.1
Notifies DocVault-core that a file has been uploaded via HTTP POST
And triggers Antivirus check, feeding back result to DocVault-core

*/

/* Required packages */
require('dotenv').config();

var request     = require('request');
var filetype    = require('file-type');
var aws         = require('aws-sdk');

var s3          = new aws.S3({
    signatureVersion    : 'v4',
    accessKeyId         : process.env.S3_KEY_ID,
    secretAccessKey     : process.env.S3_ACCESS_KEY,
    region              : 'us-west-1',
});

/* DocVault base URL */
var dvBase =  process.env.LZ_DOCVAULT_BASEURL;

/* S3 Bucket */
var s3Bucket = process.env.S3_BUCKET_NAME;

/* DV-AV Server Address */
var dvAvAddress = process.env.DV_AV_ADDRESS;


exports.handler = (event, context, callback) => {

    console.log('new S3 object key', event.Records[0].s3.object.key);

    /* Capture the S3 key for the new object */
    var newObjS3Key     = event.Records[0].s3.object.key;

    /* Grab the dvKey and the filename from the S3 key */
    var newObjDvKey     = newObjS3Key.split("/")[0];
    var newObjFilename  = newObjS3Key.split("/")[1];
    newObjFilename      = decodeURI(newObjFilename);
    newObjS3Key         = decodeURI(newObjS3Key).replace(/\+/g,' ');

    /* Retrieve the first 4100 bytes of the file, for mime-type check */
    var theFileType;

    var reqParams = {
        Bucket: s3Bucket,
        Key: newObjS3Key
    };


    /* Handles the response from Dv-Av-Server */
    var handleAvResponse = function(avResponse) {
        console.log('handleAvResponse', avResponse);

        var fileStatus;

        if (avResponse == 'OK') {
            fileStatus = true;
        }

        if (avResponse == 'FAIL') {
            fileStatus = false;
        }

        /* Send update to DocVault */
        var notifyUrl = dvBase + '/file/ingress/newstatus';

        var updateObj = {
            'ingress'               : 'AV',
            'dvkey'                 : newObjDvKey,
            'viruschecked'          : true,
            'viruscheck_clean'      : fileStatus,
            'viruscheck_timestamp'  : Math.round(Date.now()/1000)
        };

        console.log('updateObj', updateObj);

        request.post({
            url: notifyUrl,
            body: updateObj,
            json: true,
            headers: {
              'X-API-Product-Key': process.env.API_PRODUCT_KEY
            }
        }, function(error, res, body) {

            if (error) {
                console.log('error', error, error.stack);
            } else {
                console.log('body', body);
            }

        });

    };


    /* Sends the File binary to the Dv-Av-Server for checking */
    var remoteAvScan = function(fileName, fileData) {
        console.log('remoteAvScan', fileName, dvAvAddress);

        var req = request.post(dvAvAddress, function (error, response, body) {
            if (error) {
                console.log('error', error);
            } else {
                handleAvResponse(body);
            }
        });

        var form = req.form();
        form.append('file', fileData, {
            filename: fileName
        });

    };


    /* Downloads the File from S3 */
    var fetchObject = function(params) {

        return new Promise((resolve, reject) => {

            s3.getObject(params, (error, data) => {

                if (error) {
                    reject(error);
                } else {
                    resolve(data.Body);
                }

            });

        });

    };


    /* Anti-virus start */
    var avStart = async function(reqParams, fileName) {
        console.log('avStart');
        var fileData = await fetchObject(reqParams);
        remoteAvScan(fileName, fileData);
    };


    /* Update core */
    var updateCore = function() {

        /* Send update to DocVault */
        var notifyUrl = dvBase + '/file/ingress/newstatus';

        var updateObj = {
            'ingress'   : 'S3',
            'dvkey'     : newObjDvKey,
            'filename'  : newObjFilename,
            'mimetype'  : theFileType,
            'status'    : 'complete'
        };

        request.post({
            url: notifyUrl,
            body: updateObj,
            json: true,
            headers: {
              'X-API-Product-Key': process.env.API_PRODUCT_KEY
            }
        }, function(error, res) {

            if (error) {
                console.log('error', error, error.stack);
            } else {
                avStart(reqParams, newObjFilename);
            }

        });

    };

    /* Ascertain file type */
    s3.getObject(reqParams, function(err, data) {
        console.log('s3.getObject', newObjS3Key);

        if (err) {
            console.log('error', err);
        } else {
            console.log('success', data);

            /* Check the file mime-type */
            var respBuffer = new Buffer(data.Body);
            theFileType = filetype(respBuffer);

            if (theFileType) {
                theFileType = theFileType.mime;
            } else {
                theFileType = 'unknown';
            }

            updateCore();

        }

    });

};
