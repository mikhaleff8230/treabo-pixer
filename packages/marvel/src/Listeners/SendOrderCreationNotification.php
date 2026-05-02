<?php

namespace Marvel\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Marvel\Enums\EventType;
use Marvel\Events\OrderCreated;
use Marvel\Notifications\NewOrderReceived;
use Marvel\Notifications\OrderPlacedSuccessfully;
use Marvel\Services\EmailService;
use Marvel\Traits\OrderSmsTrait;
use Marvel\Traits\SmsTrait;

class SendOrderCreationNotification implements ShouldQueue
{
    use SmsTrait, OrderSmsTrait;

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Handle the event.
     *
     * @param OrderCreated $event
     * @return void
     */
    public function handle(OrderCreated $event)
    {
        $order = $event->order;
        $customer = $event->order->customer;
        $emailReceiver = $this->getWhichUserWillGetEmail(EventType::ORDER_CREATED, $order->language);
        
        // Send email notifications using EmailService
        $this->emailService->sendOrderEventNotifications($order, 'created');
        
        // Keep existing notification logic for backward compatibility
        if ($customer && $emailReceiver['customer'] && $order->parent_id == null) {
            $customer->notify(new OrderPlacedSuccessfully($event->invoiceData));
        }
        if ($emailReceiver['admin']) {
            $admins = $this->adminList();
            foreach ($admins as $admin) {
                $admin->notify(new NewOrderReceived($order, 'admin'));
            }
        }
        
        // Send SMS
        $this->sendOrderCreationSms($order);
    }
}
