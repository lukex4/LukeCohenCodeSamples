
var express     = require('express');
var app         = express();

app.get('/', (req, res) => res.send('RESPONSE'));

app.listen(5000, () => {
  console.log('Listening on port 5000');
});
