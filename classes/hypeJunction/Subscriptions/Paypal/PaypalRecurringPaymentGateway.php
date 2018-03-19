<?php

namespace hypeJunction\Subscriptions\Paypal;

use Elgg\Http\ResponseBuilder;
use ElggUser;
use hypeJunction\Payments\Amount;
use hypeJunction\Paypal\PaypalGateway;
use hypeJunction\Subscriptions\RecurringPaymentGatewayInterface;
use hypeJunction\Subscriptions\Subscription;
use hypeJunction\Subscriptions\SubscriptionPlan;
use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\Payer;
use PayPal\Api\RefundRequest;
use PayPal\Api\Sale;

class PaypalRecurringPaymentGateway extends PaypalGateway implements RecurringPaymentGatewayInterface {

	/**
	 * Create an agreement
	 *
	 * @param SubscriptionPlan $plan   Subscription plan
	 * @param array            $params Request params
	 *
	 * @return Agreement|false
	 */
	public function createAgreement(SubscriptionPlan $plan, array $params = []) {
		$date = new \DateTime('+2 minutes', new \DateTimeZone('UTC'));

		$agreement = new Agreement();
		$agreement->setName('Base Agreement')
			->setDescription('Basic Agreement')
			->setStartDate($date->format('Y-m-d\TH:i:s\Z'));

		$paypal_plan = new \PayPal\Api\Plan();
		$paypal_plan->setId($plan->paypal_id);
		$agreement->setPlan($paypal_plan);

		$payer = new Payer();
		$payer->setPaymentMethod('paypal');
		$agreement->setPayer($payer);

		try {
			$agreement->create($this->client->getApiContext());
			return $agreement;
		} catch (\Exception $ex) {
			elgg_log($ex->getMessage(), 'ERROR');
			return false;
		}
	}

	/**
	 * Start a recurring payment
	 *
	 * @param ElggUser         $user   User
	 * @param SubscriptionPlan $plan   Plan
	 * @param array            $params Request parameters
	 *
	 * @return ResponseBuilder
	 */
	public function subscribe(ElggUser $user, SubscriptionPlan $plan, array $params = []) {
		try {
			$payment_id = elgg_extract('paypal_payment_id', $params);

			$agreement = new \PayPal\Api\Agreement();
			$agreement->execute($payment_id, $this->client->getApiContext());

			if (strtoupper($agreement->getState()) == 'ACTIVE' && $record = $plan->subscribe($user)) {

				$record->paypal_id = $agreement->id;

				$agreement = Agreement::get($agreement->id, $this->client->getApiContext());

				$svc = elgg()->{'subscriptions.paypal'};
				/* @var $svc PaypalSubscriptionsService */

				$svc->importAgreement($agreement);

				return elgg_ok_response([
					'user' => $user,
					'subscription' => $record,
				], elgg_echo('subscriptions:subscribe:paypal:success', [$plan->getDisplayName()]));
			}

		} catch (\Exception $ex) {
			return elgg_error_response($ex->getMessage(), REFERRER, $ex->getCode() ? : ELGG_HTTP_INTERNAL_SERVER_ERROR);
		}

		return elgg_error_response(elgg_echo('subscriptions:subscribe:error'), REFERRER, ELGG_HTTP_INTERNAL_SERVER_ERROR);
	}

	/**
	 * Cancel subscription
	 *
	 * @param Subscription $subscription Subscription
	 * @param array        $params       Request parameters
	 *
	 * @return bool
	 */
	public function cancel(Subscription $subscription, array $params = []) {

		$at_period_end = elgg_extract('at_period_end', $params, true);

		try {

			$agreement = Agreement::get($subscription->paypal_id, $this->client->getApiContext());

			if (!$at_period_end) {
				$time = new \DateTime('now', new \DateTimeZone('UTC'));
				$used = $time->getTimestamp() - $agreement->current_period_start;

				$end = new \DateTime($agreement->getAgreementDetails()->next_billing_date, new \DateTimeZone('UTC'));
				$start = new \DateTime($agreement->getAgreementDetails()->last_payment_date, new \DateTimeZone('UTC'));

				$duration = $end->getTimestamp() - $start->getTimestamp();

				$transactions = Agreement::searchTransactions($agreement->id, $this->client->getApiContext());
				$transaction = array_shift($transactions->agreement_transaction_list);

				if ($agreement->getAgreementDetails()->getLastPaymentAmount()) {
					$amount = $agreement->getAgreementDetails()->getLastPaymentAmount()->getValue();
					$currency = $agreement->getAgreementDetails()->getLastPaymentAmount()->getCurrency();

					$amount = Amount::fromString($amount, $currency);
				} else if ($transaction) {
					$amount = Amount::fromString($transaction->getAmount()->getValue(), $transaction->getAmount()->getCurrency());
				} else {
					$amount = new Amount(0);
				}

				$refund = $amount->getAmount() - round(($used / $duration) * $amount->getAmount());

				if ($refund > 0) {
					try {
						$refund = new Amount($refund, $amount->getCurrency());


						if ($transaction) {
							$sale = Sale::get($transaction->id);

							$refundAmount = new \PayPal\Api\Amount();
							$refundAmount->setTotal($refund->getConvertedAmount());
							$refundAmount->setCurrency($refund->getCurrency());

							$refundRequest = new RefundRequest();
							$refundRequest->setAmount($refundAmount);

							$sale->refundSale($refundRequest, $this->client->getApiContext());

							system_message(elgg_echo('subscriptions:paypal:refunded', [
								$amount->getConvertedAmount(),
								$amount->getCurrency()
							]));
						}
					} catch (\Exception $ex) {
						elgg_log($ex->getMessage(), 'ERROR');
					}
				}
			}

			$desc = new AgreementStateDescriptor();
			$desc->setNote('Cancelled');

			$agreement->cancel($desc);

			return true;
		} catch (\Exception $ex) {
			elgg_log($ex->getMessage(), 'ERROR');

			return false;
		}
	}
}