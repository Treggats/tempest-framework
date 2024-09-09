<?php

declare(strict_types=1);

namespace Tempest\Database;

use BackedEnum;
use DateTimeInterface;
use PDO;
use PDOException;
use Tempest\Database\Exceptions\QueryException;
use Tempest\Database\Transactions\TransactionManager;
use Throwable;

final class GenericDatabase implements Database
{
    public function __construct(
        private PDO $pdo,
        private readonly TransactionManager $transactionManager,
    ) {
    }

    public function execute(Query $query): void
    {
        $bindings = $this->resolveBindings($query);

        try {
            $this->pdo
                ->prepare($query->getSql())
                ->execute($bindings);
        } catch (PDOException $pdoException) {
            throw new QueryException($query, $bindings, $pdoException);
        }
    }

    public function getLastInsertId(): Id
    {
        return new Id($this->pdo->lastInsertId());
    }

    public function fetch(Query $query): array
    {
        $pdoQuery = $this->pdo->prepare($query->getSql());

        $pdoQuery->execute($this->resolveBindings($query));

        return $pdoQuery->fetchAll(PDO::FETCH_NAMED);
    }

    public function fetchFirst(Query $query): ?array
    {
        return $this->fetch($query)[0] ?? null;
    }

    public function withinTransaction(callable $callback): bool
    {
        $this->transactionManager->begin();

        try {
            $callback();

            $this->transactionManager->commit();
        } catch (PDOException) {
            return false;
        } catch (Throwable) {
            $this->transactionManager->rollback();

            return false;
        }

        return true;
    }

    private function resolveBindings(Query $query): array
    {
        $bindings = [];

        foreach ($query->bindings as $key => $value) {
            if ($value instanceof Id) {
                $value = $value->id;
            }

            if ($value instanceof Query) {
                $value = $value->execute();
            }

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $bindings[$key] = $value;
        }

        return $bindings;
    }
}
