<?php
declare(strict_types=1);

const GROUPS = ['Handy', 'Neueinstellung', 'Umbau', 'Software Probleme', 'Allgemein'];
const STATUSES = [
    'open' => 'Offen',
    'in_progress' => 'In Bearbeitung',
    'waiting' => 'Wartet',
    'resolved' => 'Erledigt',
];
const PRIORITIES = [
    'low' => 'Niedrig',
    'normal' => 'Normal',
    'high' => 'Hoch',
    'urgent' => 'Dringend',
];

session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function groupSlug(string $group): string
{
    return match ($group) {
        'IT-Support' => 'it',
        'Abrechnung' => 'billing',
        'Kundenservice' => 'service',
        default => 'general',
    };
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function storagePaths(): array
{
    $directory = dirname(__DIR__) . '/data';

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Der Datenordner konnte nicht erstellt werden.');
    }

    return [
        'data' => $directory . '/tickets.json',
        'lock' => $directory . '/tickets.lock',
    ];
}

function readTickets(): array
{
    $paths = storagePaths();
    $lock = fopen($paths['lock'], 'c');

    if ($lock === false) {
        throw new RuntimeException('Die Ticketdaten können nicht gelesen werden.');
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Die Ticketdaten können nicht gesperrt werden.');
        }

        return readTicketFile($paths['data']);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function readTicketFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Die Ticketdaten konnten nicht gelesen werden.');
    }

    $tickets = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($tickets)) {
        throw new RuntimeException('Die Ticketdaten haben ein ungültiges Format.');
    }

    return $tickets;
}

function modifyTickets(callable $callback): mixed
{
    $paths = storagePaths();
    $lock = fopen($paths['lock'], 'c');

    if ($lock === false) {
        throw new RuntimeException('Die Ticketdaten können nicht gespeichert werden.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Die Ticketdaten können nicht gesperrt werden.');
        }

        $tickets = readTicketFile($paths['data']);
        $result = $callback($tickets);
        $json = json_encode($tickets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($paths['data'], $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Die Ticketdaten konnten nicht gespeichert werden.');
        }

        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function redirectWithFlash(string $type, string $message): never
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: index.php', true, 303);
    exit;
}

function validDate(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $date;
}

function validChoice(string $value, array $choices): bool
{
    return array_key_exists($value, $choices);
}

function textWithinLimit(string $value, int $limit): bool
{
    $length = preg_match_all('/./us', $value);

    return $length !== false && $length <= $limit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals(csrfToken(), $postedToken)) {
        http_response_code(403);
        exit('Die Anfrage ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.');
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $group = (string) ($_POST['group'] ?? '');
        $priority = (string) ($_POST['priority'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $dueDate = (string) ($_POST['due_date'] ?? '');

        if ($title === '' || !textWithinLimit($title, 120) || !textWithinLimit($description, 3000)
            || !in_array($group, GROUPS, true)
            || !validChoice($priority, PRIORITIES)
            || !validChoice($status, STATUSES)
            || !validDate($dueDate)) {
            redirectWithFlash('error', 'Bitte prüfe Betreff, Gruppe, Priorität, Status und Fälligkeit.');
        }

        $now = date(DATE_ATOM);
        modifyTickets(static function (array &$tickets) use ($title, $description, $group, $priority, $status, $dueDate, $now): void {
            array_unshift($tickets, [
                'id' => bin2hex(random_bytes(5)),
                'title' => $title,
                'description' => $description,
                'group' => $group,
                'priority' => $priority,
                'status' => $status,
                'due_date' => $dueDate,
                'notes' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        redirectWithFlash('success', 'Das Ticket wurde erstellt.');
    }

    if ($action === 'update' || $action === 'add_note') {
        $ticketId = (string) ($_POST['ticket_id'] ?? '');

        if ($ticketId === '' || !preg_match('/^[a-f0-9]{10}$/', $ticketId)) {
            redirectWithFlash('error', 'Das ausgewählte Ticket ist ungültig.');
        }

        if ($action === 'update') {
            $status = (string) ($_POST['status'] ?? '');
            $priority = (string) ($_POST['priority'] ?? '');
            $group = (string) ($_POST['group'] ?? '');
            $dueDate = (string) ($_POST['due_date'] ?? '');

            if (!validChoice($status, STATUSES)
                || !validChoice($priority, PRIORITIES)
                || !in_array($group, GROUPS, true)
                || !validDate($dueDate)) {
                redirectWithFlash('error', 'Bitte prüfe die Ticketangaben und versuche es erneut.');
            }

            $updated = modifyTickets(static function (array &$tickets) use ($ticketId, $status, $priority, $group, $dueDate): bool {
                foreach ($tickets as $index => $ticket) {
                    if ($ticket['id'] === $ticketId) {
                        $tickets[$index]['status'] = $status;
                        $tickets[$index]['priority'] = $priority;
                        $tickets[$index]['group'] = $group;
                        $tickets[$index]['due_date'] = $dueDate;
                        $tickets[$index]['updated_at'] = date(DATE_ATOM);
                        return true;
                    }
                }

                return false;
            });

            redirectWithFlash($updated ? 'success' : 'error', $updated ? 'Das Ticket wurde aktualisiert.' : 'Das Ticket wurde nicht gefunden.');
        }

        $note = trim((string) ($_POST['note'] ?? ''));

        if ($note === '' || !textWithinLimit($note, 2000)) {
            redirectWithFlash('error', 'Die Notiz darf nicht leer sein und höchstens 2.000 Zeichen enthalten.');
        }

        $added = modifyTickets(static function (array &$tickets) use ($ticketId, $note): bool {
            foreach ($tickets as $index => $ticket) {
                if ($ticket['id'] === $ticketId) {
                    $tickets[$index]['notes'][] = [
                        'text' => $note,
                        'created_at' => date(DATE_ATOM),
                    ];
                    $tickets[$index]['updated_at'] = date(DATE_ATOM);
                    return true;
                }
            }

            return false;
        });

        redirectWithFlash($added ? 'success' : 'error', $added ? 'Die Notiz wurde gespeichert.' : 'Das Ticket wurde nicht gefunden.');
    }

    redirectWithFlash('error', 'Die angeforderte Aktion ist unbekannt.');
}
