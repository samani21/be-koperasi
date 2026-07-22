<?php

namespace App\Http\Controllers;

use App\Services\ApiService;

abstract class Controller
{
    protected $apiService;
    public function __construct(ApiService $apiService,)
    {
        $this->apiService = $apiService;
    }
}
