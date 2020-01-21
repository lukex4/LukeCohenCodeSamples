
const PhoneNumber = require('awesome-phonenumber');
const parseFullName = require('parse-full-name').parseFullName;

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
var statementText = 'send meeting nda to mike bird 07890123456';
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

console.log('lastParts', lastParts);

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

// console.log('statementRecipientNumber', statementRecipientNumber);

var pn = new PhoneNumber(statementRecipientNumber);
statementRecipientNumber = pn.getNumber();

console.log('pn', pn);

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

console.log('responseStatement', responseStatement);
