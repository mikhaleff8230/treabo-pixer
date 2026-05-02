<?php

namespace Marvel\Payment;

use Marvel\Payments\PaymentInterface;

class Cash implements PaymentInterface
{
    public function charge($request) {
        // логика оплаты
    }

    public function verify($request) {
        // логика проверки оплаты
    }

    public function refund($request) {
        // логика возврата
    }
}

