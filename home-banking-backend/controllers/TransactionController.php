<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../MysqlConnection.php';

class TransactionController
{
    private function ensureAdmin(Response $response, &$userId = null)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return 401;
        }

        $isAdmin = $_SESSION['is_admin'] ?? null;
        if ($isAdmin === null) {
            $mysqli = MysqlConnection::getInstance();
            $stmt   = $mysqli->prepare("SELECT is_admin FROM `user` WHERE id = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return 500;
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $isAdmin = (bool) ($row['is_admin'] ?? 0);
            $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
        }

        if (! $isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'Forbidden']));
            return 403;
        }

        return 200;
    }

    // GET /accounts/{account}/transactions
    public function getAccountTransactions(Request $request, Response $response, $args)
    {
        return $this->allTransactions($request, $response, $args);
    }

    //GET /accounts/account:id/transactions -- shows all transactions
    public function allTransactions(Request $request, Response $response, $args)
    {
        // get accountid from parameters
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //fetch transactions
        $stmt = $mysqli->prepare("SELECT * FROM `transaction` WHERE account_id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $results = $result->fetch_all();
        $response->getBody()->write(json_encode($results));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    // GET /accounts/{account}/transactions/{transactionId}
    public function getAccountTransactionById(Request $request, Response $response, $args)
    {
        return $this->getTransactionById($request, $response, $args);
    }

    //GET /accounts/account:id/transactions/transaction:id -- gets the details of a single transaction
    public function getTransactionById(Request $request, Response $response, $args)
    {
        // get account and transaction ids from parameters
        $accountId     = $args['account'] ?? '';
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //get transaction
        $stmt = $mysqli->prepare("SELECT * FROM `transaction` WHERE account_id = ? AND id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $results = $result->fetch_assoc();
        if (! $results) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader("Content-type", "application/json")->withStatus(404);
        }
        $response->getBody()->write(json_encode($results));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    //POST /accounts/account:id/deposit -- register a deposit action on a specified account
    public function createDeposit(Request $request, Response $response, $args)
    {
        //get request data
        $accountId   = $args['account'] ?? '';
        $data        = $request->getParsedBody();
        $amount      = isset($data['amount']) ? trim($data['amount']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        //request data validity checks
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($amount == '') {
            $response->getBody()->write(json_encode(['error' => 'missing amount to deposit']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if (! is_numeric($amount) || (float) $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'amount must be greater than zero']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($description == '') {
            $response->getBody()->write(json_encode(['error' => 'Missing description for deposit']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //create deposit
        $stmt = $mysqli->prepare("INSERT INTO `transaction` (`account_id`, `amount`, `description`, `type`) VALUES (?, ?, ?, 'deposit')");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ids', $accountId, $amount, $description);
        $stmt->execute();
        $response->getBody()->write(json_encode(['message' => 'deposit registered successfully']));
        return $response->withHeader("Content-type", "application/json")->withStatus(201);
    }

    //POST /accounts/account:id/withdrawal -- register a withdrawal action on a specified account
    public function createWithdrawal(Request $request, Response $response, $args)
    {
        //get request data
        $accountId   = $args['account'] ?? '';
        $data        = $request->getParsedBody();
        $amount      = isset($data['amount']) ? trim($data['amount']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        //request data validity checks
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($amount == '') {
            $response->getBody()->write(json_encode(['error' => 'missing amount to withdraw']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if (! is_numeric($amount) || (float) $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'amount must be greater than zero']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($description == '') {
            $response->getBody()->write(json_encode(['error' => 'Missing description for withdrawal']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //fetch user balance
        $stmt = $mysqli->prepare("SELECT IFNULL((SELECT SUM(amount) FROM `transaction` WHERE account_id = ? AND `type` = 'deposit'),0)  - IFNULL((SELECT SUM(amount) FROM `transaction` WHERE account_id = ? AND `type` = 'withdrawal'),0) as balance");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $accountId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $balance = $result->fetch_assoc();
        $stmt->close();

        //check if user has enough balance for withdrawal
        if ((float) $amount > (float) $balance['balance']) {
            $response->getBody()->write(json_encode(['error' => 'insufficient funds']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //create withdrawal
        $stmt = $mysqli->prepare("INSERT INTO `transaction` (`account_id`, `amount`, `description`, `type`) VALUES (?, ?, ?, 'withdrawal')");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ids', $accountId, $amount, $description);
        $stmt->execute();
        $response->getBody()->write(json_encode(['message' => 'withdrawal registered successfully']));
        return $response->withHeader("Content-type", "application/json")->withStatus(201);
    }

    //PUT /accounts/account:id/transactions/transaction:id -- edit the description of a specified transaction
    public function editDescription(Request $request, Response $response, $args)
    {
        //get request data
        $accountId     = $args['account'] ?? '';
        $transactionId = $args['transactionId'] ?? '';
        $data          = $request->getParsedBody();
        $description   = isset($data['description']) ? trim($data['description']) : '';
        //request data validity checks
        if (! is_numeric($accountId) || $accountId === '' || ! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account/transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($description === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing description']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // check that transaction exists
        $stmt = $mysqli->prepare("SELECT 1 FROM `transaction` WHERE account_id = ? AND id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();
        $transactionExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $transactionExists) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //update transaction
        $stmt = $mysqli->prepare("UPDATE `transaction` SET `description` = ? WHERE account_id = ? AND id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('sii', $description, $accountId, $transactionId);
        $stmt->execute();
        $response->getBody()->write(json_encode(['message' => 'transaction description updated successfully']));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    //DELETE /accounts/account:id/transactions/transaction:id -- to delete a transaction with specified rules
    public function deleteTransaction(Request $request, Response $response, $args)
    {
        //get account and transaction ids from parameters
        $accountId     = $args['account'] ?? '';
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // check that transaction exists
        $stmt = $mysqli->prepare("SELECT 1 FROM `transaction` WHERE account_id = ? AND id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();
        $transactionExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $transactionExists) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //check if transaction to delete is last in history
        $stmt = $mysqli->prepare("SELECT id FROM `transaction` WHERE account_id = ? ORDER BY id DESC LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $result          = $stmt->get_result();
        $lastTransaction = $result->fetch_assoc();
        if ($lastTransaction['id'] != $transactionId) {
            $response->getBody()->write(json_encode(['error' => 'Only the last transaction can be deleted']));
            return $response->withHeader("Content-type", "application/json")->withStatus(400);
        }

        //delete transaction
        $stmt = $mysqli->prepare("DELETE FROM `transaction` WHERE account_id = ? AND id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $transactionId);
        $stmt->execute();
        $response->getBody()->write(json_encode(['message' => 'transaction deleted successfully']));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    // PUT /admin/transactions/{transactionId}
    public function adminUpdateTransaction(Request $request, Response $response, $args)
    {
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data        = $request->getParsedBody();
        $amount      = isset($data['amount']) ? $data['amount'] : null;
        $description = isset($data['description']) ? trim($data['description']) : null;
        $type        = isset($data['type']) ? strtolower(trim($data['type'])) : null;
        $accountId   = isset($data['account_id']) ? $data['account_id'] : null;

        $fields = [];
        $types  = '';
        $params = [];

        if ($amount !== null) {
            if (! is_numeric($amount) || (float) $amount <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Amount must be greater than zero']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`amount` = ?';
            $types   .= 'd';
            $params[] = (float) $amount;
        }

        if ($description !== null) {
            if ($description === '') {
                $response->getBody()->write(json_encode(['error' => 'Missing description']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`description` = ?';
            $types   .= 's';
            $params[] = $description;
        }

        if ($type !== null) {
            if (! in_array($type, ['deposit', 'withdrawal'], true)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid transaction type']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`type` = ?';
            $types   .= 's';
            $params[] = $type;
        }

        if ($accountId !== null) {
            if (! is_numeric($accountId) || $accountId === '') {
                $response->getBody()->write(json_encode(['error' => 'Invalid account id']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`account_id` = ?';
            $types   .= 'i';
            $params[] = (int) $accountId;
        }

        if (count($fields) === 0) {
            $response->getBody()->write(json_encode(['error' => 'No fields to update']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($accountId !== null) {
            $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('i', $accountId);
            $stmt->execute();
            $accountExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (! $accountExists) {
                $response->getBody()->write(json_encode(['error' => 'Account not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }

        $stmt = $mysqli->prepare("SELECT 1 FROM `transaction` WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $exists) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $types .= 'i';
        $params[] = (int) $transactionId;
        $sql = 'UPDATE `transaction` SET ' . implode(', ', $fields) . ' WHERE id = ?';

        $stmt = $mysqli->prepare($sql);
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'Transaction updated']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /admin/transactions/{transactionId}
    public function adminDeleteTransaction(Request $request, Response $response, $args)
    {
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt = $mysqli->prepare("DELETE FROM `transaction` WHERE id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Transaction deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    //GET /accounts/account:id/balance -- to get the balance of a specified account
    public function getBalance(Request $request, Response $response, $args)
    {
        //get account id from parameters
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        // check auth
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // check that account exists and belongs to user
        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? AND user_id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //calculate balance from transactions
        $stmt = $mysqli->prepare("SELECT IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'deposit'),0)  - IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'withdrawal'),0) as balance");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $accountId);
        $stmt->execute();
        $result  = $stmt->get_result();
        $balance = $result->fetch_assoc();
        $response->getBody()->write(json_encode(['balance' => $balance['balance']]));
        return $response->withHeader("Content-type", "application/json")->withStatus(200);
    }

    // POST /admin/transactions
    public function adminCreate(Request $request, Response $response)
    {
        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data        = $request->getParsedBody();
        $accountId   = isset($data['account_id']) ? $data['account_id'] : '';
        $amount      = isset($data['amount']) ? trim($data['amount']) : '';
        $description = isset($data['description']) ? trim($data['description']) : '';
        $type        = isset($data['type']) ? strtolower(trim($data['type'])) : '';

        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'amount must be greater than zero']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($description === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing description']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($type !== 'deposit' && $type !== 'withdrawal') {
            $response->getBody()->write(json_encode(['error' => 'Invalid transaction type']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $mysqli->prepare("INSERT INTO `transaction` (`account_id`, `amount`, `description`, `type`) VALUES (?, ?, ?, ?)");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('iiss', $accountId, $amount, $description, $type);
        $stmt->execute();
        $transactionId = $stmt->insert_id;
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'Transaction created', 'transaction_id' => $transactionId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    // PUT /admin/transactions/{transactionId}
    public function adminUpdate(Request $request, Response $response, $args)
    {
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data        = $request->getParsedBody();
        $amount      = isset($data['amount']) ? trim($data['amount']) : null;
        $description = isset($data['description']) ? trim($data['description']) : null;
        $type        = isset($data['type']) ? strtolower(trim($data['type'])) : null;

        $fields = [];
        $types  = '';
        $params = [];

        if ($amount !== null) {
            if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                $response->getBody()->write(json_encode(['error' => 'amount must be greater than zero']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`amount` = ?';
            $types   .= 'i';
            $params[] = $amount;
        }
        if ($description !== null) {
            if ($description === '') {
                $response->getBody()->write(json_encode(['error' => 'Missing description']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`description` = ?';
            $types   .= 's';
            $params[] = $description;
        }
        if ($type !== null) {
            if ($type !== 'deposit' && $type !== 'withdrawal') {
                $response->getBody()->write(json_encode(['error' => 'Invalid transaction type']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`type` = ?';
            $types   .= 's';
            $params[] = $type;
        }

        if (count($fields) === 0) {
            $response->getBody()->write(json_encode(['error' => 'No fields to update']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();
        $types .= 'i';
        $params[] = (int) $transactionId;
        $sql = 'UPDATE `transaction` SET ' . implode(', ', $fields) . ' WHERE id = ?';

        $stmt = $mysqli->prepare($sql);
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Transaction updated']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /admin/transactions/{transactionId}
    public function adminDelete(Request $request, Response $response, $args)
    {
        $transactionId = $args['transactionId'] ?? '';
        if (! is_numeric($transactionId) || $transactionId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing transaction id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("DELETE FROM `transaction` WHERE id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'transaction not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Transaction deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

}
