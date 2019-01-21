<?php
declare(strict_types=1);

$mimeDBtoFetch = "https://raw.githubusercontent.com/jshttp/mime-db/master/db.json";

if (!function_exists('curl_init')) {
    echo "CURL extension has to be enabled in your php.ini\n";
    exit(1);
}

echo "Fetching mime DB ...";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $mimeDBtoFetch,
    CURLOPT_BINARYTRANSFER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$data       = curl_exec($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if ($statusCode !== 200) {
    echo "\rFetching mime DB ... ERROR\n\nUnable to fetch mime db, got non 200 response\n";
    exit(2);
}

echo "\rFetching mime DB ... OK\n";

echo "Decoding DB ...";
$mimes = json_decode($data, true);

if (json_last_error()) {
    echo "\rDecoding DB ... ERROR\n\nUnable to decode fetched JSON, got error: " . json_last_error() . "(" . json_last_error_msg() . ")\n";
    exit(4);
}
echo "\rDecoding DB ... OK\n";

$extensions = [];

echo "\nPreparing mimes ...\n";
foreach ($mimes as $type => $typeData) {
    if (empty($typeData['extensions'])) {
        //        echo "{$type} has no associated extension\n";
        continue;
    }

    foreach ($typeData['extensions'] as $ext) {
        is_array($extensions[$ext] ?? null) ? $extensions[$ext][] = $type : $extensions[$ext] = [$type];
    }
}

ksort($mimes);
ksort($extensions);

echo "\rPreparing mimes ... OK\n";

$mimeType = file_get_contents("./src/MimeType.php");

$mimeType = preg_replace("~(.*// <-- mimes start --> \\\\)(.*)(// <-- mimes end --> \\\\.*)~si", "$1\n    private static \$mimes = " . mimesExporter($mimes) . ";\n$3", $mimeType);
$mimeType = preg_replace("~(.*// <-- extensions start --> \\\\)(.*)(// <-- extensions end --> \\\\.*)~si", "$1\n    private static \$extensions = " . extensionsExporter($extensions) . ";\n$3", $mimeType);

file_put_contents("./src/MimeType.php", $mimeType);

echo "\nProcessed " . count($mimes) . " mimetypes and " . count($extensions) . " extensions";
echo "\nDONE!";
exit(0);

##
#   END OF MAIN SCRIPT
##

function mimesExporter(array $array) :string {
    $result = "";

    foreach ($array as $key => $value) {
        $val = "";

        if (isset($value['compressible'])) {
            $val .= "        'compressible' => true,\n";
        }
        else {
            $val .= "        'compressible' => false,\n";
        }

        if (isset($value['source'])) {
            $val .= "        'source' => '" . addslashes($value['source']) . "',\n";
        }

        if (isset($value['charset'])) {
            $val .= "        'charset' => '" . addslashes($value['charset']) . "',\n";
        }

        if (isset($value['extensions'])) {
            $val .= "        'extensions' => ['" . implode("','", $value['extensions']) . "'],\n";
        }
        else {
            $val .= "        'extensions' => [],\n";
        }

        $result .= "    '{$key}' => [\n{$val}";
        $result .= "    ],\n";
    }

    return "[\n{$result}]";
}

function extensionsExporter(array $array) :string {
    $result = "";

    foreach ($array as $key => $value) {
        $result .= "    '{$key}' =>  ['" . implode("','", $value) . "'],\n";
    }

    return "[\n{$result}]";
}
