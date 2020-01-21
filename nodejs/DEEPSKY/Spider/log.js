
var fs = require('fs');

var msg = 'proc ' + Date.now() + "\n";

fs.appendFileSync('log.log', msg);
