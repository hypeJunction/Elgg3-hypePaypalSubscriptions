<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Hook;
use hypeJunction\Payments\Transaction;
use hypeJunction\Subscriptions\Paypal\PaypalRecurringPaymentGateway;
use hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService;
use PayPal\Api\Agreement;

class DigestPaymentWebhook {

	/**
	 * Digest charge webhook and update transaction status
	 *
	 * @param Hook $hook Hook
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function __invoke(Hook $hook) {

		elgg_call(ELGG_IGNORE_ACCESS | ELGG_SHOW_DISABLED_ENTITIES, function () use ($hook) {
			$data = $hook->getParam('data');

			$agreement_id = $data->resource->billing_agreement_id;

			if (!$agreement_id) {
				return;
			}

			$paypal = elgg()->paypal;
			/* @var $paypal PaypalClient */

			$agreement = Agreement::get($agreement_id, $paypal->getApiContext());


			$gateway = elgg()->{'subscriptions.paypal'};

			/* @var $gateway PaypalSubscriptionsService */

			return $gateway->importAgreement($agreement);
		});
	}
}