<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un client n'a pas de compte : il est enregistré par le commercial. */
class Client extends Model
{
    protected $guarded = [];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}
