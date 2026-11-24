<?php

namespace App\Models;

use CodeIgniter\Model;

/** Redes educativas (grupos de colegios). No es un tenant: agrupa instituciones. */
class NetworkModel extends Model
{
    protected $table          = 'networks';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $allowedFields  = ['name', 'slug', 'city', 'accent_color', 'monthly_tokens', 'status'];
}
