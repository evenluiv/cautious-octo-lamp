<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;

require_once __DIR__ . '/../vendor/autoload.php';

$validApiKeys = ['your_api_key_here', 'asd123'];

$logFilePath = __DIR__ . '/crawl_errors.log';

function validateApiKey($apiKey)
{
    global $validApiKeys;
    return in_array($apiKey, $validApiKeys);
}

function logError($message, $logFilePath)
{
    $errorMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFilePath, $errorMessage, FILE_APPEND);
}

function crawlProductsFromApi($apiUrl, $client)
{
    $products = [];
    $i = 1;

    if (!$client) {
        $client = new Client();
    }

    try {
        $response = $client->request('GET', $apiUrl);
        $jsonData = json_decode($response->getBody()->getContents(), true);

        if (isset($jsonData['hits']['hits'])) {
            foreach ($jsonData['hits']['hits'] as $productData) {
                $id = $i;
                $productName = $productData['_source']['name'] ?? '';
                $sku = $productData['_source']['sku'] ?? '';
                $originalPrice = floatval($productData['_source']['originalPriceInclTax'] ?? 0);
                $price = floatval($productData['_source']['priceInclTax'] ?? 0);
                $specialPrice = floatval($productData['_source']['specialPriceInclTax'] ?? 0);
                $discount = $originalPrice - $price;
                $urlPath = $productData['_source']['url_path'] ?? '';

                // Add each product to the array
                $products[] = [
                    'id' => $id,
                    'name' => $productName,
                    'sku' => $sku,
                    'original_price' => $originalPrice,
                    'price' => $price,
                    'special_price' => $specialPrice,
                    'discount' => $discount,
                    'url_path' => $urlPath
                ];
                $i++;
            }
        }
    } catch (RequestException $e) {
        logError('Failed to fetch products from API: ' . $e->getMessage(), __DIR__ . '/crawl_errors.log');
    }

    return $products;
}

function extractCategories($url, $client)
{
    $categories = [];

    try {
        $response = $client->request('GET', $url);
        $html = $response->getBody()->getContents();

        $crawler = new Crawler($html);
        
        $crawler->filter('a')->each(function (Crawler $node) use (&$categories) {
            $link = $node->text();
            $href = $node->attr('href');

            $categoryId = "";

            switch ($link) {
                case 'Telefonid':
                    $categoryId = "8";
                    break;
                case "Sülearvutid":
                    $categoryId = "5";
                    break;
                case "Telerid":
                    $categoryId = "100";
                    break;
                case "Kõrvaklapid":
                    $categoryId = "51,209,210,304,89,98";
                    break;
                case "Nutikellad":
                    $categoryId = "52";
                    break;
                case "Mängurile":
                    $categoryId = "468,469,470,471,472,473,474,475,476,576,577,633,634,635,636,637,638,639,640,641,642";
                    break;
                case "Robotid":
                    $categoryId = "162";
                    break;
                case "Apple":
                    $categoryId = "709";
                    break;
                default:
                    return;
                    break;
            }

            $categories[] = [
                'categoryId' => $categoryId,
                'categoryName' => $link,
                'href' => $href
            ];
        });

    } catch (Exception $e) {
        logError('Failed to extract categories: ' . $e->getMessage(), __DIR__ . '/crawl_errors.log');
    }

    return $categories;
}

function crawlEStore($url, $client = null, $logFilePath = null)
{
    if (!$client) {
        $client = new Client();
    }

    $categories = extractCategories($url, $client);


    $allProducts = [];

    foreach ($categories as $category) {
        $categoryId = $category['categoryId'];
        $categoryName = $category['categoryName'];
        $categoryHref = $category['href'];

        $apiUrl = "https://vsf-api.klick.ee/api/catalog/vue_storefront_catalog_klick/product/_search?_source_exclude=description%2Csgn%2C%2A.sgn%2Cmsrp_display_actual_price_type%2C%2A.msrp_display_actual_price_type%2Crequired_options&_source_include=type_id%2Csku%2Cconfigurable_options%2Cproduct_links%2Ctax_class_id%2Cspecial_price%2Cspecial_to_date%2Cspecial_from_date%2Cname%2Cprice%2CpriceInclTax%2CoriginalPriceInclTax%2CoriginalPrice%2CspecialPriceInclTax%2Cid%2Cimage%2Csale%2Cnew%2Curl_path%2Curl_key%2Cstatus%2Ctier_prices%2Cconfigurable_children.sku%2Cconfigurable_children.price%2Cconfigurable_children.special_price%2Cconfigurable_children.priceInclTax%2Cconfigurable_children.specialPriceInclTax%2Cconfigurable_children.originalPrice%2Cconfigurable_children.originalPriceInclTax%2CofferNo%2Cmin_month_payment%2Ccategory_ids%2Cnav_campaign&from=0&request=%7B%22query%22%3A%7B%22bool%22%3A%7B%22filter%22%3A%7B%22bool%22%3A%7B%22must%22%3A%5B%7B%22terms%22%3A%7B%22visibility%22%3A%5B2%2C3%2C4%5D%7D%7D%2C%7B%22terms%22%3A%7B%22status%22%3A%5B0%2C1%5D%7D%7D%2C%7B%22terms%22%3A%7B%22category_ids%22%3A%5B$categoryId%5D%7D%7D%5D%7D%7D%7D%7D%7D&size=1000&sort=final_price";

        $products = crawlProductsFromApi($apiUrl, $client);

        
        $allProducts[] = [
            'category' => $categoryName,
            'href' => $categoryHref,
            'products' => $products
        ];
    }

    return $allProducts;
}

if (php_sapi_name() !== 'cli') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['url']) && isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];

        if (!validateApiKey($apiKey)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid API Key']);
            exit;
        }

        $result = crawlEStore($_GET['url'], null, $logFilePath);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request. Please provide url and api_key']);
        exit;
    }
}
