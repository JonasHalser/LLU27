<?php
declare(strict_types=1);

// Motivationssprüche für den Typewriter auf dem Dashboard. Jeder angemeldete Nutzer darf sie pflegen.
const MOTIVATION_MAX_LENGTH = 120;

function motivationTableMissing(Throwable $e): bool
{
    return $e instanceof PDOException && $e->getCode() === '42S02';
}

function motivationQuotes(): array
{
    return db()->query(
        'SELECT q.id, q.quote, q.created_at, q.updated_at,
                c.display_name AS created_by_name, e.display_name AS updated_by_name
         FROM motivation_quotes q
         LEFT JOIN users c ON c.id = q.created_by
         LEFT JOIN users e ON e.id = q.updated_by
         ORDER BY q.id ASC'
    )->fetchAll();
}

function motivationQuoteTexts(): array
{
    return db()->query('SELECT quote FROM motivation_quotes ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
}

function motivationCleanQuote(string $raw): string
{
    if (!mb_check_encoding($raw, 'UTF-8')) {
        throw new RuntimeException('Ungültige Zeichen im Spruch.');
    }
    $quote = trim((string)preg_replace('/\s+/u', ' ', $raw));
    if ($quote === '') {
        throw new RuntimeException('Bitte einen Spruch eingeben.');
    }
    if (mb_strlen($quote) > MOTIVATION_MAX_LENGTH) {
        throw new RuntimeException('Ein Spruch darf höchstens ' . MOTIVATION_MAX_LENGTH . ' Zeichen lang sein.');
    }
    return $quote;
}

function motivationAdd(string $raw, int $userId): void
{
    $stmt = db()->prepare('INSERT INTO motivation_quotes (quote, created_by, updated_by) VALUES (?, ?, ?)');
    $stmt->execute([motivationCleanQuote($raw), $userId, $userId]);
}

function motivationUpdate(int $id, string $raw, int $userId): void
{
    $quote = motivationCleanQuote($raw);
    $check = db()->prepare('SELECT 1 FROM motivation_quotes WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetchColumn()) {
        throw new RuntimeException('Der Spruch existiert nicht mehr.');
    }
    $stmt = db()->prepare('UPDATE motivation_quotes SET quote = ?, updated_by = ? WHERE id = ?');
    $stmt->execute([$quote, $userId, $id]);
}

function motivationDelete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM motivation_quotes WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Der Spruch existiert nicht mehr.');
    }
}
