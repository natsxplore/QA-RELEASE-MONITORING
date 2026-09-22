<?php

require_once __DIR__ . '/ticket_helpers.php';

const TICKET_HISTORY_ACTIONS = ['created', 'updated', 'deleted', 'imported', 'transferred'];

const TICKET_HISTORY_DIFF_FIELDS = [
    'Sprint',
    'Change ID',
    'Title',
    'Change Owner',
    'Assigned QA',
    'Change Stage',
    'Change Status',
    'Change Type',
    'Created Time',
    'Release Status',
    'Remarks',
];

function ticketHistoryTableExists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([DB_NAME, 'ticket_history']);

    return (int) $stmt->fetchColumn() > 0;
}

function diffTicketSnapshots(array $before, array $after): array
{
    $changes = [];
    foreach (TICKET_HISTORY_DIFF_FIELDS as $field) {
        $from = $before[$field] ?? null;
        $to = $after[$field] ?? null;
        if ((string) $from !== (string) $to) {
            $changes[] = [
                'field' => $field,
                'from' => $from,
                'to' => $to,
            ];
        }
    }

    return $changes;
}

function recordTicketHistory(
    PDO $pdo,
    ?int $ticketId,
    string $changeId,
    string $action,
    array $details
): void {
    if (!in_array($action, TICKET_HISTORY_ACTIONS, true)) {
        throw new InvalidArgumentException('Invalid ticket history action.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ticket_history (ticket_id, change_id, action, details) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $ticketId,
        $changeId,
        $action,
        json_encode($details, JSON_UNESCAPED_UNICODE),
    ]);
}

function fetchTicketHistoryForTicket(PDO $pdo, int $ticketId): array
{
    $ticket = fetchTicketRowById($pdo, $ticketId);
    if ($ticket === null) {
        return [];
    }

    $changeId = $ticket['Change ID'];
    $stmt = $pdo->prepare(
        'SELECT id, ticket_id, change_id, action, details, created_at
         FROM ticket_history
         WHERE ticket_id = ? OR change_id = ?
         ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute([$ticketId, $changeId]);

    return array_map('formatTicketHistoryRow', $stmt->fetchAll());
}

function formatTicketHistoryRow(array $row): array
{
    $details = json_decode($row['details'] ?? '{}', true);
    if (!is_array($details)) {
        $details = [];
    }

    return [
        'id' => (int) $row['id'],
        'ticket_id' => $row['ticket_id'] !== null ? (int) $row['ticket_id'] : null,
        'change_id' => $row['change_id'],
        'action' => $row['action'],
        'action_label' => ticketHistoryActionLabel($row['action']),
        'created_at' => formatCreatedTimeDisplay($row['created_at']),
        'changes' => $details['changes'] ?? [],
        'snapshot' => $details['snapshot'] ?? null,
    ];
}

function ticketHistoryActionLabel(string $action): string
{
    return match ($action) {
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'imported' => 'Imported',
        'transferred' => 'Transferred',
        default => ucfirst($action),
    };
}
