<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Event;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Plan;

class OnDeleteEvent {

	/**
	 * Sync plan updates
	 *
	 * @param Event $event Event
	 *
	 * @return bool|null
	 */
	public function __invoke(Event $event) {

		$entity = $event->getObject();
		if (!$entity instanceof SubscriptionPlan) {
			return null;
		}

		if ($entity->getVolatileData('is_import')) {
			return null;
		}

		$paypal = elgg()->paypal;
		/* @var $paypal \hypeJunction\Paypal\PaypalClient */

		try {
			if ($entity->paypal_id) {
				$plan = Plan::get($entity->paypal_id, $paypal->getApiContext());
				$plan->delete($paypal->getApiContext());
			}
		} catch (\Exception $ex) {

		}

	}
}