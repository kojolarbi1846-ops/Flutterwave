<?php
// Include the necessary libraries and configurations
require 'vendor/autoload.php';

use Flutterwavelutterwave;

// Set your secret key here
$secret_key = 'YOUR_FLUTTERWAVE_SECRET_KEY';
$public_key = 'YOUR_FLUTTERWAVE_PUBLIC_KEY';

// Capture the payment data from the AJAX request
$request_data = json_decode(file_get_contents('php://input'), true);

// Validate the incoming request data
if (!isset($request_data['amount']) || !isset($request_data['currency'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment data.']);
    exit;
}

$amount = $request_data['amount'];
$currency = $request_data['currency'];

// Create a new Flutterwave payment request
$flutterwave = new flutterwave($secret_key);
$payment_response = $flutterwave->initPayment(
    [
        'amount' => $amount,
        'currency' => $currency,
        'tx_ref' => uniqid(),
        'email' => $request_data['email'],
        'phone_number' => $request_data['phone_number'], // optional
        // other parameters as required
    ]
);

if ($payment_response['status'] === 'success') {
    // Payment initiated successfully
    echo json_encode(['status' => 'success', 'data' => $payment_response]);
} else {
    // Something went wrong
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Payment initiation failed.']);
}
?>