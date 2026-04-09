<?php

namespace Flutterwave\sdk;

class IndexSdk {

    const WALLET_MONEY_ADD = 'wallet_money_add';

    private \$paymentDescriptions;

    public function __construct() {
        // Initialize variables and configurations 
        \$this->paymentDescriptions = [];
    }

    public function addPaymentDescription(\$description) {
        if (!empty(\$description)) {
            \$this->paymentDescriptions[] = \$description;
        }
    }

    public function getPaymentDescriptions() {
        return \$this->paymentDescriptions;
    }

    // Method to remove header issue example
    public function makeRequest(\$url, \$data) {
        // Code to make a request without header issues
        \$response = file_get_contents(\$url . '?' . http_build_query(\$data));
        return json_decode(\$response, true);
    }
}

?>