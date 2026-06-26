<?php

namespace App\Controllers;

use App\Models\Service;
use App\Utils\Response;

class ServiceController {
    private $serviceModel;
    
    public function __construct() {
        $this->serviceModel = new Service();
    }
    
    // Get all services
    public function getAll() {
        $services = $this->serviceModel->getAllActive();
        Response::success($services, 'Services retrieved successfully');
    }
    
    // Get single service by ID
    public function getOne($id) {
        $service = $this->serviceModel->getById($id);
        
        if ($service) {
            Response::success($service, 'Service retrieved successfully');
        } else {
            Response::notFound('Service not found');
        }
    }
}