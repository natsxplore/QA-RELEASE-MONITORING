<?php
/**
 * API integration tests — run with local PHP server:
 *   php -S localhost:8002 -t .
 *   php tests/run_api_tests.php --base-url=http://localhost:8002
 */

declare(strict_types=1);

$baseUrl = 'http://localhost:8002';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = rtrim(substr($arg, 11), '/');
    }
}

$passed = 0;
$failed = 0;
$createdTicketId = null;
$testChangeId = 'CH-TEST-' . substr((string) time(), -4);
$testSprint = 'Test Sprint API ' . substr((string) time(), -4);
$testDev = 'API Test Dev';
$testQa = 'API Test QA';

function httpGet(string $url): array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['success' => false, 'message' => 'HTTP GET failed', 'connected' => false];
    }
    $data = json_decode($body, true);

    return is_array($data) ? $data : ['success' => false, 'message' => 'Invalid JSON', 'connected' => false];
}

function httpPost(string $url, array $payload): array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true,
            'timeout' => 15,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['success' => false, 'message' => 'HTTP POST failed', 'connected' => false];
    }
    $data = json_decode($body, true);

    return is_array($data) ? $data : ['success' => false, 'message' => 'Invalid JSON', 'connected' => false];
}

function testCase(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $ok = (bool) $fn();
        if ($ok) {
            $passed++;
            echo "[PASS] {$name}\n";
        } else {
            $failed++;
            echo "[FAIL] {$name}\n";
        }
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$name} — " . $e->getMessage() . "\n";
    }
}

echo "QA Release Monitoring — API tests\n";
echo "Base URL: {$baseUrl}\n\n";

require_once __DIR__ . '/test_helpers.php';

testCase('Helper unit tests bundle', function () {
    $localPassed = 0;
    $localFailed = 0;
    $assert = function (string $name, callable $fn) use (&$localPassed, &$localFailed) {
        try {
            if ($fn()) {
                $localPassed++;
            } else {
                $localFailed++;
                echo "  [FAIL helper] {$name}\n";
            }
        } catch (Throwable $e) {
            $localFailed++;
            echo "  [FAIL helper] {$name} — " . $e->getMessage() . "\n";
        }
    };
    runHelperUnitTests($assert);

    return $localFailed === 0 && $localPassed > 0;
});

require_once __DIR__ . '/../includes/database.php';

testCase('1 — Database status connected', function () {
    $status = getDatabaseStatus();

    return $status['connected'] === true;
});

testCase('2 — GET api/data.php', function () use ($baseUrl) {
    $json = httpGet("{$baseUrl}/api/data.php");

    return $json['success'] === true
        && $json['connected'] === true
        && isset($json['data']['tickets'])
        && is_array($json['data']['tickets']);
});

testCase('3 — POST api/sprints/create.php', function () use ($baseUrl, $testSprint) {
    $json = httpPost("{$baseUrl}/api/sprints/create.php", ['sprintName' => $testSprint]);

    return $json['success'] === true && !empty($json['data']['id']);
});

testCase('4 — POST api/members/create.php (Developer + QA)', function () use ($baseUrl, $testDev, $testQa) {
    $dev = httpPost("{$baseUrl}/api/members/create.php", ['name' => $testDev, 'role' => 'Developer']);
    $qa = httpPost("{$baseUrl}/api/members/create.php", ['name' => $testQa, 'role' => 'QA']);

    return $dev['success'] === true && $qa['success'] === true;
});

testCase('5 — POST api/tickets/create.php', function () use ($baseUrl, $testChangeId, $testSprint, $testDev, $testQa, &$createdTicketId) {
    global $createdTicketId;
    $json = httpPost("{$baseUrl}/api/tickets/create.php", [
        'ticket' => [
            'Sprint' => $testSprint,
            'Change ID' => $testChangeId,
            'Title' => 'Automated API test ticket',
            'Change Owner' => $testDev,
            'Assigned QA' => $testQa,
            'Change Type' => 'Normal',
            'Release Status' => 'For release',
        ],
    ]);
    if ($json['success'] !== true || empty($json['data']['id'])) {
        return false;
    }
    $createdTicketId = (int) $json['data']['id'];
    $data = httpGet("{$baseUrl}/api/data.php");
    $found = false;
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if (($row['Change ID'] ?? '') === $testChangeId) {
            $found = true;
            break;
        }
    }

    return $found;
});

testCase('6 — POST api/tickets/update.php', function () use ($baseUrl, $testChangeId, $testSprint, $testDev, $testQa, &$createdTicketId) {
    global $createdTicketId;
    if (!$createdTicketId) {
        return false;
    }
    $data = httpGet("{$baseUrl}/api/data.php");
    $existing = null;
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if ((int) ($row['id'] ?? 0) === $createdTicketId) {
            $existing = $row;
            break;
        }
    }
    if (!$existing) {
        return false;
    }
    $json = httpPost("{$baseUrl}/api/tickets/update.php", [
        'id' => $createdTicketId,
        'ticket' => [
            'Sprint' => $testSprint,
            'Change ID' => $testChangeId,
            'Title' => 'Automated API test ticket (updated)',
            'Change Owner' => $testDev,
            'Assigned QA' => $testQa,
            'Change Type' => 'Normal',
            'Release Status' => 'Released',
            'Change Stage' => $existing['Change Stage'],
            'Change Status' => $existing['Change Status'],
            'Created Time' => $existing['Created Time'],
        ],
    ]);
    if ($json['success'] !== true) {
        return false;
    }
    $data = httpGet("{$baseUrl}/api/data.php");
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if ((int) ($row['id'] ?? 0) === $createdTicketId) {
            return ($row['Release Status'] ?? '') === 'Released';
        }
    }

    return false;
});

testCase('7 — Duplicate Change ID rejected', function () use ($baseUrl, $testChangeId, $testSprint, $testDev, $testQa) {
    $json = httpPost("{$baseUrl}/api/tickets/create.php", [
        'ticket' => [
            'Sprint' => $testSprint,
            'Change ID' => $testChangeId,
            'Title' => 'Duplicate attempt',
            'Change Owner' => $testDev,
            'Assigned QA' => $testQa,
            'Change Type' => 'Normal',
            'Release Status' => 'For release',
        ],
    ]);

    return $json['success'] === false && !empty($json['message']);
});

testCase('8 — Invalid ticket payload validation', function () use ($baseUrl) {
    $json = httpPost("{$baseUrl}/api/tickets/create.php", [
        'ticket' => [
            'Sprint' => 'Sprint 10',
            'Change ID' => 'CH-TEST-INVALID',
            'Title' => '',
            'Change Owner' => 'Someone',
            'Change Type' => 'Normal',
            'Release Status' => 'For release',
        ],
    ]);

    return $json['success'] === false;
});

testCase('9 — POST api/tickets/import.php (insert + upsert)', function () use ($baseUrl, $testSprint, $testDev, $testQa) {
    $importId = 'CH-TEST-IMP-' . random_int(1000, 9999);
    $rowPayload = [
        'Sprint' => $testSprint,
        'Change ID' => $importId,
        'Title' => 'Import row test',
        'Change Owner' => $testDev,
        'Assigned QA' => $testQa,
        'Change Stage' => 'Development',
        'Change Status' => 'Active',
        'Change Type' => 'Normal',
        'Release Status' => 'For release',
    ];
    $json = httpPost("{$baseUrl}/api/tickets/import.php", ['tickets' => [$rowPayload]]);
    if ($json['success'] !== true || ($json['data']['imported'] ?? 0) < 1) {
        return false;
    }

    $rowPayload['Title'] = 'Import row test (overwritten)';
    $json2 = httpPost("{$baseUrl}/api/tickets/import.php", ['tickets' => [$rowPayload]]);
    if ($json2['success'] !== true || ($json2['data']['updated'] ?? 0) < 1) {
        return false;
    }

    $data = httpGet("{$baseUrl}/api/data.php");
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if (($row['Change ID'] ?? '') === $importId) {
            if (($row['Title'] ?? '') !== 'Import row test (overwritten)') {
                return false;
            }
            httpPost("{$baseUrl}/api/tickets/delete.php", ['id' => $row['id']]);

            return true;
        }
    }

    return false;
});

testCase('12 — GET api/tickets/history.php', function () use ($baseUrl, &$createdTicketId) {
    global $createdTicketId;
    if (!$createdTicketId) {
        return false;
    }
    $json = httpGet("{$baseUrl}/api/tickets/history.php?id={$createdTicketId}");
    if ($json['success'] !== true) {
        return false;
    }
    $history = $json['data']['history'] ?? [];
    $actions = array_column($history, 'action');

    return in_array('created', $actions, true) && in_array('updated', $actions, true);
});

testCase('13 — POST api/tickets/transfer_sprint.php', function () use ($baseUrl, &$createdTicketId, $testSprint) {
    global $createdTicketId;
    if (!$createdTicketId) {
        return false;
    }
    $targetSprint = 'Test Sprint Transfer ' . substr((string) time(), -4);
    $sprintRes = httpPost("{$baseUrl}/api/sprints/create.php", ['sprintName' => $targetSprint]);
    if ($sprintRes['success'] !== true) {
        return false;
    }
    $json = httpPost("{$baseUrl}/api/tickets/transfer_sprint.php", [
        'id' => $createdTicketId,
        'targetSprint' => $targetSprint,
        'transferReason' => 'API test transfer',
    ]);
    if ($json['success'] !== true) {
        return false;
    }
    $data = httpGet("{$baseUrl}/api/data.php");
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if ((int) ($row['id'] ?? 0) === $createdTicketId) {
            $remarks = $row['Remarks'] ?? '';
            return ($row['Sprint'] ?? '') === $targetSprint
                && str_contains($remarks, 'Transferred from')
                && str_contains($remarks, $testSprint);
        }
    }

    return false;
});

testCase('10 — POST api/tickets/delete.php', function () use ($baseUrl, &$createdTicketId) {
    global $createdTicketId;
    if (!$createdTicketId) {
        return false;
    }
    $json = httpPost("{$baseUrl}/api/tickets/delete.php", ['id' => $createdTicketId]);
    if ($json['success'] !== true) {
        return false;
    }
    $data = httpGet("{$baseUrl}/api/data.php");
    foreach ($data['data']['tickets'] ?? [] as $row) {
        if ((int) ($row['id'] ?? 0) === $createdTicketId) {
            return false;
        }
    }
    $createdTicketId = null;

    return true;
});

testCase('11 — GET on create endpoint returns 405', function () use ($baseUrl) {
    $json = httpGet("{$baseUrl}/api/tickets/create.php");

    return ($json['success'] ?? true) === false;
});

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
