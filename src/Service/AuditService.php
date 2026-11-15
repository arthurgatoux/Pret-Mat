<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Env;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use Throwable;

class AuditService
{
    private ?Client $client = null;
    private string $dbName;

    public function __construct(?string $uri = null, ?string $dbName = null)
    {
        $mongoUri = $uri ?? (string) Env::get('MONGODB_URI', '');
        $this->dbName = $dbName ?? (string) Env::get('MONGODB_DB', 'pretmat');

        if ($mongoUri !== '') {
            try {
                // Timeout court (1500 ms) pour ne jamais bloquer les requêtes utilisateur en cas d'indisponibilité
                $this->client = new Client($mongoUri, [], [
                    'serverSelectionTimeoutMS' => 1500,
                    'connectTimeoutMS'         => 1500,
                ]);
            } catch (Throwable $e) {
                $this->client = null;
                error_log(sprintf('[PretMat Audit] Échec d\'initialisation MongoDB : %s', $e->getMessage()));
            }
        }
    }

    /**
     * Journalise une action métier dans la collection 'logs' de MongoDB Atlas.
     * Aucune donnée sensible n'est enregistrée (pas d'e-mail, pas de token).
     * Si MongoDB est injoignable ou en timeout, l'erreur est écrite dans error_log et l'action métier continue normalement.
     *
     * @param string $action 'connexion', 'emprunt' ou 'retour'
     * @param int|null $userId Identifiant numérique de l'utilisateur
     * @param int|null $itemId Identifiant numérique du matériel (si applicable)
     */
    public function log(string $action, ?int $userId, ?int $itemId = null): void
    {
        if ($this->client === null) {
            return;
        }

        try {
            $collection = $this->client->selectDatabase($this->dbName)->selectCollection('logs');

            $document = [
                'date'    => new UTCDateTime(),
                'action'  => $action,
                'user_id' => $userId,
                'item_id' => $itemId,
            ];

            $collection->insertOne($document);
        } catch (Throwable $e) {
            // Dégradation gracieuse : l'action métier réussit et l'erreur est consignée dans error_log
            error_log(sprintf('[PretMat Audit Error] Impossible d\'enregistrer le log MongoDB (%s) : %s', $action, $e->getMessage()));
        }
    }

    public function logLogin(int $userId): void
    {
        $this->log('connexion', $userId, null);
    }

    public function logBorrow(int $userId, int $itemId): void
    {
        $this->log('emprunt', $userId, $itemId);
    }

    public function logReturn(int $userId, int $itemId): void
    {
        $this->log('retour', $userId, $itemId);
    }

    /**
     * Récupère les derniers logs pour vérification ou affichage technique.
     *
     * @return array<array<string, mixed>>
     */
    public function getLatestLogs(int $limit = 10): array
    {
        if ($this->client === null) {
            return [];
        }

        try {
            $collection = $this->client->selectDatabase($this->dbName)->selectCollection('logs');
            $cursor = $collection->find([], [
                'sort'  => ['date' => -1],
                'limit' => $limit,
            ]);

            return iterator_to_array($cursor);
        } catch (Throwable $e) {
            error_log(sprintf('[PretMat Audit Error] Lecture logs MongoDB impossible : %s', $e->getMessage()));

            return [];
        }
    }
}
