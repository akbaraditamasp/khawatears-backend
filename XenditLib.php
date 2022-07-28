<?php

use \Xendit\Invoice;
use \Xendit\Xendit;

class XenditLib
{
    public function __construct()
    {
        Xendit::setApiKey($_ENV["XENDIT_KEY"]);
    }

    public function createInvoice($data, $items)
    {
        $params = [
            'external_id' => $data["identifier"],
            'amount' => $data["total"],
            'invoice_duration' => 86400,
            'customer' => [
                'given_names' => $data["customer_name"],
            ],
            'success_redirect_url' => $_ENV["AFTER_PAY_URL"] . $data["identifier"],
            'failure_redirect_url' => $_ENV["AFTER_PAY_URL"] . $data["identifier"],
            'currency' => 'IDR',
            'items' => array_map(function ($val) {
                return [
                    "name" => $val["product_name"],
                    "price" => $val["product_price"],
                    "quantity" => $val["qty"],
                ];
            }, $items),
        ];

        if (isset($data["information"]["shipping_cost"])) {
            $params["fees"] = [
                [
                    "type" => "Ongkir",
                    "value" => $data["information"]["shipping_cost"],
                ],
            ];
        }

        $createInvoice = Invoice::create($params);
        return $createInvoice["invoice_url"];
    }

    public static function getOrder($id)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_URL => "https://api.xendit.co/v2/invoices/$id",
            CURLOPT_USERPWD => $_ENV["XENDIT_KEY"] . ":",
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new Error($err);
        } else {
            return json_decode($response, true);
        }
    }
}
