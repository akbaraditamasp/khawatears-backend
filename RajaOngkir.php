<?php

class RajaOngkir
{
    private $curl;
    public function __construct()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "key: " . $_ENV["RAJAONGKIR_KEY"],
            ),
        ));

        $this->curl = $curl;
    }

    private function isValid($address, $data, $key, $extra = null)
    {
        $returning = null;
        foreach ($data as $check) {
            $from = strtolower($address);
            $to = strtolower($check[$key]);
            if ($extra) {
                if ($check[$extra["key"]] != $extra["value"]) {
                    continue;
                }
            }
            if (preg_match("/$to/", $from)) {
                $returning = $check;
                break;
            }
        }

        if ($returning) {
            return $returning;
        }

        return false;
    }

    private function getProvince()
    {
        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $_ENV["RAJAONGKIR_URL"] . "/api/province"));

        $response = curl_exec($this->curl);
        $err = curl_error($this->curl);

        curl_close($this->curl);

        if ($err) {
            throw new Error($err);
        } else {
            return (json_decode($response, true))["rajaongkir"]["results"];
        }
    }

    private function getCity($province)
    {
        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $_ENV["RAJAONGKIR_URL"] . "/api/city?province=$province"));

        $response = curl_exec($this->curl);
        $err = curl_error($this->curl);

        curl_close($this->curl);

        if ($err) {
            throw new Error($err);
        } else {
            return (json_decode($response, true))["rajaongkir"]["results"];
        }
    }

    private function getSubdistrict($city)
    {
        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $_ENV["RAJAONGKIR_URL"] . "/api/subdistrict?city=$city"));

        $response = curl_exec($this->curl);
        $err = curl_error($this->curl);

        curl_close($this->curl);

        if ($err) {
            throw new Error($err);
        } else {
            return (json_decode($response, true))["rajaongkir"]["results"];
        }
    }

    public function getCost($shipping, $destination, $weight, $service)
    {
        $origin = $_ENV["RAJAONGKIR_ORIGIN"];
        $shipping = strtolower($shipping);

        curl_setopt_array($this->curl, array(
            CURLOPT_URL => $_ENV["RAJAONGKIR_URL"] . "/api/cost",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "origin=$origin&originType=city&destination=$destination&destinationType=subdistrict&weight=$weight&courier=$shipping",
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded",
                "key: " . $_ENV["RAJAONGKIR_KEY"],
            ),
        ));

        $response = curl_exec($this->curl);
        $err = curl_error($this->curl);

        curl_close($this->curl);

        if ($err) {
            throw new Error($err);
        } else {
            $response = (json_decode($response, true))["rajaongkir"]["results"];
        }

        $cost_total = 0;
        foreach ($response[0]["costs"] as $cost) {
            if ($cost["service"] == $service) {
                $cost_total = $cost["cost"][0]["value"];
                break;
            }
        }

        return $cost_total;
    }

    public function checkAddress($address)
    {
        $all_province = $this->getProvince();
        $province = $this->isValid($address["province"], $all_province, "province");

        if (!$province) {
            return false;
        }

        $all_city = $this->getCity($province["province_id"]);
        $city = $this->isValid($address["city"]["name"], $all_city, "city_name", ["key" => "type", "value" => $address["city"]["type"]]);

        if (!$city) {
            return false;
        }

        $all_subdistrict = $this->getSubdistrict($city["city_id"]);
        $subdistrict = $this->isValid($address["subdistrict"], $all_subdistrict, "subdistrict_name");

        return [
            "full_address" => $address["detail"] . ", Kec. " . $subdistrict["subdistrict_name"] . ", " . $subdistrict["type"] . " " . $subdistrict["city"] . ", " . $subdistrict["province"] . " - " . $address["postal_code"],
            "subdistrict_id" => $subdistrict["subdistrict_id"],
        ];
    }

    public function getCourier()
    {
        $courier_exp = explode("|", $_ENV["RAJAONGKIR_SHIPPING_ALLOWED"]);
        $courier = [];
        foreach ($courier_exp as $item) {
            $shipping_exp = explode(":", $item);
            $shipping = $shipping_exp[0];
            $courier[$shipping] = explode(",", $shipping_exp[1]);
        }

        return $courier;
    }
}
