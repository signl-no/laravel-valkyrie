<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Signl\Valkyrie\Concerns\IsAccessScope;

#[Fillable(['name'])]
class Team extends Model
{
    use IsAccessScope, SoftDeletes;
}
