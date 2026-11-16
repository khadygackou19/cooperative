<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un chauffeur (livreur) n'a pas de compte de connexion : il est géré par le magasinier / commercial. */
class Chauffeur extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['disponible' => 'boolean'];
    }
}
