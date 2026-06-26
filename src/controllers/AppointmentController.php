<?php

namespace App\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Barber;
use App\Models\Service;
use App\Utils\Response;
use App\Utils\Validator;

class AppointmentController {
    private $appointmentModel;
    private $barberModel;
    private $serviceModel;
    private $userModel;
    
    public function __construct() {
        $this->appointmentModel = new Appointment();
        $this->barberModel = new Barber();
        $this->serviceModel = new Service();
        $this->userModel = new User();
    }
    
    // Get all appointments (with filters)
    public function getAll() {
        // Get token from header to identify user
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        
        if ($token) {
            $userData = json_decode(base64_decode($token), true);
            $user_id = $userData['user_id'] ?? null;
            $user_role = $userData['role'] ?? null;
            
            if ($user_role === 'admin') {
                $appointments = $this->appointmentModel->getAllWithDetails();
            } elseif ($user_role === 'barber') {
                $appointments = $this->appointmentModel->getByBarber($user_id);
            } else {
                $appointments = $this->appointmentModel->getByCustomer($user_id);
            }
        } else {
            Response::unauthorized('Please login to view appointments');
            return;
        }
        
        Response::success($appointments, 'Appointments retrieved successfully');
    }
    
    // Get single appointment
    public function getOne($id) {
        $appointment = $this->appointmentModel->getWithDetails($id);
        
        if ($appointment) {
            Response::success($appointment, 'Appointment retrieved successfully');
        } else {
            Response::notFound('Appointment not found');
        }
    }
    
    // Create new appointment
    public function create($data) {
        // Validate required fields
        $validation = Validator::validate($data, [
            'customer_id' => ['required'],
            'barber_id' => ['required'],
            'service_id' => ['required'],
            'appointment_date' => ['required'],
            'appointment_time' => ['required']
        ]);
        
        if ($validation) {
            Response::validationError($validation);
            return;
        }
        
        // Check if service exists
        $service = $this->serviceModel->getActiveById($data['service_id']);
        if (!$service) {
            Response::error('Service not found', 404);
            return;
        }
        
        // Check if barber exists and is active
        $barber = $this->barberModel->getBarberWithDetails($data['barber_id']);
        if (!$barber) {
            Response::error('Barber not found', 404);
            return;
        }
        
        // Check if customer exists
        $customer = $this->userModel->findById($data['customer_id']);
        if (!$customer) {
            Response::error('Customer not found', 404);
            return;
        }
        
        // Check availability
        $isAvailable = $this->barberModel->checkAvailability(
            $data['barber_id'],
            $data['appointment_date'],
            $data['appointment_time']
        );
        
        if (!$isAvailable) {
            Response::error('Barber is not available at this time', 400);
            return;
        }
        
        // Prepare appointment data
        $appointmentData = [
            'customer_id' => $data['customer_id'],
            'barber_id' => $data['barber_id'],
            'service_id' => $data['service_id'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'total_price' => $service['price'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null
        ];
        
        // Create appointment
        $appointment_id = $this->appointmentModel->create($appointmentData);
        
        if ($appointment_id) {
            $appointment = $this->appointmentModel->getWithDetails($appointment_id);
            Response::success($appointment, 'Appointment booked successfully!', 201);
        } else {
            Response::error('Failed to book appointment', 500);
        }
    }
    
    // Update appointment (status, reschedule)
    public function update($id, $data) {
        $appointment = $this->appointmentModel->getById($id);
        
        if (!$appointment) {
            Response::notFound('Appointment not found');
            return;
        }
        
        // If rescheduling, check availability
        if (isset($data['appointment_date']) || isset($data['appointment_time'])) {
            $new_date = $data['appointment_date'] ?? $appointment['appointment_date'];
            $new_time = $data['appointment_time'] ?? $appointment['appointment_time'];
            
            $isAvailable = $this->barberModel->checkAvailability(
                $appointment['barber_id'],
                $new_date,
                $new_time
            );
            
            if (!$isAvailable) {
                Response::error('Barber is not available at the requested time', 400);
                return;
            }
        }
        
        // Update appointment
        if ($this->appointmentModel->update($id, $data)) {
            $updated = $this->appointmentModel->getWithDetails($id);
            Response::success($updated, 'Appointment updated successfully');
        } else {
            Response::error('Failed to update appointment', 500);
        }
    }
    
    // Cancel appointment
    public function delete($id) {
        $appointment = $this->appointmentModel->getById($id);
        
        if (!$appointment) {
            Response::notFound('Appointment not found');
            return;
        }
        
        // Soft delete - just update status
        if ($this->appointmentModel->update($id, ['status' => 'cancelled'])) {
            Response::success(null, 'Appointment cancelled successfully');
        } else {
            Response::error('Failed to cancel appointment', 500);
        }
    }
}