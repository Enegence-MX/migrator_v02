<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Festivo extends Model
{
    protected $connection = 'mysql_dev_2';
    protected $guarded = [];
    public $timestamps = false;
}
