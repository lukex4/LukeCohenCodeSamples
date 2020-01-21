

/***
*
* "PAPERVISION" v0.1
* Implements OCR technologies to read expense receipts (etc.) from user-supplied image
*
*/

var port        = 5000;

var http        = require('http');
var multer      = require('multer');
var formidable  = require('formidable');

var upload      = multer({dest: '/tmp'});

var Tesseract   = require('tesseract.js');

var { TesseractWorker, OEM } = Tesseract;
var tesseract = new TesseractWorker();


var app = http.createServer(function(req, res) {

    if (req.method == 'POST' && req.url == '/') {

        var form = new formidable.IncomingForm();
        form.uploadDir = '/tmp';

        form.parse(req, function (err, fields, files) {

            tesseract
              .recognize(files.image.path)
              .progress((p) => {
                  console.log('progress', p);
              })
              .then(({text}) => {

                  var response = {
                      'text_extracted': text
                  };

                  console.log('response', response);

                  res.setHeader('Access-Control-Allow-Origin', '*');
                  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, PATCH, DELETE');
                  res.setHeader('Access-Control-Allow-Headers', 'X-Requested-With,content-type'); 

                  res.end(JSON.stringify(response));
              });

        });

    }

});

app.listen(port);
console.log('Server running on port ' + port);
