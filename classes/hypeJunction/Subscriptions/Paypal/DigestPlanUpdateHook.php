<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Hook;
use hypeJunction\Paypal\PaypalClient;
use PayPal\Api\Plan;

class DigestPlanUpdateHook {

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

		$paypal = elgg()->paypal;
		/* @var $paypal PaypalClient */

		$svc = elgg()->{'subscriptions.paypal'};

		/* @var $svc \hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService */

		try {
			$plan = Plan::get($data->resource->id, $paypal->getApiContext());
			return $svc->importPlan($plan);
		} catch (\Exception $ex) {
			elgg_log($ex->getMessage(), 'ERROR');
			throw new \Elgg\HttpException($ex->getMessage(), $ex->getCode());
		}
	}
}