<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Event;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Plan;
use Psr\Log\LogLevel;

class OnUpdateEvent {

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

		try {
			$subs = elgg()->{'subscriptions.paypal'};
			/* @var $subs \hypeJunction\Subscriptions\Paypal\PaypalSubscriptionsService */

			if (!$subs->exportPlan($entity)) {
				throw new \RuntimeException('Unable to export plan to PayPal. You may need to manually update it');
			}
		} catch (\Exception $ex) {
			elgg_log($ex, LogLevel::ERROR);
			register_error($ex->getMessage());
		}

	}
}