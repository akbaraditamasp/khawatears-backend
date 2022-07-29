<?php
namespace Controller;

use function App\Db;
use function App\JSON;
use function App\Post;
use function App\PrepareIdentifier;
use function App\Validate;
use RajaOngkir;
use XenditLib;

class Order
{
    public static function callback()
    {
        $token = "";
        if (isset($_SERVER['x-callback-token'])) {
            $token = trim($_SERVER["x-callback-token"]);
        } else if (isset($_SERVER['HTTP_X_CALLBACK_TOKEN'])) {
            $token = trim($_SERVER["HTTP_X_CALLBACK_TOKEN"]);
        } else if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['x-callback-token'])) {
                $token = trim($requestHeaders['x-callback-token']);
            }
        }

        if ($token !== $_ENV["XENDIT_CALLBACK_TOKEN"]) {
            JSON([], 401);
        }

        Validate(($_POST ? $_POST : []), [
            "id" => "required",
        ]);

        $data = XenditLib::getOrder(Post("id"));

        if ($data) {
            if ($data["status"] && $data["external_id"]) {
                if ($data["status"] == "PAID" || $data["status"] == "SETTLED") {
                    $db = Db();
                    $db->update("orders", [
                        "is_paid" => true,
                    ], [
                        "identifier" => $data["external_id"],
                    ]);
                    JSON($data);
                }
            }
        }
        JSON([]);
    }

    public static function getCart()
    {
        Validate($_POST, [
            "carts" => "required|array",
            "carts.*.id" => "required|numeric",
            "carts.*.qty" => "required|numeric|min:1",
        ]);

        $db = Db();

        $ids = [];

        foreach (Post("carts") as $item) {
            if (!in_array($item["id"], $ids)) {
                $ids[] = $item["id"];
            }
        }

        $products = $db->select("products", ["id [Int]", "product_name", "price [Int]"], ["id" => $ids]);

        $carts = [];

        foreach (Post("carts") as $val) {
            $product = array_filter($products, function ($item) use ($val) {return $val["id"] == $item["id"];});

            if (count($product)) {
                $product = (array_values($product))[0];
            } else {
                continue;
            }

            $carts[] = [
                "id" => $product["id"],
                "qty" => (int) $val["qty"],
                "message" => $val["message"],
                "product" => $product,
            ];
        }

        JSON($carts);
    }

    public static function create()
    {
        $rajaongkir = new RajaOngkir();
        $shipping_co = $rajaongkir->getCourier();
        $shipping = implode(",", [...array_keys($shipping_co), "COD"]);

        Validate($_POST, [
            "customer_name" => "required",
            "customer_whatsapp" => "required",
            "carts" => "required|array",
            "carts.*.id" => "required|numeric",
            "carts.*.qty" => "required|numeric|min:1",
            "information" => "required|array",
            "information.shipping" => "required|in:$shipping",
            "preview" => "boolean",
            "information.address" => "required|array",
            "information.address.province" => "required",
            "information.address.city.name" => "required",
            "information.address.city.type" => "required|in:Kabupaten,Kota",
            "information.address.subdistrict" => "required",
            "information.address.detail" => "required",
            "information.address.postal_code" => "required",
        ]);

        $information = Post("information");
        $address = $information["address"];
        $valid_address = $rajaongkir->checkAddress($address);

        $total = 0;
        $shipping_weight = 0;

        $db = Db();

        $ids = [];

        foreach (Post("carts") as $item) {
            if (!in_array($item["id"], $ids)) {
                $ids[] = $item["id"];
            }
        }

        $products = $db->select("products", ["id [Int]", "product_name", "price [Int]"], ["id" => $ids]);

        $data = [
            "customer_name" => Post("customer_name"),
            "customer_whatsapp" => Post("customer_whatsapp"),
            "is_paid" => false,
        ];
        $details = [];

        foreach (Post("carts") as $val) {
            $product = array_filter($products, function ($item) use ($val) {return $val["id"] == $item["id"];});

            if (count($product)) {
                $product = (array_values($product))[0];
            } else {
                continue;
            }

            $details[] = [
                "product_id" => $product["id"],
                "product_name" => $product["product_name"],
                "product_price" => $product["price"],
                "qty" => (int) $val["qty"],
                "message" => $val["message"],
            ];

            $total += ((int) $val["qty"]) * $product["price"];
            $shipping_weight += 250 * ((int) $val["qty"]);
        }

        if ($information["shipping"] != "COD") {
            $shipping_selected = $information["shipping"];
            $services = implode(",", $shipping_co[$shipping_selected]);

            Validate($information, [
                "shipping_service" => "required|in:$services",
            ]);

            if (!$valid_address) {
                JSON(["error" => "Alamat tidak valid"], 400);
            }

            $cost = $rajaongkir->getCost($information["shipping"], $valid_address["subdistrict_id"], $shipping_weight, $information["shipping_service"]);

            $total += $cost;
            $information["shipping_cost"] = $cost;
            $information["shipping_weight"] = $shipping_weight;
        }

        $data["total"] = $total;

        if (!Post("preview")) {
            $data["identifier"] = PrepareIdentifier($db);
            $url = (new XenditLib())->createInvoice($data + ["information" => $information], $details);
            $data["payment_link"] = $url;

            $db->insert("orders", $data + ["information [JSON]" => $information]);
            $id = $db->id();

            $data["id"] = (int) $id;

            $details = array_map(function ($val) use ($id) {
                return $val + ["order_id" => $id];
            }, $details);

            $db->insert("order_details", $details);

            \WhatsApp\Send($data["customer_whatsapp"], \WhatsApp\OrderTemplate($data + ["information" => $information], $valid_address["full_address"]));
        }

        JSON($data + [
            "information" => $information,
            "order_details" => $details,
        ]);
    }

    public static function getCourier()
    {
        echo json_encode((new RajaOngkir)->getCourier());
    }

    public static function getByIdentifier($req)
    {
        $identifier = $req["params"]["identifier"];

        $db = Db();

        $data = $db->get("orders", [
            "id [Int]",
            "identifier",
            "created_at",
            "customer_name",
            "customer_whatsapp",
            "information [JSON]",
            "total [Int]",
            "payment_link",
            "is_paid [Bool]",
        ], ["identifier" => $identifier]);

        if (!$data) {
            JSON(["error" => "Transaksi tidak ditemukan", 404]);
        }

        $details = $db->select("order_details", [
            "id [Int]",
            "order_id [Int]",
            "product_id [Int]",
            "product_name",
            "product_price [Int]",
            "qty [Int]",
            "message",
        ], [
            "order_id" => $data["id"],
        ]);

        JSON($data + ["details" => $details]);
    }

    public static function checkAddress()
    {
        Validate($_POST, [
            "address" => "required|array",
            "address.province" => "required",
            "address.city.name" => "required",
            "address.city.type" => "required|in:Kabupaten,Kota",
            "address.subdistrict" => "required",
            "address.detail" => "required",
            "address.postal_code" => "required",
        ]);

        $rajaongkir = new RajaOngkir();

        if (!$rajaongkir->checkAddress(Post("address"))) {
            JSON(["error" => "Alamat Tidak Valid"], 400);
        }

        JSON([]);
    }
}
