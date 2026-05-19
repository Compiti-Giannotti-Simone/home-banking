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

//Authentication endpoints (available to all users)
$app->post('/auth/register', 'UserController:register');
$app->post('/auth/login', 'UserController:login');
$app->post('/auth/logout', 'UserController:logout');

//User endpoints (for authenticated users only)
$app->get('/users/me', 'UserController:getCurrentUserProfile');
$app->get('/accounts', 'AccountController:getUserAccounts');
$app->get('/accounts/{account}', 'AccountController:getUserAccountById');
$app->post('/accounts', 'AccountController:createAccount');
$app->post('/accounts/{account}/deposit', 'TransactionController:createDeposit');
$app->post('/accounts/{account}/withdrawal', 'TransactionController:createWithdrawal');
$app->get('/accounts/{account}/transactions', 'TransactionController:getAccountTransactions');
$app->get('/accounts/{account}/transactions/{transactionId}', 'TransactionController:getAccountTransactionById');
$app->get('/accounts/{account}/convert/fiat', 'ConversionController:toFiat');
$app->get('/accounts/{account}/convert/crypto', 'ConversionController:toCrypto');

//Admin endpoints (for administrators only)
$app->get('/admin/users', 'UserController:getAllUsers');
$app->get('/admin/users/{id}', 'UserController:getUserById');
$app->put('/admin/users/{id}', 'UserController:adminUpdate');
$app->delete('/admin/users/{id}', 'UserController:adminDelete');

$app->get('/admin/accounts', 'AccountController:getAllAccounts');
$app->get('/admin/accounts/{account}', 'AccountController:getAccountById');
$app->put('/admin/accounts/{account}', 'AccountController:adminUpdate');
$app->delete('/admin/accounts/{account}', 'AccountController:adminDelete');

$app->put('/admin/transactions/{transactionId}', 'TransactionController:adminUpdateTransaction');
$app->delete('/admin/transactions/{transactionId}', 'TransactionController:adminDeleteTransaction');

$app->run();
