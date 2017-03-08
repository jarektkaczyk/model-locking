<?php

namespace Sofa\ModelLocking;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * @package sofa/model-locking
 * @author Jarek Tkaczyk <jarek@softonsofa.com>
 * @link https://github.com/jarektkaczyk/model-locking
 */
class ModelLock extends Model
{
    /** @var array Attributes mutated to Carbon */
    protected $casts = [
        'locked_until' => 'datetime',
    ];

    /**
     * Register 'saving' event handler that will take care of storing default values on the lock.
     *
     * @return void
     */
    protected static function boot()
    {
        static::saving(function ($lock) {
            if (!$lock->isDirty('locked_until')) {
                $lock->locked_until = $lock->lockTimestamp();
            }

            if (!$lock->user_id) {
                $lock->user_id = $lock->lockingUser();
            }

            if (!$lock->token) {
                $lock->token = $lock->generateToken();
            }
        });

        static::deleted(function ($lock) {
            if ($lock->model && $events = $lock->getEventDispatcher()) {
                $events->fire(new ModelUnlocked($lock->model));
            }
        });
    }

    /**
     * Get token identifying this lock.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token ?: $this->token = $this->generateToken();
    }

    /**
     * Generate token for this lock.
     *
     * @return string
     */
    public function generateToken()
    {
        return md5((string) $this);
    }

    /**
     * Verify whether provided value is the valid token of this lock.
     *
     * @param  string $token
     * @return boolean
     */
    public function verify($token)
    {
        return $this->token === $token;
    }

    /**
     * Lock a model for period of time.
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
     * @return $this
     *
     * @throws \Exception                   When provided $duration cannot be parsed into valid date time object.
     */
    public function lock($duration = null, $user = null)
    {
        $this->locked_until = $this->lockTimestamp($duration);
        $this->user_id = $this->lockingUser($user);
        $this->save();

        return $this;
    }

    /**
     * Get the timestamp until when the lock takes effect.
     *
     * @param  \DateTime|string $duration  DateTime|Carbon object or parsable date string @see strtotime()
     * @return \Carbon\Carbon
     */
    protected function lockTimestamp($duration = null)
    {
        if (!$duration) {
            $duration = config('model_locking.duration', '5 minutes');
        }

        return Carbon::parse($duration);
    }

    /**
     * Get the identifier of user holding lock on the model.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|integer|string  $user
     * @return integer|string
     */
    protected function lockingUser($user = null)
    {
        if ($user instanceof Authenticatable) {
            return $user->getAuthIdentifier();
        }

        if ($user) {
            return $user;
        }

        if (config('model_locking.use_authenticated_user', true)) {
            return auth()->id();
        }
    }

    /**
     * Request model to be unlocked.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string $message
     * @param  boolean $shorten
     * @return void
     */
    public function requestUnlock($user = null, $message = '', $shorten = true)
    {
        if ($events = $this->getEventDispatcher()) {
            $events->fire(new ModelUnlockRequested($this->model, $user, $message));

            if ($shorten && $new_duration = config('model_locking.request_shorten_duration')) {
                $this->lock($new_duration, $this->user_id);
            }
        }
    }

    /**
     * Relation to the model being locked.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Relation to user holding the locking.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('model_locking.user_model', config('auth.providers.users.model', 'App\User')));
    }

    /**
     * Query scope active.
     *
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query, $active = true)
    {
        return $query->where('locked_until', ($active ? '>' : '<='), Carbon::now());
    }

    /**
     * Query scope expired.
     *
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->active(false);
    }
}
