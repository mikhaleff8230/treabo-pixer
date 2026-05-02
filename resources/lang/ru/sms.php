<?php
return [
    'order' => [
        'cancelOrder'         => [
            'admin'      => [
                'message' => 'Заказ был отменен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Заказ был отменен',
            ],
            'customer'   => [
                'message' => 'Ваш заказ был отменен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Ваш заказ был отменен',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, заказ был отменен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Заказ был отменен',
            ],
        ],
        'orderCreated'        => [
            'admin'      => [
                'message' => 'Новый заказ размещен клиентом :customer_name с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Новый заказ размещен',
            ],
            'customer'   => [
                'message' => 'Ваш заказ успешно размещен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Ваш заказ успешно размещен',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, новый заказ размещен клиентом :customer_name с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Новый заказ размещен',
            ],
        ],
        'deliverOrder'        => [
            'admin'      => [
                'message' => 'Заказ успешно доставлен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Заказ доставлен',
            ],
            'customer'   => [
                'message' => 'Ваш заказ успешно доставлен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Ваш заказ успешно доставлен',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, заказ успешно доставлен с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Заказ доставлен',
            ],

        ],
        'statusChangeOrder'   => [
            'admin'      => [
                'message' => 'Статус заказа изменен на :order_status с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Статус заказа изменен',
            ],
            'customer'   => [
                'message' => 'Статус вашего заказа изменен на :order_status с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Статус вашего заказа изменен',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, статус заказа изменен на :order_status с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Статус заказа изменен',
            ],
        ],
        'paymentSuccessOrder' => [
            'admin'      => [
                'message' => 'Оплата заказа прошла успешно с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата заказа прошла успешно',
            ],
            'customer'   => [
                'message' => 'Оплата вашего заказа прошла успешно с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата вашего заказа прошла успешно',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, оплата заказа прошла успешно с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата заказа прошла успешно',
            ],
        ],
        'paymentFailedOrder'  => [
            'admin'      => [
                'message' => 'Оплата заказа не прошла с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата заказа не прошла',
            ],
            'customer'   => [
                'message' => 'Оплата вашего заказа не прошла с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата вашего заказа не прошла',
            ],
            'storeOwner' => [
                'message' => 'Уважаемый владелец магазина, оплата заказа не прошла с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Оплата заказа не прошла',
            ],
        ],
        'refundRequested'     => [
            'admin'    => [
                'message' => 'Клиент запросил возврат средств для заказа с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Запрошен возврат средств',
            ],
            'customer' => [
                'message' => 'Ваш запрос на возврат средств успешно отправлен для заказа с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Ваш запрос на возврат средств успешно отправлен',
            ],
        ],
        'refundStatusChange' => [
            'admin'    => [
                'message' => 'Статус возврата изменен на :refund_status для заказа с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Статус возврата изменен',
            ],
            'customer' => [
                'message' => 'Статус вашего возврата изменен на :refund_status для заказа с номером отслеживания :ORDER_TRACKING_NUMBER',
                'subject' => 'Статус вашего возврата изменен',
            ],
        ],
    ]
];

