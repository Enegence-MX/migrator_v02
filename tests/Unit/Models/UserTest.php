<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;

class UserTest extends TestCase
{
    /**
     * Test User model has correct fillable attributes
     */
    public function test_user_has_fillable_attributes()
    {
        $user = new User();

        $expected = [
            'name',
            'email',
            'password',
        ];

        $this->assertEquals($expected, $user->getFillable());
    }

    /**
     * Test User model has correct hidden attributes
     */
    public function test_user_has_hidden_attributes()
    {
        $user = new User();

        $expected = [
            'password',
            'remember_token',
        ];

        $this->assertEquals($expected, $user->getHidden());
    }

    /**
     * Test User model casts email_verified_at to datetime
     */
    public function test_user_casts_email_verified_at()
    {
        $user = new User();

        $casts = $user->getCasts();

        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertEquals('datetime', $casts['email_verified_at']);
    }

    /**
     * Test User model can be instantiated
     */
    public function test_user_can_be_instantiated()
    {
        $user = new User();

        $this->assertInstanceOf(User::class, $user);
    }

    /**
     * Test User model extends Authenticatable
     */
    public function test_user_extends_authenticatable()
    {
        $user = new User();

        $this->assertInstanceOf(\Illuminate\Foundation\Auth\User::class, $user);
    }

    /**
     * Test User uses HasApiTokens trait
     */
    public function test_user_uses_has_api_tokens_trait()
    {
        $traits = class_uses(User::class);

        $this->assertContains(\Laravel\Sanctum\HasApiTokens::class, $traits);
    }

    /**
     * Test User uses Notifiable trait
     */
    public function test_user_uses_notifiable_trait()
    {
        $traits = class_uses(User::class);

        $this->assertContains(\Illuminate\Notifications\Notifiable::class, $traits);
    }

    /**
     * Test User uses HasFactory trait
     */
    public function test_user_uses_has_factory_trait()
    {
        $traits = class_uses(User::class);

        $this->assertContains(\Illuminate\Database\Eloquent\Factories\HasFactory::class, $traits);
    }
}
