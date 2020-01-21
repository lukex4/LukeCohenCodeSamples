

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

// var tesseract   = Tesseract.create({
  // workerPath: path.join(__dirname, 'tesseract_lib/src/node/worker.js'),
  // langPath: path.join(__dirname, 'tesseract_lib/lang/'),
  // langPath: path.join(__dirname),
  // corePath: path.join(__dirname, 'tesseract_lib/src/core.js'),
// });

app.use(express.static('public'));

app.post('/process', upload.single('image'), (req, res) => {

    if (req.file) {

        var t = new Promise(function(resolve, reject) {

            tesseract
                .recognize(req.file.path)
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
            res.json(val);
        });

    } else {
        throw 'error';
    }

});

var PORT = 3000;

app.listen(PORT, () => {
    console.log('Listening on ' + PORT);
});
