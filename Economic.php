<?php

namespace App\Services\Integration\Economic;

use App\Models\Order;
use Exception;
use stdClass;
use DateTime;

class Economic
{
    /** @var Api */
    protected $api;

    /** @var Order */
    protected $order;

    protected $refundOrder;

    /** @var array */
    protected $data;

    public function __construct($refundOrder, Order $order, Api $api, array $data)
    {
        $this->api = $api;
        $this->order = $order;
        $this->refundOrder = $refundOrder;
        $this->data = $data;
    }

    /**
     * @throws Exception
     */
    public function sendInvoicesDrafts() : void
    {
        $customer = $this->getCustomer($this->order->customer_phone, $this->order->customer_email);
        $data = $this->formatData($customer->collection[0]);

        $response = $this->api->request(
            '/invoices/drafts',
            Api::POST,
            ['body' => $data]
        );

        $this->sendInvoicesBooked($response);
    }

    /**
     * @param stdClass $draft
     */
    protected function sendInvoicesBooked(stdClass $draft) : void
    {
        $data = $this->formatDataBooked($draft);

        $this->api->request(
            '/invoices/booked',
            Api::POST,
            ['body' => $data]
        );
    }

    /**
     * @param string $phone
     * @param string $email
     * @return mixed
     * @throws Exception
     */
    protected function getCustomer(string $phone, string $email)
    {
        $response = $this->api->request(
            'customers?filter=telephoneAndFaxNumber$eq:' . $phone . '$and:(email$eq:' . $email . ')',
            API::GET
        );

        if (empty($response->collection)) {
            throw new Exception('Can`t find customer.');
        }

        return $response;
    }

    /**
     * @param stdClass $number
     * @return false|string
     */
    protected function formatDataBooked(stdClass $number)
    {
        $data = [
            "draftInvoice" => [
                "draftInvoiceNumber"=> $number->draftInvoiceNumber
            ]
        ];

        return json_encode($data);
    }

    /**
     * @param $customer
     * @return false|string
     */
    protected function formatData($customer)
    {
        $date = new DateTime('now');
        $linesArray = [];
        $paymentTermsNumber = config("common.payment.payment_terms_number.{$this->order->pay_id}");

        $i = 1;
        $allSelectedLines = true;
        $lines = $this->data['lines'];

        foreach ($lines as $line) {
            if ((int)$line['qty'] > 0 && (bool)$line['isDiscount'] === false) {
                $linesArray[] = [
                    "lineNumber" => $i,
                    "sortKey" => $i,
                    "description" => $line['lineData']['product_name'],
                    "quantity" => $line['lineData']['quantity'],
                    "unitNetPrice" => $line['lineData']['unit_price'] * -1,
                    "totalNetAmount" => $line['lineData']['total_price'] * -1,
                    "product" => [
                        "productNumber" => $line['lineData']['product_number']
                    ]
                ];
                $i++;
            }

            if ($line['lineData']['unit_price'] >= 0 && $line['qty'] != $line['lineData']['quantity']) {
                $allSelectedLines = false;
            }
        }

        if ($allSelectedLines) {
            $linesArray[] = [
                "lineNumber" => $i,
                "sortKey" => $i,
                "description" => $this->order->shipping_name,
                "quantity" => 1,
                "unitNetPrice" => (float) number_format(($this->order->shipping_fee * 0.8) * -1, 2, '.', ''),
                "totalNetAmount" => (float) number_format(($this->order->shipping_fee * 0.8) * -1, 2, '.', ''),
                "product" => [
                    "productNumber" => "fragtinclmoms"
                ]
            ];
            $i++;
        }

        if (isset($this->refundOrder->shipping_pay)) {
            $linesArray[] = [
                "lineNumber" => $i,
                "sortKey" => $i,
                "description" => $this->order->shipping_name,
                "quantity" => 1,
                "unitNetPrice" => (float) number_format(($this->refundOrder->shipping_pay * 0.8), 2, '.', ''),
                "totalNetAmount" => (float) number_format(($this->refundOrder->shipping_pay * 0.8), 2, '.', ''),
                "product" => [
                    "productNumber" => "fragtinclmoms"
                ]
            ];
        }

        $data = [
            "date" => "{$date->format('Y-m-d')}",
            "currency" => "DKK",
            "paymentTerms" => [
                "paymentTermsNumber" => $paymentTermsNumber
            ],
            "customer" => [
                "customerNumber" => $customer->customerNumber
            ],
            "recipient" => [
                "name" => $customer->name,
                "address" => $customer->address,
                "zip" => $customer->zip,
                "city" => $customer->city,
                "country" => $customer->country,
                "vatZone" => [
                    "name" => "Domestic",
                    "vatZoneNumber" => 1,
                    "enabledForCustomer" => true,
                    "enabledForSupplier" => true
                ]
            ],
            "delivery" => [
                "address" => "address",
                "zip" => "zip",
                "city" => "city",
                "country" => "Danmark"
            ],
            "references" => [
                "other" => "{$this->order->id}"
            ],
            "layout" => [
                "layoutNumber" => 18
            ],
            "lines" => $linesArray
        ];

        return json_encode($data);
    }
}
