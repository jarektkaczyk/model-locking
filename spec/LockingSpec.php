<?php

use Kahlan\Arg;
use Carbon\Carbon;
use Kahlan\Plugin\Stub;
use Kahlan\Plugin\Monkey;
use Sofa\ModelLocking\Locking;
use Sofa\ModelLocking\ModelLock;
use Sofa\ModelLocking\ModelLocked;
use Sofa\ModelLocking\ModelUnlocked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Events\Dispatcher;
use Sofa\ModelLocking\ModelUnlockRequested;
use Illuminate\Contracts\Auth\Authenticatable;

describe('Sofa\ModelLocking\Locking', function () {

    /*
    |--------------------------------------------------------------------------
    | Arrangements for test cases reside below
    |--------------------------------------------------------------------------
    */

    it('checks if active lock for model exists', function () {
        $this->post->modelLock = null;
        expect($this->post->isLocked())->toBe(false);
    });


    it('checks if existing lock is still active', function () {
        expect($this->post->isLocked())->toBe(true);

        $this->lock->locked_until = Carbon::parse('-1 minute');
        expect($this->post->isLocked())->toBe(false);
    });


    it('gets user who locked model', function () {
        $this->lock->locked_until = Carbon::parse('+1 minute');
        expect($this->post->lockedBy())->toBe($this->user);
    });


    it('gets null as timestamp and user if model is not locked', function () {
        $this->post->modelLock = null;
        expect($this->post->lockedUntil())->toBeNull();
        expect($this->post->lockedBy())->toBeNull();
    });


    it('sets by default authenticated user as one who is locking the model', function () {
        $this->post->lock();
        expect($this->post->lockedBy())->toEqual($this->user);
    });


    it('unlocks the model on demand', function () {
        expect($this->post->isLocked())->toBe(true);
        $this->post->unLock();
        expect(isset($this->post->relations['modelLock']))->toBe(false);
    });


    it('lets you request unlock of a locked model', function () {
        $this->lock->setRelation('model', $this->post, false);
        $this->post->requestUnlock();
    });


    it('allows setting lock shortening when unlock request is made', function () {
        Monkey::patch('config', function ($key) {
            if ($key == 'model_locking.request_shorten_duration') return '66 seconds';
        });
        $this->lock->setRelation('model', $this->post);
        $this->post->requestUnlock();
        expect($this->post->lockedUntil().'')->toEqual(Carbon::now()->addSeconds(66).'');
    });


    it('verifies if model can be accessed with provided token', function () {
        expect($this->post->isAccessible('invalid_token'))->toBe(false);
        expect($this->post->isAccessible($this->lock->getToken()))->toBe(true);
        $this->post->lock('-1 minute');
        expect($this->post->isAccessible())->toBe(true);
    });


    it('allows passing user_id as locking user', function () {
        $this->post->lock('11 minutes', 11);
        expect($this->post->modelLock->user_id)->toBe(11);
    });


    it('allows passing user instance as only param to `lock` method', function () {
        $other_user = Stub::create(['implements' => Authenticatable::class]);
        Stub::on($other_user)->method('getAuthIdentifier')->andReturn(99);
        $this->post->lock($other_user);
        expect($this->post->modelLock->user_id)->toBe(99);
    });


    context('Lock duration precedence', function () {

        it('locks the model for provided time by given user', function () {
            $this->post->lock('2 minutes', $this->user);
            expect($this->post->lockedUntil())->toEqual(Carbon::now()->addMinutes(2));
            expect($this->post->lockedBy())->toBe($this->user);
        });

        it('next takes `lock_duration` property if set on the model', function () {
            $this->post->lock_duration = '3 minutes';
            $this->post->lock();
            expect($this->post->lockedUntil())->toEqual(Carbon::now()->addMinutes(3));
        });

        it('then falls back to the config', function () {
            Monkey::patch('config', function ($key) {
                if ($key == 'model_locking.duration') return '10 minutes';
                if ($key == 'model_locking.use_authenticated_user') return false;
            });
            $this->post->lock();
            expect($this->post->lockedUntil())->toEqual(Carbon::now()->addMinutes(10));
        });

        it('finally gets the default value: 5 minutes', function () {
            $this->post->lock();
            expect($this->post->lockedUntil())->toEqual(Carbon::now()->addMinutes(5));
        });
    });


    context('Fires broadcasting events to make push notifications a cinch', function () {

        it('fires event when model is being locked', function () {
            expect($this->events)->toReceive('fire')->with(Arg::toEqual(new ModelLocked($this->post)));
            $this->post->lock();
        });

        it('fires event when model is being unlocked', function () {
            expect($this->events)->toReceive('fire')->with(Arg::toEqual(new ModelUnlocked($this->post)));
            $this->post->unlock();
        });

        it('fires event when unlock request is made, with optional: requesting user and his message', function () {
            $this->lock->setRelation('model', $this->post);
            expect($this->events)->toReceive('fire')->with(Arg::toEqual(
                new ModelUnlockRequested($this->post, 'requesting user', 'request message')
            ));
            $this->post->requestUnlock('requesting user', 'request message');
        });
    });


    /*
    |--------------------------------------------------------------------------
    | Arrangements
    |--------------------------------------------------------------------------
    */
    beforeEach(function () {
        Monkey::patch('config', function ($key, $default = null) {
            return $default;
        });

        Monkey::patch('auth', function () {
            return Stub::create();
        });
    });

    given('config', function () {
        return (object) [
            'model_locking.duration' => null,
            'model_locking.use_authenticated_user' => null,
            'model_locking.request_shorten_duration' => null,
            'model_locking.user' => null,
            'auth.providers.users.model' => null,
        ];
    });

    given('user', function () {
        return Stub::create(['implements' => Authenticatable::class]);
    });

    given('events', function () {
        return Stub::create(['implements' => Dispatcher::class]);
    });

    given('lock', function () {
        $lock = Stub::create(['extends' => ModelLock::class]);
        Stub::on($lock)->method('getDateFormat')->andReturn('Y-m-d H:i:s');
        Stub::on($lock)->method('save')->andReturn(true);

        $lock->locked_until = Carbon::now()->addMinutes(1);
        $lock->setRelation('user', $this->user);
        $lock->setEventDispatcher($this->events);

        return $lock;
    });

    given('post', function () {
        $PostModel = Stub::create(['uses' => Locking::class]);
        $post = new $PostModel;
        $post->modelLock = $this->lock;
        $post->relations = ['modelLock' => $this->lock];
        Stub::on($post)->method('getEventDispatcher')->andReturn($this->events);

        $query = Stub::create();
        Stub::on($post)->method('morphOne')->andReturn($query);
        Stub::on($query)->method('active')->andReturn($query);
        Stub::on($query)->method('firstOrNew')->andReturn($this->lock);

        return $post;
    });
});
