<?php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../../includes/validation.php';
require_once __DIR__ . '/../../includes/ticket_helpers.php';
require_once __DIR__ . '/../../includes/ticket_history.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed.', 405);
}

$body = readJsonBody();
$rows = $body['tickets'] ?? null;
if (!is_array($rows) || count($rows) === 0) {
    jsonError('No tickets to import.');
}

try {
    $pdo = assertDatabaseConnection();
    $summary = dbTransaction($pdo, function (PDO $pdo) use ($rows) {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $logHistory = ticketHistoryTableExists($pdo);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped++;
                $errors[] = 'Row ' . ($index + 1) . ': invalid format.';
                continue;
            }

            $changeIdKey = trim((string) ($row['Change ID'] ?? $row['change_id'] ?? ''));
            $existingId = $changeIdKey !== '' ? findTicketIdByChangeId($pdo, $changeIdKey) : null;
            $before = $existingId !== null ? fetchTicketRowById($pdo, $existingId) : null;

            try {
                $result = upsertTicketFromImport($pdo, $row);
            } catch (TicketValidationException $e) {
                $skipped++;
                $errors[] = 'Row ' . ($index + 1) . ': ' . $e->getMessage();
                continue;
            }

            if ($logHistory) {
                $snapshot = fetchTicketRowById($pdo, $result['id']);
                if ($snapshot !== null) {
                    if ($result['mode'] === 'insert') {
                        recordTicketHistory(
                            $pdo,
                            $result['id'],
                            $snapshot['Change ID'],
                            'imported',
                            ['snapshot' => $snapshot]
                        );
                    } else {
                        $changes = $before !== null ? diffTicketSnapshots($before, $snapshot) : [];
                        recordTicketHistory(
                            $pdo,
                            $result['id'],
                            $snapshot['Change ID'],
                            'updated',
                            ['changes' => $changes, 'snapshot' => $snapshot]
                        );
                    }
                }
            }

            if ($result['mode'] === 'insert') {
                $imported++;
            } else {
                $updated++;
            }
        }

        if ($imported === 0 && $updated === 0 && $skipped > 0) {
            throw new TicketValidationException('No tickets were imported. Fix the rows reported in errors.');
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    });

    jsonSuccess($summary);
} catch (TicketValidationException $e) {
    jsonError($e->getMessage());
} catch (PDOException $e) {
    handleDbException($e);
}
