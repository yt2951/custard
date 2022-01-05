<?php

declare(strict_types=1);

namespace Core\Database\Repositories;

use Cake\Database\Expression\QueryExpression;
use Core\Database\Configuration;
use Core\Database\Connections\ConnectionInterface;
use Memcached;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QueryBuilderRepository implements RepositoryInterface
{
    protected string $table = 'app';

    protected bool $softDeletes = false;

    protected string $notFoundMessage = 'Resources with id %d not found';

    protected ConnectionInterface $connection;

    protected Memcached $memcached;

    public function __construct()
    {
        $this->connection = Configuration::connect();
        $this->memcached = new Memcached();
    }

    public function all(): array
    {
        $query = $this->connection->getQuery();

        return $query
            ->select('*')
            ->from($this->table)
            // ->where(fn (QueryExpression $expression) => (
            //     $this->softDeletes ? $expression->isNull('deleted_at') : []
            // ))
            ->execute()
            ->fetchAll(\PDO::FETCH_OBJ) ?: [];
    }

    public function count(mixed $value, string $column = 'id'): int
    {
        $query = $this->connection->getQuery();

        return (int) $query
            ->select(['count' => $query->func()->count('*')])
            ->from($this->table)
            // ->where(fn (QueryExpression $expression) => (
            //     $this->softDeletes ? $expression->isNull('deleted_at') : []
            // ))
            ->andWhere([$column => $value])
            ->execute()
            ->fetch(\PDO::FETCH_OBJ)
            ?->count;
    }

    public function random(): ?object
    {
        $query = $this->connection->getQuery();

        return $query
            ->select('*')
            ->from($this->table)
            // ->where(fn (QueryExpression $expression) => (
            //     $this->softDeletes ? $expression->isNull('deleted_at') : []
            // ))
            ->order($query->func()->rand())
            ->execute()
            ->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function find(mixed $value, string $column = 'id'): object|array
    {
        $query = $this->connection->getQuery();

        $data = $query
            ->select('*')
            ->from($this->table)
            // ->where(fn (QueryExpression $expression) => (
            //     $this->softDeletes ? $expression->isNull('deleted_at') : []
            // ))
            ->andWhere([$column => $value])
            ->execute()
            ->fetch(\PDO::FETCH_OBJ);

        if (is_object($data) === false && $column === 'id') {
            throw new NotFoundHttpException(sprintf($this->notFoundMessage, $value));
        }

        return $data ?: [];
    }

    public function create(array $values): int
    {
        return (int) $this->connection->getConnection()
            ->insert($this->table, $values)
            ->lastInsertId();
    }

    public function update(int $id, array $values): object
    {
        if ($this->count($id) === 0) {
            throw new NotFoundHttpException(sprintf($this->notFoundMessage, $id));
        }

        $this->connection->getConnection()->update($this->table, $values, ['id' => $id]);

        return $this->find($id);
    }

    public function delete(mixed $value, string $column = 'id'): void
    {
        if ($this->count($value) === 0) {
            throw new NotFoundHttpException(sprintf($this->notFoundMessage, $value));
        }

        switch ($this->softDeletes) {
            case true:
                $this->connection->getConnection()->update($this->table, [
                    'deleted_at' => $this->connection->getQuery()->func()->now(),
                ], [$column => $value]);
                break;
            default:
                $this->connection->getConnection()->delete($this->table, [$column => $value]);
                break;
        }
    }

    public function lastModifiedTime(): \DateTime
    {
        return $this->connection->getLastModifiedTime($this->table);
    }
}
