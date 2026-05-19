Place files in your \xampp\htdocs and run apache and mysql using xampp to run
then execute the init.sql dump query in myphpadmin to initialize the db

## 2. Schema del database

Lo schema è definito in `build/init.sql`.

![Schema concettuale del database](./schema-concettuale.png)

### Tabella `account`
- `id` int(11) NOT NULL AUTO_INCREMENT
- `name` varchar(20) NOT NULL
- `surname` varchar(20) NOT NULL
- `currency` varchar(10) NOT NULL
- `created_at` datetime NOT NULL DEFAULT current_timestamp()
- PRIMARY KEY (`id`)

### Tabella `transaction`
- `id` int(11) NOT NULL AUTO_INCREMENT
- `account_id` int(11) NOT NULL
- `type` enum('deposit','withdrawal') NOT NULL
- `amount` int(11) NOT NULL
- `description` varchar(100) NOT NULL
- `created_at` datetime NOT NULL DEFAULT current_timestamp()
- PRIMARY KEY (`id`)
- KEY `account_id` (`account_id`)
- FOREIGN KEY (`account_id`) REFERENCES `account` (`id`) ON DELETE CASCADE

### Dati iniziali di esempio
- Account 1: `Paride Ficiente`, valuta `USD`
- Account 2: `Mimas Turbo`, valuta `EUR`
- Account 3: `Musso Leeni`, valuta `USD`
- Account 4: `Lamin Kiadura`, valuta `YEN`

Transazioni di esempio:
- deposito 500 per account 1
- prelievo 200 per account 1
- deposito 500 per account 2
- prelievo 150 per account 2

## 3. Elenco degli endpoint realizzati

### Transazioni
- `GET /accounts/{account}/transactions`
  - restituisce tutte le transazioni per l'account specificato
- `GET /accounts/{account}/transactions/{transactionId}`
  - restituisce i dettagli di una transazione specifica
- `POST /accounts/{account}/deposit`
  - registra un deposito sull'account specificato
- `POST /accounts/{account}/withdrawal`
  - registra un prelievo sull'account specificato
- `PUT /accounts/{account}/transactions/{transactionId}`
  - modifica la descrizione di una transazione esistente
- `DELETE /accounts/{account}/transactions/{transactionId}`
  - elimina una transazione solo se è l'ultima nella cronologia dell'account

### Saldo e conversione
- `GET /accounts/{account}/balance`
  - calcola il saldo dell'account come differenza tra depositi e prelievi
- `GET /accounts/{account}/balance/convert/fiat?to={CURRENCY}`
  - converte il saldo dall valuta dell'account a una valuta fiat di destinazione
- `GET /accounts/{account}/balance/convert/crypto?to={SYMBOL}`
  - converte il saldo dalla valuta dell'account a una criptovaluta

## 4. Esempi di chiamata per ogni endpoint

### `GET /accounts/{account}/transactions`
```bash
curl -X GET http://localhost:8080/accounts/1/transactions
```

### `GET /accounts/{account}/transactions/{transactionId}`
```bash
curl -X GET http://localhost:8080/accounts/1/transactions/2
```

### `POST /accounts/{account}/deposit`
```bash
curl -X POST http://localhost:8080/accounts/1/deposit \
  -H "Content-Type: application/json" \
  -d '{"amount": 200, "description": "Salary"}'
```

### `POST /accounts/{account}/withdrawal`
```bash
curl -X POST http://localhost:8080/accounts/1/withdrawal \
  -H "Content-Type: application/json" \
  -d '{"amount": 50, "description": "Groceries"}'
```

### `PUT /accounts/{account}/transactions/{transactionId}`
```bash
curl -X PUT http://localhost:8080/accounts/1/transactions/2 \
  -H "Content-Type: application/json" \
  -d '{"description": "Updated description"}'
```

### `DELETE /accounts/{account}/transactions/{transactionId}`
```bash
curl -X DELETE http://localhost:8080/accounts/1/transactions/2
```

### `GET /accounts/{account}/balance`
```bash
curl -X GET http://localhost:8080/accounts/1/balance
```

### `GET /accounts/{account}/balance/convert/fiat`
```bash
curl -X GET "http://localhost:8080/accounts/1/balance/convert/fiat?to=EUR"
```

### `GET /accounts/{account}/balance/convert/crypto`
```bash
curl -X GET "http://localhost:8080/accounts/1/balance/convert/crypto?to=BTC"
```

## 5. Scelte progettuali

- **Slim Framework**: la scelta è motivata dalla necessità di un backend semplice, minimalista e facilmente estendibile. Slim offre routing chiaro, middleware per il parsing JSON e sufficiente flessibilità per costruire API REST senza aggiungere complessità.
- **Singleton per la connessione DB**: `MysqlConnection` è usato per centralizzare la gestione della connessione e ridurre l'overhead di aprire più connessioni consecutive. Questo rende il codice più semplice e garantisce che tutti i controller usino la stessa istanza di connessione.
- **Prepared statements**: ogni query usa parametri bindati per ridurre il rischio di SQL injection e migliorare la sicurezza. L'approccio è utile anche per separare la logica SQL dai dati inviati dall'utente.
- **Saldo calcolato dinamicamente**: invece di memorizzare un campo `balance` nella tabella `account`, il saldo viene ricavato al volo come `somma(depositi) - somma(prelievi)`. Questo mantiene lo storico delle transazioni coerente e garantisce che il saldo rifletta sempre l'effettivo stato delle operazioni registrate.
- **Conversioni esterne**: per fornire funzionalità di conversione di valuta fiat e crypto si è scelto di usare servizi esterni già esistenti.
  - la conversione fiat utilizza l'API `frankfurter.dev` perché fornisce tassi aggiornati senza richiedere autenticazione complessa.
  - la conversione crypto utilizza l'API Binance come esempio pratico di prezzo medio di mercato.
- **Regole di business implementate**:
  - il deposito e il prelievo richiedono validazione dell'importo e della descrizione per evitare dati incompleti.
  - il prelievo controlla il saldo disponibile prima di creare la transazione, evitando conti negativi.
  - la cancellazione dei movimenti è permessa solo per l'ultima transazione dell'account, per ridurre le incongruenze nel calcolo del saldo storico e mantenere un comportamento prevedibile.
- **Validazione richiesta**: ogni endpoint controlla la presenza e la correttezza di ID, importi e altri parametri obbligatori (`amount`, `description`, `to`). Questo riduce il rischio di errori runtime e offre risposte HTTP adeguate (`400`, `404`, `500`) in caso di input non valido.
