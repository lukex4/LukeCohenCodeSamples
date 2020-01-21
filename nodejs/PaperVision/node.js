

/***
*
* "PAPERVISION" v0.1
* Implements OCR technologies to read expense receipts (etc.) from user-supplied image
*
*/

var express     = require('express');
var multer      = require('multer');
var path        = require('path');

var upload      = multer({dest: '/tmp'});
var app         = express();

var Tesseract   = require('tesseract.js');

var { TesseractWorker, OEM } = Tesseract;
var tesseract = new TesseractWorker({
    langPath: path.join(__dirname, 'tesseract_lib/lang')
});

var filepath = 'chanel.jpg';

var t = new Promise(function(resolve, reject) {

    tesseract
        .recognize(filepath)
        .progress((p) => {
            console.log('progress', p);
        })
        .catch((err) => reject(err))
        .then(({text}) => {

            var response = {
                'text_extracted': text
            };

            resolve(response);

        });

});

t.then(function(val) {
    console.log('val', val);
});
