<?php

namespace App\Controllers;

use App\Models\Barber;
use App\Utils\Response;

class BarberController {
    private $barberModel;
    
    public function __construct() {
        $this->barberModel = new Barber();
    }
    
    // Get all barbers
    public function getAll() {
        $barbers = $this->barberModel->getAllWithDetails();
        Response::success($barbers, 'Barbers retrieved successfully');
    }
    
    // Get single barber by ID
    public function getOne($id) {
        $barber = $this->barberModel->getBarberWithDetails($id);
        
        if ($barber) {
            Response::success($barber, 'Barber retrieved successfully');
        } else {
            Response::notFound('Barber not found');
        }
    }
}