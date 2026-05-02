<?php


namespace App\Services\Tinkoff\Actions;


class CreatePaymentData

{
    public function __construct(

          public int $amount,
          public string $order,
          public string $successUrl,
          public string $failureUrl,
          public string $callbackUrl,

    )

{
    
}






}