<?php

namespace Sofa\ModelLocking;

use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
abstract class LockEvent implements ShouldBroadcast
{
    use SerializesModels;

    public $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    abstract public function broadcastOn();
}
