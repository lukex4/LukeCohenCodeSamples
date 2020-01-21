
/** dd-service-sms alpha 0.1 */

const http              = require('http');
const express           = require('express');
const MessagingResponse = require('twilio').twiml.MessagingResponse;
const PhoneNumber       = require('awesome-phonenumber');
const parseFullName     = require('parse-full-name').parseFullName;
const bodyParser        = require('body-parser');
const {google}          = require('googleapis');

const app = express();
app.use(bodyParser.urlencoded({ extended: false }));

var processStatement = function(statement) {

  var contractTypes = {
    'NDA_MEETING_GENERAL': {
      'name_informal': 'meeting nda',
      'name_formal': 'General Meeting NDA',
      'description': 'This protects sensitive information you share during your next meeting.'
    },
    'NDA_CONVERSATION_PHONE': {
      'name_informal': 'phone call nda',
      'name_formal': 'Phone Conversation NDA',
      'description': ''
    }
  }

  var actionTypes = ['Send', 'Cancel', 'Agree'];

  var statementAction;
  var statementContract;
  var statementContractDescription;
  var statementRecipientName;
  var statementRecipientNumber = null;
  var statementText = statement;
  statementText = statementText.toLowerCase();

  var statementParts = statementText.split('to');

  /* Ascertain the statement action intent */
  for (var type in actionTypes) {

    if (statementText.includes(actionTypes[type].toLowerCase())) {
      statementAction = actionTypes[type];
    }

  }

  /* Ascertain the statement contract intent */
  for (var contract in contractTypes) {

    if (statementText.includes(contractTypes[contract].name_informal)) {
      statementContract = contractTypes[contract].name_formal;
      statementContractDescription = contractTypes[contract].description;
    }

  }

  /* Ascertain the statement contract recipient phone number */
  var lastPart = statementParts[1].replace('+', '00').trim();
  var lastParts = lastPart.split(' ');

  for (var part in lastParts) {

    if (!isNaN(lastParts[part])) {
      statementRecipientNumber = lastParts[part];

      if (statementRecipientNumber.substr(0, 2)=='00') {
        statementRecipientNumber = statementRecipientNumber.slice(2);
        statementRecipientNumber = '+' + statementRecipientNumber;
      }

      lastParts.splice(part,1);

    }

  }

  var pn = new PhoneNumber(statementRecipientNumber);
  statementRecipientNumber = pn.getNumber('international');

  /* Ascertain the statement contract recipient name */
  var name = parseFullName(lastParts.join(' '));

  statementRecipientName = name.title + ' ' + name.first + ' ' + name.middle + ' ' + name.last;
  statementRecipientName = statementRecipientName.replace('  ', ' ').trim();

  /* Wrap it up */
  var statementAnalysed = {
    text: statementText,
    action: statementAction,
    contract: statementContract,
    contractDescription: statementContractDescription,
    recipientName: statementRecipientName,
    recipientNumber: statementRecipientNumber
  };

  console.log('statementAnalysed', statementAnalysed);

  var responseStatement = 'You want to ' + statementAnalysed.action.toLowerCase() + ' the ' + statementAnalysed.contract + ' to ' + statementAnalysed.recipientName + ', via SMS to ' + statementAnalysed.recipientNumber + '? ' + statementAnalysed.contractDescription + ' To confirm, reply YES.';

  return responseStatement;

};

/* Is it alive? */
app.get('/test', (req, res) => {

  res.end('...');

});

/* Twilio */
app.post('/sms', (req, res) => {

  var twiml = new MessagingResponse();

  var msgResponse = processStatement(req.body.Body);

  if (!msgResponse) {
    msgResponse = 'empty response';
  }

  twiml.message(msgResponse);

  res.writeHead(200, {'Content-Type': 'text/xml'});
  res.end(twiml.toString());

});

/* Google Calendar */
app.get('/google-calendar', (req, res) => {

  res.end('/google-calendar response');

});

/* Launch server */
http.createServer(app).listen(8080, () => {
  console.log('Express.js server listening on port 8080');
});
