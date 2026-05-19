<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../MysqlConnection.php';

class UserController
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
            $isAdmin = $this->fetchIsAdmin($userId);
            $_SESSION['is_admin'] = $isAdmin ? 1 : 0;
        }

        if (! $isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'Forbidden']));
            return 403;
        }

        return 200;
    }

    private function fetchIsAdmin($userId)
    {
        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("SELECT is_admin FROM `user` WHERE id = ? LIMIT 1");
        if (! $stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (bool) ($row['is_admin'] ?? 0);
    }

    // POST /users/register
    public function register(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $name     = isset($data['name']) ? trim($data['name']) : '';
        $surname  = isset($data['surname']) ? trim($data['surname']) : '';
        $username = isset($data['username']) ? trim($data['username']) : '';
        $email    = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

        if ($name === '' || $surname === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing name or surname']));
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

        $_SESSION['user_id']  = $userId;
        $_SESSION['is_admin'] = 0;

        $response->getBody()->write(json_encode(['message' => 'User registered', 'user_id' => $userId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    // POST /users/login
    public function login(Request $request, Response $response)
    {
        $data     = $request->getParsedBody();
        $username = isset($data['username']) ? trim($data['username']) : '';
        $email    = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';

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
            $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, password_hash, is_admin FROM `user` WHERE username = ? OR email = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('ss', $username, $email);
        } elseif ($username !== '') {
            $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, password_hash, is_admin FROM `user` WHERE username = ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('s', $username);
        } else {
            $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, password_hash, is_admin FROM `user` WHERE email = ? LIMIT 1");
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

        $stmt = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $_SESSION['user_id']  = $user['id'];
        $_SESSION['is_admin'] = (int) ($user['is_admin'] ?? 0);

        $payload = [
            'user_id'  => $user['id'],
            'name'     => $user['name'],
            'surname'  => $user['surname'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'accounts' => $accounts,
            'is_admin' => (bool) ($user['is_admin'] ?? 0),
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // GET /users/me
    public function getCurrentUser(Request $request, Response $response)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();

        $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, is_admin, created_at FROM `user` WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (! $user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $payload = [
            'user'     => $user,
            'accounts' => $accounts,
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // GET /users/me/accounts
    public function getCurrentUserAccounts(Request $request, Response $response)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (! $userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
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

    // GET /users/{id}/accounts
    public function getUserAccountsById(Request $request, Response $response, $args)
    {
        $targetId = $args['id'] ?? '';
        if (! is_numeric($targetId) || $targetId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing user id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response->getBody()->write(json_encode(['accounts' => $accounts]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // GET /users/{id}
    public function getUserById(Request $request, Response $response, $args)
    {
        $targetId = $args['id'] ?? '';
        if (! is_numeric($targetId) || $targetId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing user id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();

        $stmt = $mysqli->prepare("SELECT id, name, surname, username, email, is_admin, created_at FROM `user` WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (! $user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $mysqli->prepare("SELECT id, currency, created_at FROM `account` WHERE user_id = ? ORDER BY id ASC");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $payload = [
            'user'     => $user,
            'accounts' => $accounts,
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // POST /admin/users
    public function adminCreate(Request $request, Response $response)
    {
        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data     = $request->getParsedBody();
        $name     = isset($data['name']) ? trim($data['name']) : '';
        $surname  = isset($data['surname']) ? trim($data['surname']) : '';
        $username = isset($data['username']) ? trim($data['username']) : '';
        $email    = isset($data['email']) ? trim($data['email']) : '';
        $password = isset($data['password']) ? $data['password'] : '';
        $isAdmin  = isset($data['is_admin']) ? (int) $data['is_admin'] : 0;

        if ($name === '' || $surname === '') {
            $response->getBody()->write(json_encode(['error' => 'Missing name or surname']));
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

        $stmt = $mysqli->prepare("INSERT INTO `user` (`name`, `surname`, `username`, `email`, `password_hash`, `is_admin`) VALUES (?, ?, ?, ?, ?, ?)");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('sssssi', $name, $surname, $username, $email, $passwordHash, $isAdmin);
        $stmt->execute();
        $userId = $stmt->insert_id;
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'User created', 'user_id' => $userId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    // PUT /admin/users/{id}
    public function adminUpdate(Request $request, Response $response, $args)
    {
        $targetId = $args['id'] ?? '';
        if (! is_numeric($targetId) || $targetId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing user id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $data     = $request->getParsedBody();
        $name     = isset($data['name']) ? trim($data['name']) : null;
        $surname  = isset($data['surname']) ? trim($data['surname']) : null;
        $username = isset($data['username']) ? trim($data['username']) : null;
        $email    = isset($data['email']) ? trim($data['email']) : null;
        $password = isset($data['password']) ? $data['password'] : null;
        $isAdmin  = isset($data['is_admin']) ? (int) $data['is_admin'] : null;

        if ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid email address']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $fields = [];
        $types  = '';
        $params = [];

        if ($name !== null) {
            $fields[] = '`name` = ?';
            $types   .= 's';
            $params[] = $name;
        }
        if ($surname !== null) {
            $fields[] = '`surname` = ?';
            $types   .= 's';
            $params[] = $surname;
        }
        if ($username !== null) {
            $fields[] = '`username` = ?';
            $types   .= 's';
            $params[] = $username;
        }
        if ($email !== null) {
            $fields[] = '`email` = ?';
            $types   .= 's';
            $params[] = $email;
        }
        if ($password !== null && $password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $response->getBody()->write(json_encode(['error' => 'Password hashing failed']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $fields[] = '`password_hash` = ?';
            $types   .= 's';
            $params[] = $passwordHash;
        }
        if ($isAdmin !== null) {
            $fields[] = '`is_admin` = ?';
            $types   .= 'i';
            $params[] = $isAdmin ? 1 : 0;
        }

        if (count($fields) === 0) {
            $response->getBody()->write(json_encode(['error' => 'No fields to update']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mysqli = MysqlConnection::getInstance();

        if ($username !== null && $username !== '') {
            $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE username = ? AND id <> ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('si', $username, $targetId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) {
                $response->getBody()->write(json_encode(['error' => 'Username already in use']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
        }

        if ($email !== null && $email !== '') {
            $stmt = $mysqli->prepare("SELECT 1 FROM `user` WHERE email = ? AND id <> ? LIMIT 1");
            if (! $stmt) {
                $response->getBody()->write(json_encode(['error' => 'Database error']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            $stmt->bind_param('si', $email, $targetId);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) {
                $response->getBody()->write(json_encode(['error' => 'Email already in use']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(409);
            }
        }

        $types .= 'i';
        $params[] = (int) $targetId;
        $sql = 'UPDATE `user` SET ' . implode(', ', $fields) . ' WHERE id = ?';

        $stmt = $mysqli->prepare($sql);
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        $response->getBody()->write(json_encode(['message' => 'User updated']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // DELETE /admin/users/{id}
    public function adminDelete(Request $request, Response $response, $args)
    {
        $targetId = $args['id'] ?? '';
        if (! is_numeric($targetId) || $targetId === '') {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing user id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $status = $this->ensureAdmin($response);
        if ($status !== 200) {
            return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
        }

        $mysqli = MysqlConnection::getInstance();
        $stmt   = $mysqli->prepare("DELETE FROM `user` WHERE id = ?");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode(['message' => 'User deleted']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
