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
     * Determine weather model is locked to the current user
     *
     * @return boolean
     */
    public function isLockedToCurrentUser()
    {
        if ($this->isLocked()) {
            return $this->modelLock->verifyCurrentUser();
        }

        return false;
    }

    /**
     * Determine weather model is locked to the provided user
     *
     * @param  App\User $user
     * @return boolean
     */
    public function isLockedToUser($user)
    {
        if ($this->isLocked()) {
            return $this->modelLock->verifyUser($user);
        }

        return false;
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
     * Get the timestamp when model gets unlocked
     *
     * @param string $format
     * @param integer $sub_minutes
     * @return \Carbon\Carbon|null
     */
    public function lockedUntil($format = null, $sub_minutes = 0)
    {
        if ($this->isLocked()) {
            $timestamp = $this->modelLock->locked_until;

            if ($sub_minutes > 0) {
                $timestamp = $timestamp->subMinutes($sub_minutes);
            }

            if (! is_null($format)) {
                return $timestamp->format($format);
            }

            return $timestamp;
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
            $events->fire(new ModelLocked($this));
        }

        return $lock->getToken();
    }

    /**
     * Lock to a specific user for an optional duration
     *
     * @param  App\User $user
     * @param  integer $duration
     * @return string
     */
    public function lockTo($user, $duration = null)
    {
        return $this->lock($duration, $user);
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
            $events->fire(new ModelUnlocked($this));
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

    /**
     * Relation of locked models based on the user provided.
     *
     * @param  App\User $user
     * @return ModelLock
     */
    public function lockedModelsByUser($user)
    {
        return ModelLock::active()->where('user_id', $user->id);
    }
}
