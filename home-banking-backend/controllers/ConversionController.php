<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../MysqlConnection.php';

class ConversionController
{
    // /accounts/account:id/balance/convert/fiat?to=USD
    public function toFiat(Request $request, Response $response, $args)
    {
        $mysqli = MysqlConnection::getInstance();
        if ($mysqli->connect_error) {
            $response->getBody()->write(json_encode(['error' => 'Database connection failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        //get account id and currency to convert to
        $id    = $args['account'] ?? null;
        $query = $request->getQueryParams();
        $to    = strtoupper($query['to'] ?? '');

        if (! $id || ! is_numeric($id)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (! $to) {
            $response->getBody()->write(json_encode(['error' => 'Missing target currency (to)']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

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
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //get user balance and currency
        $stmt = $mysqli->prepare("SELECT currency, (IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'deposit'),0)  - IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'withdrawal'),0)) as balance FROM account WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('iii', $id, $id, $id);
        $stmt->execute();
        $result  = $stmt->get_result();
        $account = $result->fetch_assoc();
        if (! $account) {
            $mysqli->close();
            $response->getBody()->write(json_encode(['error' => 'Account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $from    = strtoupper($account['currency'] ?? 'EUR');
        $balance = $account['balance'];
        $stmt->close();

        //contact frankfurter api to convert currencies
        if ($from != $to) {
            $url         = "https://api.frankfurter.dev/v2/rate/{$from}/{$to}";
            $opts        = ['http' => ['timeout' => 5, 'ignore_errors' => true]];
            $context     = stream_context_create($opts);
            $apiResponse = @file_get_contents($url, false, $context);

            if ($apiResponse === false) {
                $mysqli->close();
                $response->getBody()->write(json_encode(['error' => 'Currency conversion service unavailable']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(502);
            }

            $data = json_decode($apiResponse, true);
            if (! isset($data['rate'])) {
                $mysqli->close();
                $response->getBody()->write(json_encode(['error' => 'Conversion failed or unsupported currency']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $rate            = (float) $data['rate'];
            $convertedAmount = $balance * $rate;
        } else {
            // if converting from same currency to same currency, set rate to 1
            $rate            = 1.0;
            $convertedAmount = $balance;
        }

        $mysqli->close();

        $payload = [
            'account_id'        => $id,
            'provider'          => 'frankfurter',
            'conversion_type'   => 'fiat',
            'from_currency'     => $from,
            'to_currency'       => $to,
            'original_balance'  => $balance,
            'rate'              => $rate,
            'converted_balance' => $convertedAmount,
            'date'              => date('c'),
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    // /accounts/account:id/balance/convert/crypto?to=BTC
    // https://api.binance.com/api/v3/avgPrice?symbol={$marketsymbol}
    public function toCrypto(Request $request, Response $response, $args)
    {
        $mysqli = MysqlConnection::getInstance();
        if ($mysqli->connect_error) {
            $response->getBody()->write(json_encode(['error' => 'Database connection failed']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        //get account id and crypto to convert to
        $id    = $args['account'] ?? null;
        $query = $request->getQueryParams();
        $to    = strtoupper($query['to'] ?? '');

        if (! $id || ! is_numeric($id)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid or missing account id']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if (! $to) {
            $response->getBody()->write(json_encode(['error' => 'Missing target crypto (to)']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

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
        $stmt->bind_param('ii', $id, $userId);
        $stmt->execute();
        $accountExists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (! $accountExists) {
            $response->getBody()->write(json_encode(['error' => 'account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        //get user balance and currency
        $stmt = $mysqli->prepare("SELECT currency, (IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'deposit'),0)  - IFNULL((SELECT SUM(amount) as withdrawals FROM `transaction` WHERE account_id = ? AND `type` = 'withdrawal'),0)) as balance FROM account WHERE id = ? LIMIT 1");
        if (! $stmt) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            $mysqli->close();
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
        $stmt->bind_param('iii', $id, $id, $id);
        $stmt->execute();
        $result  = $stmt->get_result();
        $account = $result->fetch_assoc();
        if (! $account) {
            $mysqli->close();
            $response->getBody()->write(json_encode(['error' => 'Account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $from    = strtoupper($account['currency'] ?? 'EUR');
        $symbol  = $to . $from;
        $balance = $account['balance'];
        $stmt->close();

        //contact binance api to convert
        $url         = "https://api.binance.com/api/v3/avgPrice?symbol={$symbol}";
        $opts        = ['http' => ['timeout' => 5, 'ignore_errors' => true]];
        $context     = stream_context_create($opts);
        $apiResponse = @file_get_contents($url, false, $context);

        if ($apiResponse === false) {
            $mysqli->close();
            $response->getBody()->write(json_encode(['error' => 'Crypto conversion service unavailable']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(502);
        }

        $data = json_decode($apiResponse, true);
        if (! isset($data['price'])) {
            $mysqli->close();
            $response->getBody()->write(json_encode(['error' => 'Conversion failed or unsupported market']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        $price           = (float) $data['price'];
        $convertedAmount = $balance / $price;

        $mysqli->close();

        $payload = [
            'account_id'       => $id,
            'provider'         => 'binance',
            'conversion_type'  => 'crypto',
            'from_currency'    => $from,
            'to_crypto'        => $to,
            'market_symbol'    => $symbol,
            'original_balance' => $balance,
            'price'            => $price,
            'converted_amount' => $convertedAmount,
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

}
