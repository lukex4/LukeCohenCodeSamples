
/**
*
* WARNING: This script deletes all of the transaction history of the Deepsky Spider. Use with caution.
*
*/

var level       = require('level');
var path        = require('path');


/**
*
* Create LevelDB connection
*
*/
var dbPath  = path.join(__dirname, 'deepsky-spider');
var db      = level(dbPath);

db.createReadStream()
  .on('data', function (data) {
    console.log(data.key, '=', data.value)
    db.del(data.key);
});

db.createReadStream()
.on('data', function (data) {
  console.log(data.key, '=', data.value)
});

console.log('done');
