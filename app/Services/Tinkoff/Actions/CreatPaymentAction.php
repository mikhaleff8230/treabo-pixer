<?php


namespace App\Services\Tinkoff\Actions;


class CreatePaymentAction

{
public function __construct(
    private Tinkoffservice $tinkoff,
)

{

}


    public static function make(TinkoffService $tinkoff): static

{
    return new static($tinkoff);
}

public function run(CreatePaymentData $data): PaymentEntity
{

       $response = Http::post('https://securepay.tinkoff.ru/v2/Init', [
       
       'Terminalkey' => $this->tinkoff->config->terminal,
       'Amount' => $data->amount,
       'OrderId' => $data->order,
       
       ]);
          
          $respons = $response->json();

          if ($response['Success'] === false) {

            throw new TinkoffExeption($response['Details']);
          }
  
  
    }



}
