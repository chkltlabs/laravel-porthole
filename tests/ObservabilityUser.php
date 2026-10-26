<?php

declare(strict_types=1);

namespace Chkltlabs\ObservabilityMcp\Tests;

use Illuminate\Foundation\Auth\User;

class ObservabilityUser extends User
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->id = $attributes['id'] ?? 1;
        $this->email = $attributes['email'] ?? 'ops@example.com';
    }
}
