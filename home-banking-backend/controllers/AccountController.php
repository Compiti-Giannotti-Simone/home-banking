<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../MysqlConnection.php';

class AccountController
{
    // POST /accounts/register
    public function register(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $name     = isset($data['name']) ? trim($data['name']) : '';
        $surname  = isset($data['surname']) ? trim($data['surname']) : '';
        $currency = isset($data['currency']) ? strtoupper(trim($data['currency'])) : '';
        $username = isset($data['username']) ? trim($data['username']) : '';
        $email    = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($name === '' || $surname === '' || $currency === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing name, surname, or currency']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($username === '' && $email === '') {
            $response->getBody()->write(json_encode(['error' => 'Provide a username or email']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid email address']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($password === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing password']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($username !== '' || $email !== '') {
            if ($username !== '' && $email !== '') {
                $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE username = ? OR email = ? LIMIT 1");
                if (! $stmt) {
                    $response->getBody()->write(json_encode(['error' => 'Database error']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
                $stmt->bind_param('ss', $username, $email);
            } elseif ($username !== '') {
                $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE username = ? LIMIT 1");
                if (! $stmt) {
                    $response->getBody()->write(json_encode(['error' => 'Database error']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
                $stmt->bind_param('s', $username);
            } else {
                $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE email = ? LIMIT 1");
                if (! $stmt) {
                    $response->getBody()->write(json_encode(['error' => 'Database error']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
                }
                $stmt->bind_param('s', $email);
            }

            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                $response->getBody()->write(json_encode(['error' => 'Username or email already in use']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            $response->getBody()->write(json_encode(['error' => 'Password hashing failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $stmt = $mysqli->prepare("INSERT INTO `user` (`name`, `surname`, `username`, `email`, `password_hash`) VALUES (?, ?, ?, ?, ?)");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('sssss', $name, $surname, $username, $email, $passwordHash);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO `account` (`user_id`, `currency`) VALUES (?, ?)");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('is', $userId, $currency);
        $stmt->execute();
        $accountId = $stmt->insert_id;
        $stmt->close();

        $_SESSION['user_id'] = $userId;

        $response->getBody()->write(json_encode(['message' => 'User registered', 'user_id' => $userId, 'account_id' => $accountId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    // POST /accounts/login
    public function login(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $username = isset($data['username']) ? trim($data['username']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($username === '') {
            $response->getBody()->write(json_encode(['error' => 'Provide a username or email']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        if ($password === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing password']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($username !== '' && $email !== '') {
            $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, password_hash FROM `user` WHERE username = ? OR email = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('ss', $username, $username);
        }

        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (! $account || ! password_verify($password, $account['password_hash'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $stmt = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $account['id']);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $_SESSION['user_id'] = $account['id'];

        $payload = [
            'user_id'  => $account['id'],
            'name'     => $account['name'],
            'surname'  => $account['surname'],
            'username' => $account['username'],
            'email'    => $account['email'],
            'accounts' => $accounts,
        ];

        $response->getBody()->write(json_encode($payload));
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
}
