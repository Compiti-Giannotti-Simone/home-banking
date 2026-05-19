<?php
session_start();

use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/controllers/TransactionController.php';
require __DIR__ . '/controllers/ConversionController.php';
require __DIR__ . '/controllers/AccountController.php';
require __DIR__ . '/controllers/UserController.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

//Users API's
$app->post('/users/register', 'UserController:register');
$app->post('/users/login', 'UserController:login');
$app->get('/users/me', 'UserController:getCurrentUser');
$app->get('/users/me/accounts', 'UserController:getCurrentUserAccounts');
$app->get('/users/{id}', 'UserController:getUserById');
$app->get('/users/{id}/accounts', 'UserController:getUserAccountsById');

//Admin Users API's
$app->post('/admin/users', 'UserController:adminCreate');
$app->put('/admin/users/{id}', 'UserController:adminUpdate');
$app->delete('/admin/users/{id}', 'UserController:adminDelete');

//Accounts API's
$app->post('/users/me/accounts', 'AccountController:create');
$app->get('/users/me/accounts/{account}', 'AccountController:getMyAccount');
$app->delete('/users/me/accounts/{account}', 'AccountController:deleteMyAccount');
$app->delete('/accounts/{account}', 'AccountController:deleteAccount');

//Admin Accounts API's
$app->post('/admin/accounts', 'AccountController:adminCreate');
$app->post('/users/{id}/accounts', 'AccountController:adminCreate');
$app->put('/admin/accounts/{account}', 'AccountController:adminUpdate');
$app->delete('/admin/accounts/{account}', 'AccountController:adminDelete');

//Transactions API's
$app ->get('/accounts/{account}/transactions', 'TransactionController:allTransactions');
$app ->get('/accounts/{account}/transactions/{transactionId}', 'TransactionController:getTransactionById');
$app ->post('/accounts/{account}/deposit', 'TransactionController:createDeposit');
$app ->post('/accounts/{account}/withdrawal', 'TransactionController:createWithdrawal');
$app ->put('/accounts/{account}/transactions/{transactionId}', 'TransactionController:editDescription');
$app ->delete('/accounts/{account}/transactions/{transactionId}', 'TransactionController:deleteTransaction');

//Admin Transactions API's
$app->post('/admin/transactions', 'TransactionController:adminCreate');
$app->put('/admin/transactions/{transactionId}', 'TransactionController:adminUpdate');
$app->delete('/admin/transactions/{transactionId}', 'TransactionController:adminDelete');

$app ->get('/accounts/{account}/balance', 'TransactionController:getBalance');

//Convertions API's
$app ->get('/accounts/{account}/balance/convert/fiat', 'ConversionController:toFiat');
$app ->get('/accounts/{account}/balance/convert/crypto', 'ConversionController:toCrypto');

$app->run();
