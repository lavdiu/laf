<?php

declare(strict_types=1);

namespace Laf\Core\Database;

/**
 * Connection Statistics
 * 
 * Tracks database connection metrics
 */
readonly class ConnectionStats
{
    public function __construct(
        public int $queriesExecuted = 0,
        public float $totalQueryTime = 0.0,
        public int $transactionsStarted = 0,
        public int $transactionsCommitted = 0,
        public int $transactionsRolledBack = 0,
        public float $connectionTime = 0.0,
        public ?\DateTimeImmutable $connectedAt = null,
        public ?\DateTimeImmutable $lastQueryAt = null,
    ) {}

    /**
     * Get average query time in milliseconds
     *
     * @return float
     */
    public function getAverageQueryTime(): float
    {
        if ($this->queriesExecuted === 0) {
            return 0.0;
        }

        return ($this->totalQueryTime / $this->queriesExecuted) * 1000;
    }

    /**
     * Get queries per second
     *
     * @return float
     */
    public function getQueriesPerSecond(): float
    {
        if ($this->connectionTime === 0.0) {
            return 0.0;
        }

        return $this->queriesExecuted / $this->connectionTime;
    }

    /**
     * Get transaction success rate
     *
     * @return float
     */
    public function getTransactionSuccessRate(): float
    {
        $total = $this->transactionsCommitted + $this->transactionsRolledBack;
        
        if ($total === 0) {
            return 0.0;
        }

        return ($this->transactionsCommitted / $total) * 100;
    }

    /**
     * Create a new instance with incremented query count
     *
     * @param float $queryTime
     * @return self
     */
    public function withQuery(float $queryTime): self
    {
        return new self(
            queriesExecuted: $this->queriesExecuted + 1,
            totalQueryTime: $this->totalQueryTime + $queryTime,
            transactionsStarted: $this->transactionsStarted,
            transactionsCommitted: $this->transactionsCommitted,
            transactionsRolledBack: $this->transactionsRolledBack,
            connectionTime: $this->connectionTime,
            connectedAt: $this->connectedAt,
            lastQueryAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Create a new instance with incremented transaction started
     *
     * @return self
     */
    public function withTransactionStarted(): self
    {
        return new self(
            queriesExecuted: $this->queriesExecuted,
            totalQueryTime: $this->totalQueryTime,
            transactionsStarted: $this->transactionsStarted + 1,
            transactionsCommitted: $this->transactionsCommitted,
            transactionsRolledBack: $this->transactionsRolledBack,
            connectionTime: $this->connectionTime,
            connectedAt: $this->connectedAt,
            lastQueryAt: $this->lastQueryAt,
        );
    }

    /**
     * Create a new instance with incremented transaction committed
     *
     * @return self
     */
    public function withTransactionCommitted(): self
    {
        return new self(
            queriesExecuted: $this->queriesExecuted,
            totalQueryTime: $this->totalQueryTime,
            transactionsStarted: $this->transactionsStarted,
            transactionsCommitted: $this->transactionsCommitted + 1,
            transactionsRolledBack: $this->transactionsRolledBack,
            connectionTime: $this->connectionTime,
            connectedAt: $this->connectedAt,
            lastQueryAt: $this->lastQueryAt,
        );
    }

    /**
     * Create a new instance with incremented transaction rolled back
     *
     * @return self
     */
    public function withTransactionRolledBack(): self
    {
        return new self(
            queriesExecuted: $this->queriesExecuted,
            totalQueryTime: $this->totalQueryTime,
            transactionsStarted: $this->transactionsStarted,
            transactionsCommitted: $this->transactionsCommitted,
            transactionsRolledBack: $this->transactionsRolledBack + 1,
            connectionTime: $this->connectionTime,
            connectedAt: $this->connectedAt,
            lastQueryAt: $this->lastQueryAt,
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'queries_executed' => $this->queriesExecuted,
            'total_query_time' => $this->totalQueryTime,
            'average_query_time_ms' => $this->getAverageQueryTime(),
            'queries_per_second' => $this->getQueriesPerSecond(),
            'transactions_started' => $this->transactionsStarted,
            'transactions_committed' => $this->transactionsCommitted,
            'transactions_rolled_back' => $this->transactionsRolledBack,
            'transaction_success_rate' => $this->getTransactionSuccessRate(),
            'connection_time' => $this->connectionTime,
            'connected_at' => $this->connectedAt?->format('Y-m-d H:i:s'),
            'last_query_at' => $this->lastQueryAt?->format('Y-m-d H:i:s'),
        ];
    }
}
