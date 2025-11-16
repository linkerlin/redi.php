<?php

namespace Rediphp;

/**
 * Redis Pipeline Interface
 * Provides batch operation support for Redis commands
 */
interface RPipeline
{
    /**
     * Execute all queued commands and return results
     *
     * @return array Results from all queued commands
     */
    public function execute(): array;

    /**
     * Queue a Redis command for batch execution
     *
     * @param string $method Method name
     * @param array $args Arguments
     * @return self
     */
    public function queueCommand(string $method, array $args): self;

    /**
     * Get the number of queued commands
     *
     * @return int
     */
    public function getQueuedCount(): int;

    /**
     * Clear all queued commands
     *
     * @return self
     */
    public function clear(): self;

    /**
     * Check if pipeline is empty
     *
     * @return bool
     */
    public function isEmpty(): bool;
}