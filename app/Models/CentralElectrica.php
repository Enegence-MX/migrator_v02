<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CentralElectrica extends Model
{
    protected $connection = 'mysql_dev_2';
    protected $table = 'centralElectrica';
    protected $guarded = [];
}
