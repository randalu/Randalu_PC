<?php

namespace App\Services;

use App\Jobs\SendSms;
use App\Models\Order;
use App\Models\Setting;

class OrderSmsNotifier
{
    public function sendStatusUpdate(Order $order): void
    {
        if (! filter_var(Setting::getValue('sms_order_updates_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        SendSms::dispatch($order->customer_phone, $this->renderTemplate(
            Setting::getValue('sms_order_update_template', 'Your order {order_number} is now {status}. Track it at {tracking_url}'),
            $order,
        ));
    }

    private function renderTemplate(string $template, Order $order): string
    {
        return strtr($template, [
            '{order_number}' => $order->order_number,
            '{status}' => str($order->status)->headline()->toString(),
            '{tracking_url}' => route('orders.status'),
            '{courier}' => $order->courier_name ?? '',
            '{tracking_number}' => $order->tracking_number ?? '',
        ]);
    }
}
