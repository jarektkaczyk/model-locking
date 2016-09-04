<?php

namespace Sofa\ModelLocking;

/**
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
class ModelUnlockRequested extends LockEvent
{
    public $user;
    public $message;

    public function __construct($model, $user = null, $message = '')
    {
        parent::__construct($model);

        $this->user = $user;
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return (array) config('model_locking.channels.request', []);
    }
}
