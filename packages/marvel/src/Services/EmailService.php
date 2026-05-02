<?php

namespace Marvel\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Shop;
use Marvel\Mail\ContactAdmin;
use Marvel\Mail\ForgetPassword;
use Marvel\Mail\AdminCommissionRateUpdate;
use Marvel\Mail\VendorCommissionRateUpdate;
use Marvel\Mail\CustomEmail;
use Marvel\Mail\TestEmail;
use Marvel\Notifications\NewOrderReceived;
use Marvel\Notifications\OrderPlacedSuccessfully;
use Marvel\Notifications\OrderCancelledNotification;
use Marvel\Notifications\OrderDeliveredNotification;
use Marvel\Notifications\OrderStatusChangedNotification;
use Marvel\Notifications\PaymentSuccessfulNotification;
use Marvel\Notifications\PaymentFailedNotification;
use Marvel\Notifications\ProductApprovedNotification;
use Marvel\Notifications\ProductRejectedNotification;
use Marvel\Notifications\NewReviewCreated;
use Marvel\Notifications\NotifyQuestionAnswered;
use Marvel\Notifications\RefundRequested;
use Marvel\Notifications\RefundUpdate;
use Marvel\Notifications\StoreNoticeNotification;
use Marvel\Notifications\NewOrderProcessed;
use Marvel\Exceptions\MarvelException;

class EmailService
{
    /**
     * Send contact form email to admin
     */
    public function sendContactEmail(array $details): bool
    {
        try {
            Mail::to(config('shop.admin_email'))->send(new ContactAdmin($details));
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send contact email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        try {
            Mail::to($user->email)->send(new ForgetPassword($user, $token));
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order notification to customer
     */
    public function sendOrderNotificationToCustomer(Order $order, string $notificationType): bool
    {
        try {
            if (!$order->customer) {
                return false;
            }

            switch ($notificationType) {
                case 'created':
                    $order->customer->notify(new OrderPlacedSuccessfully($order));
                    break;
                case 'processed':
                    $order->customer->notify(new NewOrderProcessed($order));
                    break;
                case 'delivered':
                    $order->customer->notify(new OrderDeliveredNotification($order));
                    break;
                case 'cancelled':
                    $order->customer->notify(new OrderCancelledNotification($order));
                    break;
                case 'status_changed':
                    $order->customer->notify(new OrderStatusChangedNotification($order));
                    break;
                default:
                    return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send order notification to customer: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order notification to store owner
     */
    public function sendOrderNotificationToStoreOwner(Order $order, string $notificationType): bool
    {
        try {
            if (!$order->shop || !$order->shop->owner) {
                return false;
            }

            switch ($notificationType) {
                case 'created':
                    $order->shop->owner->notify(new NewOrderReceived($order, 'storeOwner'));
                    break;
                case 'processed':
                    $order->shop->owner->notify(new NewOrderProcessed($order));
                    break;
                case 'delivered':
                    $order->shop->owner->notify(new OrderDeliveredNotification($order));
                    break;
                case 'cancelled':
                    $order->shop->owner->notify(new OrderCancelledNotification($order));
                    break;
                case 'status_changed':
                    $order->shop->owner->notify(new OrderStatusChangedNotification($order));
                    break;
                default:
                    return false;
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send order notification to store owner: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order notification to admin
     */
    public function sendOrderNotificationToAdmin(Order $order, string $notificationType): bool
    {
        try {
            $admins = $this->getAdminUsers();
            
            if ($admins->isEmpty()) {
                return false;
            }

            foreach ($admins as $admin) {
                switch ($notificationType) {
                    case 'created':
                        $admin->notify(new NewOrderReceived($order, 'admin'));
                        break;
                    case 'processed':
                        $admin->notify(new NewOrderProcessed($order));
                        break;
                    case 'delivered':
                        $admin->notify(new OrderDeliveredNotification($order));
                        break;
                    case 'cancelled':
                        $admin->notify(new OrderCancelledNotification($order));
                        break;
                    case 'status_changed':
                        $admin->notify(new OrderStatusChangedNotification($order));
                        break;
                    default:
                        return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send order notification to admin: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send product notification
     */
    public function sendProductNotification(Product $product, string $notificationType, string $recipientType): bool
    {
        try {
            if ($recipientType === 'vendor' && $product->shop && $product->shop->owner) {
                if ($notificationType === 'approved') {
                    $product->shop->owner->notify(new ProductApprovedNotification($product));
                } else {
                    $product->shop->owner->notify(new ProductRejectedNotification($product));
                }
                return true;
            } elseif ($recipientType === 'admin') {
                $admins = $this->getAdminUsers();
                
                foreach ($admins as $admin) {
                    if ($notificationType === 'approved') {
                        $admin->notify(new ProductApprovedNotification($product));
                    } else {
                        $admin->notify(new ProductRejectedNotification($product));
                    }
                }
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Failed to send product notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment notification
     */
    public function sendPaymentNotification(Order $order, string $notificationType, string $recipientType): bool
    {
        try {
            if ($recipientType === 'customer' && $order->customer) {
                if ($notificationType === 'successful') {
                    $order->customer->notify(new PaymentSuccessfulNotification($order));
                } else {
                    $order->customer->notify(new PaymentFailedNotification($order));
                }
                return true;
            } elseif ($recipientType === 'vendor' && $order->shop && $order->shop->owner) {
                if ($notificationType === 'successful') {
                    $order->shop->owner->notify(new PaymentSuccessfulNotification($order));
                } else {
                    $order->shop->owner->notify(new PaymentFailedNotification($order));
                }
                return true;
            } elseif ($recipientType === 'admin') {
                $admins = $this->getAdminUsers();
                
                foreach ($admins as $admin) {
                    if ($notificationType === 'successful') {
                        $admin->notify(new PaymentSuccessfulNotification($order));
                    } else {
                        $admin->notify(new PaymentFailedNotification($order));
                    }
                }
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Failed to send payment notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bulk email to users
     */
    public function sendBulkEmail(array $userIds, string $subject, string $message, string $template = 'emails.custom'): int
    {
        try {
            $users = User::whereIn('id', $userIds)->get();
            $sentCount = 0;

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new CustomEmail($user, $subject, $message, $template));
                    $sentCount++;
                } catch (\Exception $e) {
                    \Log::error('Failed to send email to user ' . $user->id . ': ' . $e->getMessage());
                }
            }

            return $sentCount;
        } catch (\Exception $e) {
            \Log::error('Failed to send bulk email: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send commission rate update notification
     */
    public function sendCommissionRateUpdate(Shop $shop, float $oldRate, float $newRate, string $recipientType): bool
    {
        try {
            if ($recipientType === 'vendor' && $shop->owner) {
                Mail::to($shop->owner->email)->send(new VendorCommissionRateUpdate($shop, $oldRate, $newRate));
                return true;
            } elseif ($recipientType === 'admin') {
                $admins = $this->getAdminUsers();
                
                foreach ($admins as $admin) {
                    Mail::to($admin->email)->send(new AdminCommissionRateUpdate($shop, $oldRate, $newRate));
                }
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Failed to send commission rate update: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send test email
     */
    public function sendTestEmail(string $email): bool
    {
        try {
            Mail::to($email)->send(new TestEmail());
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send test email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all relevant parties for an order event
     */
    public function sendOrderEventNotifications(Order $order, string $eventType): array
    {
        $results = [
            'customer' => false,
            'store_owner' => false,
            'admin' => false
        ];

        // Send to customer
        if ($order->customer) {
            $results['customer'] = $this->sendOrderNotificationToCustomer($order, $eventType);
        }

        // Send to store owner
        if ($order->shop && $order->shop->owner) {
            $results['store_owner'] = $this->sendOrderNotificationToStoreOwner($order, $eventType);
        }

        // Send to admin
        $results['admin'] = $this->sendOrderNotificationToAdmin($order, $eventType);

        return $results;
    }

    /**
     * Send notification to all relevant parties for a product event
     */
    public function sendProductEventNotifications(Product $product, string $eventType): array
    {
        $results = [
            'vendor' => false,
            'admin' => false
        ];

        // Send to vendor
        if ($product->shop && $product->shop->owner) {
            $results['vendor'] = $this->sendProductNotification($product, $eventType, 'vendor');
        }

        // Send to admin
        $results['admin'] = $this->sendProductNotification($product, $eventType, 'admin');

        return $results;
    }

    /**
     * Send notification to all relevant parties for a payment event
     */
    public function sendPaymentEventNotifications(Order $order, string $eventType): array
    {
        $results = [
            'customer' => false,
            'store_owner' => false,
            'admin' => false
        ];

        // Send to customer
        if ($order->customer) {
            $results['customer'] = $this->sendPaymentNotification($order, $eventType, 'customer');
        }

        // Send to store owner
        if ($order->shop && $order->shop->owner) {
            $results['store_owner'] = $this->sendPaymentNotification($order, $eventType, 'vendor');
        }

        // Send to admin
        $results['admin'] = $this->sendPaymentNotification($order, $eventType, 'admin');

        return $results;
    }

    /**
     * Get admin users
     */
    private function getAdminUsers()
    {
        return User::whereHas('permissions', function($query) {
            $query->where('name', 'super_admin');
        })->get();
    }

    /**
     * Check if email configuration is valid
     */
    public function isEmailConfigurationValid(): array
    {
        $config = [
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
            'admin_email' => config('shop.admin_email'),
            'mail_driver' => config('mail.default'),
            'is_valid' => false
        ];

        if ($config['from_address'] && $config['admin_email'] && $config['mail_driver']) {
            $config['is_valid'] = true;
        }

        return $config;
    }
}


