<?php

namespace hypeJunction\Subscriptions\Paypal;

use hypeJunction\Payments\Amount;
use hypeJunction\Paypal\PaypalClient;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Agreement;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Plan;
use PayPal\Common\PayPalModel;

class PaypalSubscriptionsService {

	/**
	 * @var PaypalClient
	 */
	protected $client;

	/**
	 * Constructor
	 *
	 * @param PaypalClient $client Client
	 */
	public function __construct(PaypalClient $client) {
		$this->client = $client;
	}

	/**
	 * Import plan
	 *
	 * @param Plan $plan Plan
	 *
	 * @return SubscriptionPlan|false
	 * @throws \Exception
	 */
	public function importPlan(Plan $plan) {

		return elgg_call(ELGG_IGNORE_ACCESS | ELGG_SHOW_DISABLED_ENTITIES, function () use ($plan) {
			$entities = elgg_get_entities([
				'types' => 'object',
				'subtypes' => SubscriptionPlan::SUBTYPE,
				'metadata_name_value_pairs' => [
					'paypal_id' => $plan->id,
				],
				'limit' => 1,
			]);

			if (empty($entities)) {
				$entity = new SubscriptionPlan();
				$entity->container_guid = elgg_get_site_entity()->guid;
				$entity->access_id = ACCESS_PUBLIC;
			} else {
				$entity = array_shift($entities);
			}

			$entity->title = $plan->description;

			$entity->paypal_id = $plan->id;

			$entity->setPlanId($plan->name);

			foreach ($plan->payment_definitions as $definition) {
				/* @var $definition PaymentDefinition */

				switch ($definition->type) {
					case 'REGULAR' :
						$frequencies = [
							'DAY' => 'day',
							'WEEK' => 'week',
							'MONTH' => 'month',
							'YEAR' => 'year',
						];

						$entity->interval = $frequencies[$definition->frequency];
						$entity->interval_count = $definition->frequency_interval;

						$amount = Amount::fromString($definition->amount->value, $definition->amount->currency);
						$entity->setPrice($amount);
						break;

					case 'TRIAL' :
						$frequencies = [
							'DAY' => 1,
							'WEEK' => 7,
							'MONTH' => 31,
							'YEAR' => 365,
						];

						$entity->trial_period_days = $frequencies[$definition->frequency] * $definition->frequency_interval * $definition->cycles;
						break;
				}
			}


			$entity->setVolatileData('is_import', true);

			if (!$entity->save()) {
				return false;
			}

			return $entity;
		});

	}

	/**
	 * Create/update a plan
	 *
	 * @param SubscriptionPlan $plan Plan
	 *
	 * @return SubscriptionPlan|false
	 * @throws \Exception
	 */
	public function exportPlan(SubscriptionPlan $plan) {
		return elgg_call(ELGG_IGNORE_ACCESS, function () use ($plan) {
			if ($plan->paypal_id) {
				$paypal_plan = Plan::get($plan->paypal_id, $this->client->getApiContext());

				if ($paypal_plan->getState() !== 'ACTIVE') {
					$patch = new Patch();

					$value = new PayPalModel(json_encode(['state' => 'ACTIVE']));

					$patch->setOp('replace')
						->setPath('/')
						->setValue($value);

					$patchRequest = new PatchRequest();
					$patchRequest->addPatch($patch);

					$paypal_plan->update($patchRequest, $this->client->getApiContext());
				}

				return $plan;
			} else {
				$paypal_plan = new \PayPal\Api\Plan();

				$paypal_plan->setName($plan->plan_id)
					->setDescription($plan->title)
					->setType('INFINITE');

				$paymentDefinition = new PaymentDefinition();
				$paymentDefinition->setName('Subscription')
					->setType('REGULAR')
					->setFrequency(strtoupper($plan->interval))
					->setFrequencyInterval($plan->interval_count)
					->setCycles(0)
					->setAmount(new Currency([
						'value' => $plan->getPrice()->getConvertedAmount(),
						'currency' => $plan->getPrice()->getCurrency(),
					]));

				$definitions = [$paymentDefinition];

				if ($plan->trial_period_days) {
					$trialDefinition = new PaymentDefinition();
					$trialDefinition->setName('Trial')
						->setType('TRIAL')
						->setFrequency('DAY')
						->setFrequencyInterval($plan->trial_period_days)
						->setCycles(1)
						->setAmount(new Currency([
							'value' => 0,
							'currency' => $plan->getPrice()->getCurrency(),
						]));

					$definitions[] = $trialDefinition;
				}

				$preferences = new MerchantPreferences();
				$preferences->setCancelUrl(elgg_normalize_url('settings/subscriptions'));
				$preferences->setReturnUrl(elgg_normalize_url('settings/subscriptions'));

				$paypal_plan->setPaymentDefinitions($definitions);
				$paypal_plan->setMerchantPreferences($preferences);

				try {
					$paypal_plan = $paypal_plan->create($this->client->getApiContext());
				} catch (\Exception $ex) {
					return false;
				}

			}

			$plan->paypal_id = $paypal_plan->id;

			return $plan;

		});
	}

	/**
	 * Import paypal subscription
	 *
	 * @param Agreement $agreement Subscription
	 *
	 * @return \hypeJunction\Subscriptions\Subscription|false
	 * @throws \Exception
	 */
	public function importAgreement(Agreement $agreement) {

		return elgg_call(ELGG_IGNORE_ACCESS | ELGG_SHOW_DISABLED_ENTITIES, function () use ($agreement) {

			$entities = elgg_get_entities([
				'types' => 'object',
				'subtypes' => \hypeJunction\Subscriptions\Subscription::SUBTYPE,
				'metadata_name_value_pairs' => [
					'paypal_id' => $agreement->id,
				],
				'limit' => 1,
			]);

			$entity = false;

			if ($entities) {
				$entity = $entities[0];
			} else {
				try {
					$plans = elgg_get_entities([
						'types' => 'object',
						'subtypes' => \hypeJunction\Subscriptions\SubscriptionPlan::SUBTYPE,
						'metadata_name_value_pairs' => [
							'paypal_id' => $agreement->plan->id,
						],
						'limit' => 1,
					]);

					$plan = array_shift($plans);

					$email = $agreement->payer->payer_info->email;
					$users = get_user_by_email($email);

					if ($plan instanceof SubscriptionPlan && $users) {
						$user = array_shift($users);

						$dt = new \DateTime($agreement->getAgreementDetails()->next_billing_date, new \DateTimeZone('UTC'));

						$entity = $plan->subscribe($user, $dt->getTimestamp());
					}
				} catch (\Exception $ex) {

				}
			}

			if (!$entity) {
				return true;
			}

			switch (strtoupper($agreement->getState())) {
				case 'ACTIVE' :
				case 'PENDING' :
					if ($agreement->getAgreementDetails()->final_payment_date) {
						$dt = new \DateTime($agreement->getAgreementDetails()->final_payment_date, new \DateTimeZone('UTC'));
						$entity->current_period_end = $dt->getTimestamp();
					} else {
						$dt = new \DateTime($agreement->getAgreementDetails()->next_payment_date, new \DateTimeZone('UTC'));
						if ($dt->getTimestamp() > time()) {
							$entity->current_period_end = $dt->getTimestamp();
						}
					}
					break;

				case 'EXPIRED' :
					$entity->cancelled_at = time();
					$dt = new \DateTime($agreement->getAgreementDetails()->final_payment_date, new \DateTimeZone('UTC'));
					$entity->current_period_end = $dt->getTimestamp();
					break;

			}

			$entity->paypal_id = $agreement->id;

			return $entity;
		});
	}
}