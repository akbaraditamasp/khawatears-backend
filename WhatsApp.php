<?php
namespace WhatsApp;

use Error;

function OrderTemplate($data, $address)
{
    $text = "Halo Kak %f_name%!
Terima kasih sudah memesan produknya Khawatears ya, berikut detail pesanan kakak:

No. Transaksi:
%id%

Nama:
%name%

No. Whatsapp:
%number%

Alamat:
%address%

Pengiriman:
%shipping%

Total Bayar:
%total%

Untuk pembayaran dan detail pemesanan, kakak bisa buka link berikut
https://store.khawatears.com/order/%id%";

    $text = str_replace("%id%", $data["identifier"], $text);
    $text = str_replace("%f_name%", (explode(" ", $data["customer_name"])[0]), $text);
    $text = str_replace("%name%", $data["customer_name"], $text);
    $text = str_replace("%number%", $data["customer_whatsapp"], $text);
    $text = str_replace("%address%", $address, $text);
    $text = str_replace("%total%", "Rp" . number_format($data["total"], 0, ".", ","), $text);

    if ($data["information"]["shipping"] !== "COD") {
        $text = str_replace("%shipping%", strtoupper($data["information"]["shipping"] . " - " . $data["information"]["shipping_service"]), $text);
    } else {
        $text = str_replace("%shipping%", $data["information"]["shipping"], $text);
    }

    return $text;
}

function Send($phone, $message)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    $phone = preg_replace("/^08/", "628", $phone);

    $payload = json_encode([
        "messageType" => "text",
        "phone" => $phone,
        "body" => $message,
    ]);
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_URL => "https://sendtalk-api.taptalk.io/api/v1/message/send_whatsapp",
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_HTTPHEADER => array(
            "API-Key: " . $_ENV["WHATSAPP_KEY"],
            "Content-Type: application/json",
        ),
        CURLOPT_POSTFIELDS => $payload,
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        throw new Error($err);
    }
}
