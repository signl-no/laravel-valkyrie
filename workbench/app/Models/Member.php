<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Signl\Valkyrie\Concerns\HasRolesAndAbilities;

#[Fillable(['name'])]
class Member extends Model
{
    use HasRolesAndAbilities, SoftDeletes;
}
