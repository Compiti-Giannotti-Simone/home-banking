<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../MysqlConnection.php';

class AccountController
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

    // GET /accounts
    public function getUserAccounts(Request $request, Response $response)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt = $mysqli->prepare(
            "SELECT a.id, a.currency, a.created_at, "
            . "IFNULL(SUM(CASE WHEN t.type = 'deposit' THEN t.amount WHEN t.type = 'withdrawal' THEN -t.amount ELSE 0 END), 0) AS balance "
            . "FROM `account` a "
            . "LEFT JOIN `transaction` t ON t.account_id = a.id "
            . "WHERE a.user_id = ? "
            . "GROUP BY a.id "
            . "ORDER BY a.id ASC"
        );
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response->getBody()->write(json_encode(['accounts' => $accounts]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // POST /accounts
    public function createAccount(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : '';

        if ($currency === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing currency']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();

        $stmt = $mysqli->prepare("SELECT COUNT(*) AS total FROM `account` WHERE user_id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int) ($row['total'] ?? 0) >= 5) {
            $response->getBody()->write(json_encode(['error' => 'Account limit reached (max 5 per user)']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $stmt = $mysqli->prepare("SELECT 1 FROM `account` WHERE user_id = ? AND currency = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('is', $userId, $currency);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $response->getBody()->write(json_encode(['error' => 'Account for this currency already exists']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        $stmt = $mysqli->prepare("INSERT INTO `account` (`user_id`, `currency`) VALUES (?, ?)");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('is', $userId, $currency);
        $stmt->execute();
        $accountId = $stmt->insert_id;
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'Account created', 'account_id' => $accountId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }


    // GET /accounts/{account}
    public function getUserAccountById(Request $request, Response $response, $args)
    {
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare(
            "SELECT a.id, a.user_id, a.currency, a.created_at, "
            . "IFNULL(SUM(CASE WHEN t.type = 'deposit' THEN t.amount WHEN t.type = 'withdrawal' THEN -t.amount ELSE 0 END), 0) AS balance "
            . "FROM `account` a "
            . "LEFT JOIN `transaction` t ON t.account_id = a.id "
            . "WHERE a.id = ? AND a.user_id = ? "
            . "GROUP BY a.id LIMIT 1"
        );
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (! $account) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['account' => $account]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // GET /admin/accounts
    public function getAllAccounts(Request $request, Response $response)
    {
        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt = $mysqli->prepare(
            "SELECT a.id, a.user_id, a.currency, a.created_at, "
            . "IFNULL(SUM(CASE WHEN t.type = 'deposit' THEN t.amount WHEN t.type = 'withdrawal' THEN -t.amount ELSE 0 END), 0) AS balance "
            . "FROM `account` a "
            . "LEFT JOIN `transaction` t ON t.account_id = a.id "
            . "GROUP BY a.id "
            . "ORDER BY a.id ASC"
        );
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response->getBody()->write(json_encode(['accounts' => $accounts]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /users/me/accounts/{account}
    public function deleteMyAccount(Request $request, Response $response, $args)
    {
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("DELETE FROM `account` WHERE id = ? AND user_id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Account deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /accounts/{account}
    public function deleteAccount(Request $request, Response $response, $args)
    {
        $accountId = $args['account'] ?? '';
        $data      = $request->getParsedBody();
        $username  = isset($data['username']) ? trim($data['username']) : '';
        $email     = isset($data['email']) ? trim($data['email']) : '';
        $password  = isset($data['password']) ? $data['password'] : '';

        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($username === '' && $email === '') {
            $response->getBody()->write(json_encode(['error' => 'Provide a username or email']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($password === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing password']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($username !== '' && $email !== '') {
            $stmt = $mysqli->prepare("SELECT id, password_hash FROM `user` WHERE username = ? OR email = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('ss', $username, $email);
        } elseif ($username !== '') {
            $stmt = $mysqli->prepare("SELECT id, password_hash FROM `user` WHERE username = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('s', $username);
        } else {
            $stmt = $mysqli->prepare("SELECT id, password_hash FROM `user` WHERE email = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('s', $email);
        }

        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (! $user || ! password_verify($password, $user['password_hash'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $stmt = $mysqli->prepare("DELETE FROM `account` WHERE id = ? AND user_id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('ii', $accountId, $user['id']);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Account deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    // PUT /admin/accounts/{account}
    public function adminUpdate(Request $request, Response $response, $args)
    {
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data     = $request->getParsedBody();
        $userId   = isset($data['user_id']) ? $data['user_id'] : null;
        $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : null;

        $fields = [];
        $types  = '';
        $params = [];

        if ($userId !== null) {
            if (! is_numeric($userId) || $userId === '') {
                $response->getBody()->write(json_encode(['error' => 'Invalid user id']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`user_id` = ?';
            $types   .= 'i';
            $params[] = (int) $userId;
        }
        if ($currency !== null) {
            if ($currency === '') {
                $response->getBody()->write(json_encode(['error' => 'Missing currency']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $fields[] = '`currency` = ?';
            $types   .= 's';
            $params[] = $currency;
        }

        if (count($fields) === 0) {
            $response->getBody()->write(json_encode(['error' => 'No fields to update']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($userId !== null) {
            $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE id = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $userExists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (! $userExists) {
                $response->getBody()->write(json_encode(['error' => 'User not found']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        }

        $types .= 'i';
        $params[] = (int) $accountId;
        $sql = 'UPDATE `account` SET ' . implode(', ', $fields) . ' WHERE id = ?';

        $stmt = $mysqli->prepare($sql);
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'Account updated']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /admin/accounts/{account}
    public function adminDelete(Request $request, Response $response, $args)
    {
        $accountId = $args['account'] ?? '';
        if (! is_numeric($accountId) || $accountId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("DELETE FROM `account` WHERE id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $accountId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'Account deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
