<?php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/ticket_helpers.php';
require_once __DIR__ . '/../../includes/ticket_history.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

$body = readJsonBody();
$ticket = $body['ticket'] ?? $body;
if (!is_array($ticket)) {
    jsonError('Invalid ticket data.');
}

try {
    $fields = ticketPayloadFromRequest($ticket, false);
    $pdo = assertDatabaseConnection();
    $newId = dbTransaction($pdo, function (PDO $pdo) use ($fields) {
        $newId = insertTicket($pdo, $fields);
        if (ticketHistoryTableExists($pdo)) {
            $snapshot = fetchTicketRowById($pdo, $newId);
            if ($snapshot !== null) {
                recordTicketHistory(
                    $pdo,
                    $newId,
                    $snapshot['Change ID'],
                    'created',
                    ['snapshot' => $snapshot]
                );
            }
        }

        return $newId;
    });

    jsonSuccess(['id' => $newId]);
} catch (TicketValidationException $e) {
    jsonError($e->getMessage());
} catch (PDOException $e) {
    handleDbException($e);
} catch (Throwable $e) {
    logServerError($e);
    jsonError('Unable to create ticket.', 500, false);
}
