<?php
declare(strict_types=1);

require_once __DIR__ . '/performance.php';

/**
 * LLU 27 – Strava Performance Detail Sync
 *
 * - Importiert Aktivitäts-Streams
 * - Berechnet HF-Drift, HF-Zonen und KM-Splits
 * - Überspringt vorübergehend fehlerhafte Aktivitäten
 * - Vermeidet wiederholte 404-Anfragen
 * - Begrenzt zusätzliche Detail-API-Aufrufe
 *
 * Erwartete Tabellen:
 *   activities
 *   athletes
 *   activity_performance
 *   activity_performance_errors
 */

function ll27SyncPerformance(): array
{
    $messages=[];
    $batch=max(1,min(20,(int)cfg('PERFORMANCE_BATCH_SIZE','5')));
    for($i=0;$i<$batch;$i++){
        $attempted=false;
        $messages=array_merge($messages,ll27SyncPerformanceOne($attempted));
        if(!$attempted)break;
    }
    return $messages;
}

function ll27SyncPerformanceOne(bool &$attempted): array
{
    $attempted=false;
    $pdo = db();
    $messages = [];

    /*
     * 1. Erfolgreich importierte Analysen
     *    nach sieben Tagen aus dem Cache entfernen.
     */
    $pdo->exec(
        "DELETE FROM activity_performance
         WHERE fetched_at <
         UTC_TIMESTAMP() - INTERVAL 7 DAY"
    );

    /*
     * Fehlerdaten für Aktivitäten behalten,
     * damit fehlgeschlagene Läufe nicht ständig
     * erneut angefordert werden.
     *
     * Alte Fehlerdaten nach 180 Tagen bereinigen.
     */
    $pdo->exec(
        "DELETE FROM activity_performance_errors
         WHERE updated_at <
         UTC_TIMESTAMP() - INTERVAL 180 DAY"
    );

    /*
     * 2. Zusätzliches Abrufbudget.
     *
     * Die vorhandenen Limits bleiben bewusst
     * konservativ. Der reguläre Strava-Sync
     * verursacht ebenfalls API-Anfragen.
     */
    $maxPerDay = max(1,min(1000,(int)cfg('PERFORMANCE_MAX_PER_DAY','180')));
    $maxPerHour = max(1,min(200,(int)cfg('PERFORMANCE_MAX_PER_HOUR','30')));

    $today = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM activity_performance
         WHERE fetched_at >= UTC_DATE()"
    )->fetchColumn();

    $hour = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM activity_performance
         WHERE fetched_at >=
         UTC_TIMESTAMP() - INTERVAL 1 HOUR"
    )->fetchColumn();

    /*
     * Fehlgeschlagene Detailabrufe ebenfalls
     * berücksichtigen, soweit sie im Fehlerlog
     * erfasst sind.
     */
    $errorsToday = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM activity_performance_errors
         WHERE updated_at >= UTC_DATE()"
    )->fetchColumn();

    $errorsHour = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM activity_performance_errors
         WHERE updated_at >=
         UTC_TIMESTAMP() - INTERVAL 1 HOUR"
    )->fetchColumn();

    if (
        ($today + $errorsToday) >= $maxPerDay ||
        ($hour + $errorsHour) >= $maxPerHour
    ) {
        return [
            'Performance: Detailbudget vorerst ausgeschöpft.'
        ];
    }

    /*
     * 3. Nach einem 429 nicht sofort weitere
     *    Aktivitäten abfragen.
     */
    $recentLimitError = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM activity_performance_errors
         WHERE last_error LIKE '%HTTP 429%'
           AND updated_at >=
               UTC_TIMESTAMP() - INTERVAL 1 HOUR"
    )->fetchColumn();

    if ($recentLimitError > 0) {
        return [
            'Performance: Strava-Ratenlimit erkannt. '
            . 'Detailabrufe vorübergehend pausiert.'
        ];
    }

    /*
     * 4. Nächste passende Aktivität auswählen.
     *
     * Ein Aktivitätsabruf wird nur versucht,
     * wenn:
     *
     * - die Aktivität noch im lokalen Cache ist
     * - noch keine Performance-Analyse existiert
     * - keine aktive Fehlersperre besteht
     */
    $sql = "
        SELECT
            a.id,
            a.user_id,
            a.activity_date,
            ath.access_token,
            ath.refresh_token,
            ath.expires_at,
            ath.athlete_id,
            ath.last_sync,
            ath.last_full_sync,
            ath.sync_error

        FROM activities a

        INNER JOIN athletes ath
            ON ath.user_id = a.user_id

        LEFT JOIN activity_performance p
            ON p.activity_id = a.id

        LEFT JOIN activity_performance_errors e
            ON e.activity_id = a.id

        WHERE
            a.activity_date >=
                UTC_TIMESTAMP() - INTERVAL 180 DAY

            AND a.cache_updated_at >=
                UTC_TIMESTAMP() - INTERVAL 7 DAY

            AND (p.activity_id IS NULL
                OR COALESCE(JSON_EXTRACT(p.detail_json, '$.analysis_version'), 0) < 4)

            AND (
                e.activity_id IS NULL
                OR e.next_retry_at <= UTC_TIMESTAMP()
            )

        ORDER BY
            (p.activity_id IS NULL) DESC,
            a.activity_date DESC,
            a.id DESC

        LIMIT 1
    ";

    $candidate = $pdo
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        return [
            'Performance: Keine weiteren '
            . 'Aktivitäten zur Detailanalyse vorhanden.'
        ];
    }

    $activityId = (int) $candidate['id'];
    $userId = (int) $candidate['user_id'];
    $attempted=true;

    /*
     * 5. Aktivitäts-Streams abrufen.
     */
    try {
        $token = tokenRefresh($candidate);

        $url = 'https://www.strava.com/api/v3/activities/'
            . $activityId
            . '/streams';

        $streams = httpJson(
            'GET',
            $url,
            [
                'keys' => 'time,distance,heartrate,latlng,altitude,grade_smooth,watts,cadence,moving,velocity_smooth',
                'key_by_type' => 'true'
            ],
            $token
        );

        /*
         * 6. Trainingsmetriken berechnen.
         */
        $hrMax = max(
            120.0,
            min(
                220.0,
                (float) cfg('LLU27_HR_MAX', '190')
            )
        );

        $metrics = ll27Performance(
            $streams,
            $hrMax
        );

        $metrics['hr_max_reference'] = $hrMax;

        /*
         * 7. Erfolgreiche Detailanalyse speichern.
         */
        $insert = $pdo->prepare(
            "INSERT INTO activity_performance
                (
                    activity_id,
                    user_id,
                    detail_json,
                    fetched_at
                )
             VALUES
                (
                    ?,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                detail_json = VALUES(detail_json),
                fetched_at = UTC_TIMESTAMP()"
        );

        $insert->execute([
            $activityId,
            $userId,
            json_encode(
                $metrics,
                JSON_THROW_ON_ERROR
            )
        ]);

        /*
         * Früheren Fehlerstatus entfernen,
         * nachdem die Analyse gespeichert wurde.
         */
        $deleteError = $pdo->prepare(
            "DELETE FROM activity_performance_errors
             WHERE activity_id = ?"
        );

        $deleteError->execute([$activityId]);

        $messages[] =
            'Performance: Lauf '
            . $activityId
            . ' erfolgreich analysiert und gespeichert.';

    } catch (Throwable $e) {

        /*
         * 8. Fehler behandeln.
         *
         * Ein fehlerhafter Lauf darf nicht
         * alle anderen Aktivitäten blockieren.
         */
        $errorMessage = $e->getMessage();

        /*
         * HTTP-Status aus der bestehenden
         * API-Fehlermeldung erkennen.
         */
        $httpStatus = null;

        if (
            preg_match(
                '/\b(?:HTTP\s*)?(401|403|404|429|500|502|503|504)\b/i',
                $errorMessage,
                $matches
            )
        ) {
            $httpStatus = (int) $matches[1];
        }

        /*
         * Anzahl bisheriger Fehlversuche laden.
         */
        $attemptStmt = $pdo->prepare(
            "SELECT attempts
             FROM activity_performance_errors
             WHERE activity_id = ?"
        );

        $attemptStmt->execute([$activityId]);

        $previousAttempts =
            (int) $attemptStmt->fetchColumn();

        $attempts = $previousAttempts + 1;

        /*
         * Fehlerabhängige Wartezeiten.
         *
         * 404: längere Pause, möglicherweise
         *      dauerhaft nicht verfügbare Aktivität.
         *
         * 401/403: Zugang oder Berechtigung prüfen.
         *
         * 429: API-Limit erreicht.
         *
         * Andere Fehler: kürzere Wiederholung.
         */
        switch ($httpStatus) {

            case 404:
                $retrySeconds = min(
                    30 * 86400,
                    86400 * (2 ** min($attempts - 1, 5))
                );
                $errorType = '404 – Aktivität nicht verfügbar';
                break;

            case 401:
            case 403:
                $retrySeconds = 86400;
                $errorType = 'Strava-Berechtigung prüfen';
                break;

            case 429:
                $retrySeconds = 86400;
                $errorType = 'Strava-API-Limit erreicht';
                break;

            default:
                $retrySeconds = min(
                    86400,
                    3600 * (2 ** min($attempts - 1, 5))
                );
                $errorType = 'Detailabruf fehlgeschlagen';
                break;
        }

        $retryAt = gmdate(
            'Y-m-d H:i:s',
            time() + $retrySeconds
        );

        /*
         * Fehlgeschlagenen Lauf protokollieren.
         */
        $saveError = $pdo->prepare(
            "INSERT INTO activity_performance_errors
                (
                    activity_id,
                    user_id,
                    attempts,
                    last_error,
                    next_retry_at,
                    updated_at
                )
             VALUES
                (
                    ?,
                    ?,
                    1,
                    ?,
                    ?,
                    UTC_TIMESTAMP()
                )
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                attempts = attempts + 1,
                last_error = VALUES(last_error),
                next_retry_at = VALUES(next_retry_at),
                updated_at = UTC_TIMESTAMP()"
        );

        $saveError->execute([
            $activityId,
            $userId,
            substr($errorMessage, 0, 2000),
            $retryAt
        ]);

        /*
         * Nur eine Statusmeldung zurückgeben.
         * Keine Exception an den Hauptsync werfen.
         */
        $messages[] =
            'Performance: Lauf '
            . $activityId
            . ' übersprungen. '
            . $errorType
            . '. Nächster Versuch ab '
            . $retryAt
            . ' UTC.';

        error_log(
            'LLU27 Performance: Lauf '
            . $activityId
            . ' – '
            . $errorMessage
        );
    }

    return $messages;
}
