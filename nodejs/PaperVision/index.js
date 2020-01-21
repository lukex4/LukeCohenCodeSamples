

/***
*
* "PAPERVISION" v0.1
* Implements OCR technologies to read expense receipts (etc.) from user-supplied image
*
*/


/* Required packages */
require('dotenv').config();

var uuid        = require('uuid');
var fs          = require('fs');
var path        = require('path');
var Tesseract   = require('tesseract.js');


/* Tesseract instance for this analysis */
var { TesseractWorker, OEM } = Tesseract;
var tesseract = new TesseractWorker({
    langPath: path.join(__dirname, 'tesseract_lib/lang'),
    cacheMethod: 'readOnly',
    gzip: false
});


module.exports.handler = async (event, context) => {
    console.log('event', event);


    /* Extract image data from request */
    var imgBase64   = event.base64img;
    var imgFiletype = event.filetype;
    var imgBuffer   = Buffer.from(imgBase64, 'base64');

    imgBase64 = 'data:' + imgFiletype + ';base64,' + imgBase64;


    /* Save image to /tmp */
    var filename = '/tmp/' + uuid.v4();


    /* Let Tesseract do its magic */
    return await new Promise(function(resolve, reject) {

        tesseract
            // .recognize(filename)
            .recognize(imgBase64)
            .progress((p) => {
                console.log('...', p);
            })
            .catch((err) => reject(err))
            .then(({text}) => {

                var extractedText = {
                    'extractedText': text
                };

                var response = {
                    statusCode: 200,
                    headers: {
                        'Access-Control-Allow-Origin': '*'
                    },
                    body: extractedText
                };

                console.log('response', response);

                resolve(response);

            });

    });


}
