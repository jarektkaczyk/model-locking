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

    /**
     * Get the data that should be sent with the broadcasted event.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'model' => $this->model->broadcastAs(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return config('model_locking.broadcast_as.'.static::class) ?: static::class;
    }

    /**
     * Determine if this event should broadcast.
     *
     * @return bool
     */
    public function broadcastWhen()
    {
        return config('model_locking.broadcast_enabled', true);
    }
}
