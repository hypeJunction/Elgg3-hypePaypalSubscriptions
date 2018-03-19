<?php

return [
	'subscriptions.paypal' => \DI\object(\hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService::class)
		->constructor(\DI\get('paypal')),

	'subscriptions.gateways.paypal' => \DI\object(\hypeJunction\Subscriptions\Paypal\PaypalRecurringPaymentGateway::class)
		->constructor(\DI\get('paypal')),
];