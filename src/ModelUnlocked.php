<?php

namespace Sofa\ModelLocking;

/**
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
class ModelUnlocked extends LockEvent
{
    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return (array) config('model_locking.channels.unlocked', []);
    }
}
