<?php

return [
	'actions' => [
		'subscriptions/paypal/import' => [
			'controller' => \hypeJunction\Subscriptions\Paypal\ImportPaypalPlans::class,
			'access' => 'admin',
		],
		'payments/checkout/paypal/subscription' => [
			'controller' =>\hypeJunction\Subscriptions\Paypal\CreateAgreementAction::class,
			'access' => 'public',
			'middleware' => [
				\Elgg\Router\Middleware\AjaxGatekeeper::class,
			],
		],
	],
];
