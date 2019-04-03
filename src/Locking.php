<?php

namespace Sofa\ModelLocking;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * This trait provides pseudo pessimistic locking feature to Eloquent models.
 *
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
trait Locking
{
    /**
     * Get representation of the model that will be sent onto the queue (and to broadcasting later).
     *
     * @return mixed
     */
    public function broadcastAs()
    {
        return $this->toArray();
    }

    /**
     * Determine whether model is not locked at all or provided token unlocks it.
     *
     * @param  string  $token
     * @return boolean
     */
    public function isAccessible($token = null)
    {
        if ($this->isLocked()) {
            return $this->modelLock->verify($token);
        }

        return true;
    }

    /**
     * Determine whether there is an active lock on the model.
     *
     * @return boolean
     */
    public function isLocked()
    {
        return $this->modelLock
            && $this->modelLock->locked_until->isFuture();
    }

    /**
     * Get timestamp when model gets unlocked.
     *
     * @return \Carbon\Carbon|null
     */
    public function lockedUntil()
    {
        if ($this->isLocked()) {
            return $this->modelLock->locked_until;
        }
    }

    /**
     * Get user who locked the model.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function lockedBy()
    {
        if ($this->isLocked()) {
            return $this->modelLock->user;
        }
    }

    /**
     * Lock the model and get lock token for further reference.
     *
     * Duration precedence:
     *   1. provided param
     *   2. lockable model property
     *   3. value from the config
     *   4. default 5 minutes
     *
     * @param  \DateTime|string $duration
     *                         DateTime|Carbon object or parsable date string @see strtotime()
     * @param  \Illuminate\Contracts\Auth\Authenticatable|integer|string $user
     *                         Identifier of the user who is locking the model
     * @return string
     */
    public function lock($duration = null, $user = null)
    {
        if ($duration instanceof Authenticatable) {
            list($user, $duration) = [$duration, null];
        }

        if (!$duration && property_exists($this, 'lock_duration')) {
            $duration = $this->lock_duration;
        }

        $lock = $this->modelLock()->firstOrNew([])->lock($duration, $user);

        $this->setRelation('modelLock', $lock);

        if ($events = $this->getEventDispatcher()) {
            $events->dispatch(new ModelLocked($this));
        }

        return $lock->getToken();
    }

    /**
     * Release the lock.
     *
     * @return $this
     */
    public function unlock()
    {
        $this->modelLock()->delete();

        unset($this->relations['modelLock']);

        if ($events = $this->getEventDispatcher()) {
            $events->dispatch(new ModelUnlocked($this));
        }

        return $this;
    }

    /**
     * Request unlocking of this model.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string $message
     * @return \Carbon\Carbon|null
     */
    public function requestUnlock($user = null, $message = '')
    {
        if ($this->isLocked()) {
            $this->modelLock->requestUnlock($user, $message);

            return $this->lockedUntil();
        }
    }

    /**
     * Relation with the model lock.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function modelLock()
    {
        return $this->morphOne(ModelLock::class, 'model')->active();
    }
}
