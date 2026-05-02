<?php


namespace App\Services\Tinkoff;


class TinkoffService

{
    public function __construct(

        public TinkoffConfig $config,
    
    
    )







use App\Services\Tinkoff\TinkoffConfig;
use App\Services\Tinkoff\TinkoffServices;

$config = config('services.tinkoff');



$tinkoff = new TinkoffService(

    new TinkoffConfig(
    terminal : $config{'terminal'},
    password : $config{'password'},
    )
);

$tinkoff→createPayment(123);
  
$tinkoff→findePayment(123);

$tinkoff→cancelPayment(123);

$tinkoff→checkCallback({});

}