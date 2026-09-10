<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    protected $connection = 'central';

    protected $fillable = [
        'team_id',
        'name',
        'active',
        'sync_general_data',
        'sync_catalog_data',
        'sync_cloud_catalog_data',
        'sync_measurements',
        'sync_liquidaciones_ecd',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sync_general_data' => 'boolean',
        'sync_catalog_data' => 'boolean',
        'sync_cloud_catalog_data' => 'boolean',
        'sync_measurements' => 'boolean',
        'sync_liquidaciones_ecd' => 'boolean',
    ];

    public function credentials(): HasOne
    {
        return $this->hasOne(TeamCredential::class, 'team_id', 'team_id');
    }

    public function databases(): HasMany
    {
        return $this->hasMany(TeamDatabase::class, 'team_id', 'team_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'team_id', 'team_id');
    }
}
