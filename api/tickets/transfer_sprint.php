<?php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/ticket_helpers.php';
require_once __DIR__ . '/../../includes/ticket_history.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

$body = readJsonBody();
$id = requirePositiveInt($body['id'] ?? null, 'ticket id');
$targetSprint = requireNonEmptyString($body['targetSprint'] ?? null, 'target sprint', 100);
$transferReason = trim((string) ($body['transferReason'] ?? ''));

try {
    $pdo = assertDatabaseConnection();
    dbTransaction($pdo, function (PDO $pdo) use ($id, $targetSprint, $transferReason) {
        $before = fetchTicketRowById($pdo, $id);
        if ($before === null) {
            throw new TicketNotFoundException();
        }

        transferTicketSprint($pdo, $id, $targetSprint, $transferReason);

        if (ticketHistoryTableExists($pdo)) {
            $after = fetchTicketRowById($pdo, $id);
            if ($after !== null) {
                $changes = diffTicketSnapshots($before, $after);
                recordTicketHistory(
                    $pdo,
                    $id,
                    $after['Change ID'],
                    'transferred',
                    ['changes' => $changes, 'snapshot' => $after]
                );
            }
        }
    });

    $pdo = getDb();
    $ticket = fetchTicketRowById($pdo, $id);
    jsonSuccess(['id' => $id, 'ticket' => $ticket]);
} catch (TicketNotFoundException $e) {
    jsonError('Ticket not found.', 404);
} catch (TicketValidationException $e) {
    jsonError($e->getMessage());
} catch (PDOException $e) {
    handleDbException($e);
} catch (Throwable $e) {
    logServerError($e);
    jsonError('Unable to transfer ticket.', 500, false);
}
