# Sofa/ModelLocking

[![Build Status](https://travis-ci.org/jarektkaczyk/model-locking.svg)](https://travis-ci.org/jarektkaczyk/model-locking) [![Coverage Status](https://coveralls.io/repos/jarektkaczyk/model-locking/badge.svg)](https://coveralls.io/r/jarektkaczyk/model-locking) [![Downloads](https://poser.pugx.org/sofa/model-locking/downloads)](https://packagist.org/packages/sofa/model-locking) [![stable](https://poser.pugx.org/sofa/model-locking/v/stable.svg)](https://packagist.org/packages/sofa/model-locking)

Pseudo pessimistic model locking for the [Eloquent ORM (Laravel 5.3+)](https://laravel.com/docs/5.3/eloquent). 

## Installation

Package goes along with Laravel (Illuminate) versioning for your convenience:

Laravel / Illuminate **5.3+**:

1. require package: `composer require sofa/model-locking:"~5.3"`
2. add to your `config/app.php` under `providers`: `Sofa\ModelLocking\ServiceProvider::class,`
3. publish package assets: `php artisan vendor:publish --provider="Sofa\ModelLocking\ServiceProvider"`
4. create model locks table by running `php artisan migrate`
5. add trait `use \Sofa\ModelLocking\Locking` to the model that should offer locking
6. OPTIONALLY customize package config in `config/model_locking.php`


## Usage

Basic example:

```php
// controller
public function edit(Post $post)
{
    if ($post->isLocked()) {
        return response([
            'status' => 'locked',
            'message' => 'Resource you are trying to access is locked',
            'lock_expiration' => $post->lockedUntil(),
        ], 423);
    }

    return view('posts.edit', compact('post'));
}

public function update(Post $post)
{
    if ($post->isAccessible(request('lock_token'))) {
        return redirect()->back()
                         ->withErrors(['danger' => 'Resource you are trying to update is locked']);
    }

    $post->update(request()->all());
    // broadcasts ModelUnlocked event, so you can push notification
    // to the user who tried to access locked post.
    $post->unlock();

    return redirect('posts.index');
}

public function requestUnlock(Post $post)
{
    if ($post->isAccessible()) {
        $token = $post->lock('5 minutes', auth()->user());

        return response([
            'status' => 'unlocked',
            'message' => 'Resource is now locked by you',
            'lock_expiration' => $post->lockedUntil(),
            'lock_token' => $token,
        ]);
    }

    // broadcasts ModelUnlockRequested event, so you can push
    // notification to the user who locked the resource.
    $post->requestUnlock(auth()->user(), request('unlock_message'));
}

// app/Console/Kernel - it will remove expired locks
//                      AND fire ModelUnlocked event for all of them
$schedule->command('locks:flush')->everyMinute();


// Available broadcasting events:
// new ModelLocked($post)
// new ModelUnlocked($post)
// new ModelUnlockRequested($post, $requesting_user, $request_message)
```


soon more in-depth info, meanwhile take a look at the specs:

```php
  /\ /\__ _| |__ | | __ _ _ __
 / //_/ _` | '_ \| |/ _` | '_ \
/ __ \ (_| | | | | | (_| | | | |
\/  \/\__,_|_| |_|_|\__,_|_| |_|

Sofa\ModelLocking\Locking
  ✔ it checks if active lock for model exists
  ✔ it checks if existing lock is still active
  ✔ it gets user who locked model
  ✔ it gets null as timestamp and user if model is not locked
  ✔ it sets by default authenticated user as one who is locking the model
  ✔ it unlocks the model on demand
  ✔ it lets you request unlock of a locked model
  ✔ it allows setting lock shortening when unlock request is made
  ✔ it verifies if model can be accessed with provided token
  ✔ it allows passing user_id as locking user
  ✔ it allows passing user instance as only param to `lock` method
  Lock duration precedence
    ✔ it locks the model for provided time by given user
    ✔ it next takes `lock_duration` property if set on the model
    ✔ it then falls back to the config
    ✔ it finally gets the default value: 5 minutes
  Fires broadcasting events to make push notifications a cinch
    ✔ it fires event when model is being locked
    ✔ it fires event when model is being unlocked
    ✔ it fires event when unlock request is made, with optional: requesting user and his message


Executed 23 of 23 PASS in 0.337 seconds
```


## Contribution

All contributions are welcome, PRs must be **tested** (using [kahlan](http://kahlan.readthedocs.io)) and  **PSR-2 compliant**.
