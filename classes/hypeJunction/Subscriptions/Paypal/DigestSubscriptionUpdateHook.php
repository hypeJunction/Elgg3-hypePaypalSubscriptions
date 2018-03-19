<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Hook;
use hypeJunction\Paypal\PaypalClient;
use hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService;
use PayPal\Api\Agreement;

class DigestSubscriptionUpdateHook {

	/**
	 * Digest plan created web hook
	 *
	 * @param Hook $hook Hook
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function __invoke(Hook $hook) {

		$data = $hook->getParam('data');

		try {

			$paypal = elgg()->paypal;
			/* @var $paypal PaypalClient */

			$svc = elgg()->{'subscriptions.paypal'};

			/* @var $svc PaypalSubscriptionsService */

			$agreement = Agreement::get($data->resource->id, $paypal->getApiContext());

			return $svc->importAgreement($agreement);
		} catch (\Exception $ex) {
			elgg_log($ex->getMessage(), 'ERROR');

			return false;
		}
	}
}