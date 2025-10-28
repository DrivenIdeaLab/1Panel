<?php

namespace App\Services\Swarm;

use App\Services\Swarm\Enums\SwarmTopology;
use App\Services\Swarm\Enums\ExecutionStrategy;
use App\Services\Swarm\Exceptions\SwarmConfigException;

/**
 * Claude Flow Swarm Configuration
 * 
 * Handles the initialization and configuration of agent swarms
 * with hierarchical topology, parallel execution, and auto-spawn capabilities.
 */
class SwarmConfig
{
    /**
     * @var SwarmTopology
     */
    private SwarmTopology $topology;

    /**
     * @var ExecutionStrategy
     */
    private ExecutionStrategy $executionStrategy;

    /**
     * @var int
     */
    private int $maxAgents;

    /**
     * @var bool
     */
    private bool $autoSpawn;

    /**
     * @var bool
     */
    private bool $memoryPersistence;

    /**
     * @var array
     */
    private array $memoryNamespaces;

    /**
     * @var array
     */
    private array $resourceLimits;

    /**
     * @var string
     */
    private string $swarmId;

    /**
     * @var array
     */
    private array $communicationChannels;

    public function __construct(
        SwarmTopology $topology = SwarmTopology::HIERARCHICAL,
        ExecutionStrategy $executionStrategy = ExecutionStrategy::PARALLEL,
        int $maxAgents = 8,
        bool $autoSpawn = true,
        bool $memoryPersistence = true
    ) {
        $this->topology = $topology;
        $this->executionStrategy = $executionStrategy;
        $this->maxAgents = $this->validateMaxAgents($maxAgents);
        $this->autoSpawn = $autoSpawn;
        $this->memoryPersistence = $memoryPersistence;
        $this->swarmId = $this->generateSwarmId();
        $this->initializeDefaults();
    }

    /**
     * Validate maximum agent count
     */
    private function validateMaxAgents(int $maxAgents): int
    {
        if ($maxAgents < 1 || $maxAgents > 50) {
            throw new SwarmConfigException('Max agents must be between 1 and 50');
        }
        
        return $maxAgents;
    }

    /**
     * Generate unique swarm identifier
     */
    private function generateSwarmId(): string
    {
        return 'swarm_' . uniqid() . '_' . time();
    }

    /**
     * Initialize default configurations
     */
    private function initializeDefaults(): void
    {
        $this->memoryNamespaces = [
            'shared' => "swarm:{$this->swarmId}:shared",
            'tasks' => "swarm:{$this->swarmId}:tasks",
            'results' => "swarm:{$this->swarmId}:results",
            'coordination' => "swarm:{$this->swarmId}:coordination",
            'metrics' => "swarm:{$this->swarmId}:metrics"
        ];

        $this->resourceLimits = [
            'memory_per_agent' => '512MB',
            'cpu_per_agent' => 1,
            'max_execution_time' => 3600, // 1 hour
            'max_concurrent_tasks' => $this->maxAgents * 2
        ];

        $this->communicationChannels = [
            'broadcast' => "swarm:{$this->swarmId}:broadcast",
            'task_queue' => "swarm:{$this->swarmId}:tasks",
            'result_queue' => "swarm:{$this->swarmId}:results",
            'coordination' => "swarm:{$this->swarmId}:coord"
        ];
    }

    /**
     * Get swarm topology configuration
     */
    public function getTopologyConfig(): array
    {
        return match ($this->topology) {
            SwarmTopology::HIERARCHICAL => [
                'structure' => 'hierarchical',
                'coordinator_agents' => max(1, intval($this->maxAgents / 4)),
                'worker_agents' => $this->maxAgents - max(1, intval($this->maxAgents / 4)),
                'delegation_strategy' => 'top_down',
                'coordination_pattern' => 'tree'
            ],
            SwarmTopology::MESH => [
                'structure' => 'mesh',
                'peer_agents' => $this->maxAgents,
                'coordination_pattern' => 'peer_to_peer',
                'consensus_required' => false
            ],
            SwarmTopology::STAR => [
                'structure' => 'star',
                'central_coordinator' => 1,
                'worker_agents' => $this->maxAgents - 1,
                'coordination_pattern' => 'centralized'
            ],
            SwarmTopology::RING => [
                'structure' => 'ring',
                'sequential_agents' => $this->maxAgents,
                'coordination_pattern' => 'sequential',
                'ring_direction' => 'clockwise'
            ]
        };
    }

    /**
     * Get execution strategy configuration
     */
    public function getExecutionConfig(): array
    {
        return match ($this->executionStrategy) {
            ExecutionStrategy::PARALLEL => [
                'type' => 'parallel',
                'max_concurrent' => $this->maxAgents,
                'load_balancing' => 'round_robin',
                'failure_handling' => 'retry_other_agent'
            ],
            ExecutionStrategy::SEQUENTIAL => [
                'type' => 'sequential',
                'queue_strategy' => 'fifo',
                'failure_handling' => 'halt_and_retry'
            ],
            ExecutionStrategy::HYBRID => [
                'type' => 'hybrid',
                'parallel_threshold' => 3,
                'sequential_fallback' => true,
                'adaptive_switching' => true
            ]
        };
    }

    /**
     * Get memory persistence configuration
     */
    public function getMemoryConfig(): array
    {
        return [
            'enabled' => $this->memoryPersistence,
            'namespaces' => $this->memoryNamespaces,
            'persistence_strategy' => 'redis_with_disk_backup',
            'ttl' => 86400, // 24 hours
            'cleanup_strategy' => 'lru',
            'max_memory_per_namespace' => '1GB'
        ];
    }

    /**
     * Get auto-spawn configuration
     */
    public function getAutoSpawnConfig(): array
    {
        return [
            'enabled' => $this->autoSpawn,
            'spawn_triggers' => [
                'queue_length_threshold' => 10,
                'response_time_threshold' => 5000, // ms
                'cpu_threshold' => 80, // %
                'failure_rate_threshold' => 0.2
            ],
            'spawn_strategy' => 'gradual_increase',
            'max_spawn_rate' => 2, // agents per minute
            'cooldown_period' => 60 // seconds
        ];
    }

    /**
     * Get complete swarm configuration
     */
    public function toArray(): array
    {
        return [
            'swarm_id' => $this->swarmId,
            'topology' => $this->getTopologyConfig(),
            'execution' => $this->getExecutionConfig(),
            'memory' => $this->getMemoryConfig(),
            'auto_spawn' => $this->getAutoSpawnConfig(),
            'resource_limits' => $this->resourceLimits,
            'communication' => $this->communicationChannels,
            'max_agents' => $this->maxAgents,
            'created_at' => now()->toISOString()
        ];
    }

    // Getters
    public function getTopology(): SwarmTopology { return $this->topology; }
    public function getExecutionStrategy(): ExecutionStrategy { return $this->executionStrategy; }
    public function getMaxAgents(): int { return $this->maxAgents; }
    public function isAutoSpawnEnabled(): bool { return $this->autoSpawn; }
    public function isMemoryPersistenceEnabled(): bool { return $this->memoryPersistence; }
    public function getSwarmId(): string { return $this->swarmId; }
    public function getMemoryNamespaces(): array { return $this->memoryNamespaces; }
    public function getResourceLimits(): array { return $this->resourceLimits; }
    public function getCommunicationChannels(): array { return $this->communicationChannels; }

    // Setters with validation
    public function setMaxAgents(int $maxAgents): self
    {
        $this->maxAgents = $this->validateMaxAgents($maxAgents);
        return $this;
    }

    public function setTopology(SwarmTopology $topology): self
    {
        $this->topology = $topology;
        return $this;
    }

    public function setExecutionStrategy(ExecutionStrategy $strategy): self
    {
        $this->executionStrategy = $strategy;
        return $this;
    }

    public function enableAutoSpawn(bool $enable = true): self
    {
        $this->autoSpawn = $enable;
        return $this;
    }

    public function enableMemoryPersistence(bool $enable = true): self
    {
        $this->memoryPersistence = $enable;
        return $this;
    }
}