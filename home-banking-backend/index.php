<?php
session_start();

use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/TransactionController.php';
require __DIR__ . '/controllers/ConversionController.php';
require __DIR__ . '/controllers/AccountController.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

//Accounts API's
$app->post('/accounts/register', 'AccountController:register');
$app->post('/accounts/login', 'AccountController:login');
$app->delete('/accounts/{account}', 'AccountController:deleteAccount');

//Transactions API's
$app ->get('/accounts/{account}/transactions', 'TransactionController:allTransactions');
$app ->get('/accounts/{account}/transactions/{transactionId}', 'TransactionController:transaction');
$app ->post('/accounts/{account}/deposit', 'TransactionController:deposit');
$app ->post('/accounts/{account}/withdrawal', 'TransactionController:withdraw');
$app ->put('/accounts/{account}/transactions/{transactionId}', 'TransactionController:editDesc');
$app ->delete('/accounts/{account}/transactions/{transactionId}', 'TransactionController:delete');

$app ->get('/accounts/{account}/balance', 'TransactionController:getBalance');

//Convertions API's
$app ->get('/accounts/{account}/balance/convert/fiat', 'ConversionController:toFiat');
$app ->get('/accounts/{account}/balance/convert/crypto', 'ConversionController:toCrypto');

$app->run();



//curl -X GET http://localhost:8080/accounts/1/transactions
//curl -X GET http://localhost:8080/accounts/1/transactions/2
//curl -X POST http://localhost:8080/accounts/1/deposit -H "Content-Type: application/json" -d '{"amount": 200, "description": "Salary"}'
//curl -X POST http://localhost:8080/accounts/1/withdrawal -H  "Content-Type: application/json" -d '{"amount": 50, "description": "Groceries"}'
//curl -X PUT http://localhost:8080/accounts/1/transactions/2 -H "Content-Type: application/json" -d '{"description": "Updated description"}'
//curl -X DELETE http://localhost:8080/accounts/1/transactions/2
//curl -X GET http://localhost:8080/accounts/1/balance
//curl -X GET http://localhost:8080/accounts/1/balance/convert/fiat?to=EUR
//curl -X GET http://localhost:8080/accounts/1/balance/convert/crypto?to=BTC
