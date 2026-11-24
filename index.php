<?php
declare(strict_types=1);

require __DIR__ . '/includes/app.php';

$tickets = readTickets();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$groupFilter = (string) ($_GET['group'] ?? '');

$filteredTickets = array_filter(
    $tickets,
    static function (array $ticket) use ($search, $statusFilter, $groupFilter): bool {
        $matchesSearch = $search === ''
            || stripos($ticket['title'], $search) !== false
            || stripos($ticket['description'], $search) !== false
            || stripos($ticket['id'], $search) !== false;
        $matchesStatus = $statusFilter === '' || $ticket['status'] === $statusFilter;
        $matchesGroup = $groupFilter === '' || $ticket['group'] === $groupFilter;

        return $matchesSearch && $matchesStatus && $matchesGroup;
    }
);

usort(
    $filteredTickets,
    static fn (array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at'])
);

$openCount = count(array_filter(
    $tickets,
    static fn (array $ticket): bool => $ticket['status'] !== 'resolved'
));
$urgentCount = count(array_filter(
    $tickets,
    static fn (array $ticket): bool => $ticket['priority'] === 'urgent'
        && $ticket['status'] !== 'resolved'
));
$overdueCount = count(array_filter(
    $tickets,
    static fn (array $ticket): bool => $ticket['due_date'] !== ''
        && $ticket['due_date'] < date('Y-m-d')
        && $ticket['status'] !== 'resolved'
));

function formatDate(string $date): string
{
    if ($date === '') {
        return 'Kein Termin';
    }

    return (new DateTimeImmutable($date))->format('d.m.Y');
}

function formatDateTime(string $dateTime): string
{
    return (new DateTimeImmutable($dateTime))->format('d.m.Y · H:i');
}

function statusClass(string $status): string
{
    return match ($status) {
        'open' => 'status-open',
        'in_progress' => 'status-progress',
        'waiting' => 'status-waiting',
        'resolved' => 'status-resolved',
        default => 'status-open',
    };
}

function priorityClass(string $priority): string
{
    return match ($priority) {
        'urgent' => 'priority-urgent',
        'high' => 'priority-high',
        'low' => 'priority-low',
        default => 'priority-normal',
    };
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Ticketsystem · Übersicht</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="index.php" aria-label="Ticketdesk Startseite">
            <span class="brand-mark">T</span>
            <span>ticket<span class="brand-light">desk</span></span>
        </a>
        <div class="workspace-label">ARBEITSBEREICH</div>
        <nav class="side-nav" aria-label="Hauptnavigation">
            <a class="nav-link active" href="index.php">
                <span class="nav-icon">▦</span> Alle Tickets
                <span class="nav-count"><?= count($tickets) ?></span>
            </a>
            <a class="nav-link" href="index.php?status=open">
                <span class="nav-icon">◷</span> Offen
            </a>
            <a class="nav-link" href="index.php?status=in_progress">
                <span class="nav-icon">↻</span> In Bearbeitung
            </a>
            <a class="nav-link" href="index.php?status=resolved">
                <span class="nav-icon">✓</span> Erledigt
            </a>
        </nav>
        <div class="sidebar-divider"></div>
        <div class="workspace-label">GRUPPEN</div>
        <nav class="side-nav group-nav" aria-label="Ticketgruppen">
            <?php foreach (GROUPS as $group): ?>
                <a class="nav-link" href="index.php?group=<?= rawurlencode($group) ?>">
                    <span class="group-dot group-dot-<?= e(groupSlug($group)) ?>"></span>
                    <?= e($group) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <!--<div class="sidebar-footer">
            <span class="avatar avatar-sidebar">TD</span>
            <span class="sidebar-user"><strong>Ticketdesk</strong><small>Support-Team</small></span>
            <span class="sidebar-more">···</span>
        </div>-->
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb"><span>Arbeitsbereich</span><span class="crumb-separator">/</span><strong>Tickets</strong></div>
            <div class="topbar-actions">
                <span class="today-label"><?= e((new DateTimeImmutable())->format('d.m.Y')) ?></span>
                <span class="avatar avatar-top" title="Ticketdesk">TD</span>
            </div>
        </header>

        <div class="page-content">
            <?php if (is_array($flash)): ?>
                <div class="flash-message flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <section class="page-heading">
                <div>
                    <div class="eyebrow">SUPPORT-CENTER</div>
                    <h1>Tickets</h1>
                    <p class="page-subtitle">Behalte Anfragen, Aufgaben und Fristen im Blick.</p>
                </div>
                <button class="button button-primary" type="button" data-open-dialog="new-ticket-dialog">
                    <span class="button-plus">+</span> Neues Ticket
                </button>
            </section>

            <section class="stats-grid" aria-label="Ticketübersicht">
                <article class="stat-card">
                    <div class="stat-top"><span>Offene Tickets</span><span class="stat-icon icon-blue">▦</span></div>
                    <div class="stat-value"><?= $openCount ?></div>
                    <div class="stat-caption">Noch nicht abgeschlossen</div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Hohe Priorität</span><span class="stat-icon icon-orange">↗</span></div>
                    <div class="stat-value"><?= $urgentCount ?></div>
                    <div class="stat-caption">Dringend zu bearbeiten</div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Überfällig</span><span class="stat-icon icon-red">◷</span></div>
                    <div class="stat-value"><?= $overdueCount ?></div>
                    <div class="stat-caption">Fälligkeit bereits überschritten</div>
                </article>
                <article class="stat-card">
                    <div class="stat-top"><span>Gesamt</span><span class="stat-icon icon-purple">▤</span></div>
                    <div class="stat-value"><?= count($tickets) ?></div>
                    <div class="stat-caption">Tickets im System</div>
                </article>
            </section>

            <section class="ticket-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Alle Tickets <span class="result-count"><?= count($filteredTickets) ?></span></h2>
                        <p>Verwalte und priorisiere deine Support-Anfragen.</p>
                    </div>
                    <form class="filter-form" method="get" action="index.php">
                        <label class="search-field">
                            <span class="search-icon">⌕</span>
                            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Tickets suchen…" aria-label="Tickets suchen">
                        </label>
                        <select name="status" aria-label="Nach Status filtern">
                            <option value="">Alle Status</option>
                            <?php foreach (STATUSES as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="group" aria-label="Nach Gruppe filtern">
                            <option value="">Alle Gruppen</option>
                            <?php foreach (GROUPS as $group): ?>
                                <option value="<?= e($group) ?>" <?= $groupFilter === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button button-filter" type="submit">Filtern</button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Status</th>
                            <th>Priorität</th>
                            <th>Gruppe</th>
                            <th>Fälligkeit</th>
                            <th>Notizen</th>
                            <th><span class="sr-only">Details</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($filteredTickets === []): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <span class="empty-icon">▤</span>
                                        <strong><?= $tickets === [] ? 'Noch keine Tickets' : 'Keine Tickets gefunden' ?></strong>
                                        <span><?= $tickets === [] ? 'Erstelle dein erstes Ticket, um Anfragen zentral zu verwalten.' : 'Passe deine Suche oder Filter an.' ?></span>
                                        <?php if ($tickets === []): ?>
                                            <button class="button button-primary button-small" type="button" data-open-dialog="new-ticket-dialog">Erstes Ticket erstellen</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filteredTickets as $ticket): ?>
                                <?php
                                $isOverdue = $ticket['due_date'] !== ''
                                    && $ticket['due_date'] < date('Y-m-d')
                                    && $ticket['status'] !== 'resolved';
                                ?>
                                <tr>
                                    <td>
                                        <button class="ticket-title-button" type="button" data-open-dialog="ticket-<?= e($ticket['id']) ?>">
                                            <span class="ticket-id">#<?= e(strtoupper($ticket['id'])) ?></span>
                                            <strong><?= e($ticket['title']) ?></strong>
                                        </button>
                                        <div class="ticket-description"><?= e($ticket['description'] ?: 'Keine Beschreibung') ?></div>
                                    </td>
                                    <td><span class="status-badge <?= e(statusClass($ticket['status'])) ?>"><?= e(STATUSES[$ticket['status']]) ?></span></td>
                                    <td><span class="priority-label <?= e(priorityClass($ticket['priority'])) ?>"><span></span><?= e(PRIORITIES[$ticket['priority']]) ?></span></td>
                                    <td><span class="group-label"><span class="group-dot group-dot-<?= e(groupSlug($ticket['group'])) ?>"></span><?= e($ticket['group']) ?></span></td>
                                    <td><span class="due-date <?= $isOverdue ? 'due-overdue' : '' ?>"><?= e(formatDate($ticket['due_date'])) ?></span></td>
                                    <td><span class="notes-count">▤ <?= count($ticket['notes']) ?></span></td>
                                    <td><button class="row-action" type="button" data-open-dialog="ticket-<?= e($ticket['id']) ?>" aria-label="Ticketdetails öffnen">›</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span><?= count($filteredTickets) ?> von <?= count($tickets) ?> Tickets</span>
                    <?php if ($search !== '' || $statusFilter !== '' || $groupFilter !== ''): ?>
                        <a href="index.php" class="clear-filters">Filter zurücksetzen</a>
                    <?php else: ?>
                    <?php endif; ?>
                </div>
            </section>
            <footer class="page-footer">Ticketdesk</footer>
        </div>
    </main>
</div>

<dialog class="ticket-dialog" id="new-ticket-dialog" aria-labelledby="new-ticket-title">
    <div class="dialog-heading">
        <div><div class="eyebrow">SUPPORT-CENTER</div><h2 id="new-ticket-title">Neues Ticket erstellen</h2></div>
        <button class="dialog-close" type="button" data-close-dialog aria-label="Dialog schließen">×</button>
    </div>
    <form method="post" action="index.php" class="ticket-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <label class="form-field form-field-wide">Betreff <span class="required">*</span>
            <input name="title" type="text" maxlength="120" required placeholder="Worum geht es?">
        </label>
        <label class="form-field form-field-wide">Beschreibung
            <textarea name="description" rows="4" maxlength="3000" placeholder="Beschreibe die Anfrage oder Aufgabe …"></textarea>
        </label>
        <label class="form-field">Gruppe
            <select name="group" required>
                <?php foreach (GROUPS as $group): ?><option value="<?= e($group) ?>"><?= e($group) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Priorität
            <select name="priority" required>
                <?php foreach (PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === 'normal' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Status
            <select name="status" required>
                <?php foreach (STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $value === 'open' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Fälligkeit
            <input name="due_date" type="date">
        </label>
        <div class="dialog-actions">
            <button class="button button-quiet" type="button" data-close-dialog>Abbrechen</button>
            <button class="button button-primary" type="submit">Ticket erstellen</button>
        </div>
    </form>
</dialog>

<?php foreach ($tickets as $ticket): ?>
    <dialog class="ticket-dialog detail-dialog" id="ticket-<?= e($ticket['id']) ?>" aria-labelledby="title-<?= e($ticket['id']) ?>">
        <div class="dialog-heading">
            <div>
                <div class="eyebrow">TICKET #<?= e(strtoupper($ticket['id'])) ?></div>
                <h2 id="title-<?= e($ticket['id']) ?>"><?= e($ticket['title']) ?></h2>
            </div>
            <button class="dialog-close" type="button" data-close-dialog aria-label="Dialog schließen">×</button>
        </div>
        <?php if ($ticket['description'] !== ''): ?>
            <p class="detail-description"><?= nl2br(e($ticket['description'])) ?></p>
        <?php endif; ?>
        <form method="post" action="index.php" class="ticket-form detail-edit-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">
            <div class="detail-meta">
                <label class="form-field">Status
                    <select name="status">
                        <?php foreach (STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= $ticket['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Priorität
                    <select name="priority">
                        <?php foreach (PRIORITIES as $value => $label): ?><option value="<?= e($value) ?>" <?= $ticket['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Gruppe
                    <select name="group">
                        <?php foreach (GROUPS as $group): ?><option value="<?= e($group) ?>" <?= $ticket['group'] === $group ? 'selected' : '' ?>><?= e($group) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Fälligkeit
                    <input name="due_date" type="date" value="<?= e($ticket['due_date']) ?>">
                </label>
            </div>
            <div class="dialog-actions detail-save-actions">
                <button class="button button-primary" type="submit">Änderungen speichern</button>
            </div>
        </form>
        <section class="notes-section">
            <div class="notes-heading"><h3>Notizen</h3><span><?= count($ticket['notes']) ?></span></div>
            <?php if ($ticket['notes'] === []): ?>
                <p class="notes-empty">Noch keine Notizen zu diesem Ticket.</p>
            <?php else: ?>
                <div class="notes-list">
                    <?php foreach ($ticket['notes'] as $note): ?>
                        <article class="note-item">
                            <!--<span class="avatar note-avatar">TD</span>-->
                            <div class="note-body">
                                <div class="note-meta"><!--<strong>Support-Team</strong>--><time><?= e(formatDateTime($note['created_at'])) ?></time></div>
                                <p><?= nl2br(e($note['text'])) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="index.php" class="note-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="ticket_id" value="<?= e($ticket['id']) ?>">
                <label class="sr-only" for="note-<?= e($ticket['id']) ?>">Neue Notiz</label>
                <textarea id="note-<?= e($ticket['id']) ?>" name="note" rows="2" maxlength="2000" required placeholder="Notiz hinzufügen …"></textarea>
                <button class="button button-primary button-small" type="submit">Notiz speichern</button>
            </form>
        </section>
    </dialog>
<?php endforeach; ?>
<script src="assets/js/app.js" defer></script>
</body>
</html>
