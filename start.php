<?php

/**
 * Subscriptions
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 */
require_once __DIR__ . '/autoloader.php';

return function () {
	elgg_register_event_handler('init', 'system', function () {

		$svc = elgg()->subscriptions;
		/* @var $svc \hypeJunction\Subscriptions\SubscriptionsService */

		$svc->registerGateway(elgg()->{'subscriptions.gateways.paypal'});

		elgg_register_event_handler('update', 'object', \hypeJunction\Subscriptions\Paypal\OnUpdateEvent::class);
		elgg_register_event_handler('delete', 'object', \hypeJunction\Subscriptions\Paypal\OnDeleteEvent::class);

		elgg_register_event_handler('cancel', 'subscription', \hypeJunction\Subscriptions\Paypal\OnSubscriptionCancelEvent::class);

		elgg_register_plugin_hook_handler('register', 'menu:page', \hypeJunction\Subscriptions\Paypal\PageMenu::class);

		elgg_register_plugin_hook_handler('BILLING.PLAN.CREATED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPlanUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.PLAN.UPDATED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPlanUpdateHook::class);

		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.CANCELLED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.CREATED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.EXPIRED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.RE-ACTIVATED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.SUSPENDED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);
		elgg_register_plugin_hook_handler('BILLING.SUBSCRIPTION.UPDATED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestSubscriptionUpdateHook::class);

		elgg_register_plugin_hook_handler('PAYMENT.SALE.PENDING', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPaymentWebhook::class);
		elgg_register_plugin_hook_handler('PAYMENT.SALE.COMPLETED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPaymentWebhook::class);
		elgg_register_plugin_hook_handler('PAYMENT.SALE.REFUNDED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPaymentWebhook::class);
		elgg_register_plugin_hook_handler('PAYMENT.SALE.DENIED', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPaymentWebhook::class);
		elgg_register_plugin_hook_handler('PAYMENT.SALE.REVERSE', 'paypal', \hypeJunction\Subscriptions\Paypal\DigestPaymentWebhook::class);
	});
};
