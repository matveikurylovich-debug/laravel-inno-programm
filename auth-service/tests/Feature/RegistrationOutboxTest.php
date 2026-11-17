<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Junges\Kafka\Facades\Kafka;

it('commits registration and two durable deliveries without contacting brokers', function () {
    Kafka::shouldReceive('publish')->never();
    DB::transaction(function () {
        $user = User::factory()->create();
        event(new Registered($user));
    });
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('jobs', 2);
    expect(DB::table('jobs')->pluck('queue')->unique()->all())->toBe(['auth-events']);
});

it('rolls back registration deliveries with the user', function () {
    try {
        DB::transaction(function () {
            $user = User::factory()->create();
            event(new Registered($user));
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('jobs', 0);
});
