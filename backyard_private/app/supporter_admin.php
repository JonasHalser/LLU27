<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

/** Delete the account and its private activity while preserving team history. */
function supporterAdminDelete(int $supporterId): void {
    if($supporterId<1) throw new RuntimeException('Ungültiges Supporter-Konto.');
    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT id FROM supporters WHERE id=? FOR UPDATE');
        $q->execute([$supporterId]);
        if(!$q->fetchColumn()) throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $q=$p->prepare('SELECT COUNT(*) FROM supporter_coin_bets b JOIN supporter_coin_markets m ON m.id=b.market_id WHERE b.supporter_id=? AND m.settled_at IS NULL');
        $q->execute([$supporterId]);
        if((int)$q->fetchColumn()>0) throw new RuntimeException('Dieses Konto hat noch offene Coin-Einsätze und kann deshalb nicht gelöscht werden.');

        // Match self-service deletion: remove messages; FK cascades clear tokens,
        // tips and push subscriptions. Missions and joker history are anonymized.
        $p->prepare('DELETE FROM supporter_cheers WHERE supporter_id=?')->execute([$supporterId]);
        $p->prepare('DELETE FROM supporters WHERE id=?')->execute([$supporterId]);
        $p->commit();
    } catch(Throwable $e) {
        if($p->inTransaction()) $p->rollBack();
        throw $e;
    }
}
