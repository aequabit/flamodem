<?php

//
// Flamodem - Self-hosting for Flameshot
// from Taube for AURUM
//
// Version 0.0.1
//

function dbgprint($var, string $description = ""): void {
     ob_start();
     if (!empty($description))
          echo $description . ": ";
     var_dump($var);
     error_log(ob_get_clean(), 4);
}

function respond(int $status, array $body = []): void {
     http_response_code($status);
     die(json_encode($body, JSON_FORCE_OBJECT));
} 

const FLAMODEM_CONFIG_PATH = __DIR__ . "/flamodem.config.php";
if (!file_exists(FLAMODEM_CONFIG_PATH)) {
     http_response_code(500);
     die("Please read the documentation first.");
}
require_once FLAMODEM_CONFIG_PATH;

//

function random_string($length = RANDOM_FILENAME_LENGTH): string {
     $result = "";
     for ($i = 0; $i < $length; $i++)
         $result .= RANDOM_FILENAME_CHARSET[random_int(0, strlen(RANDOM_FILENAME_CHARSET) - 1)];
     return $result;
}

function random_filename(): string {
     $filename = "";
     do {
          $filename = random_string() . ".png";
     } while (is_file(UPLOAD_DIR . "/" . $filename));

     return $filename;
}

function sanitize_filename(string $filename): string | false {
     $filename = str_replace([" ", "/"], "_", $filename); // Replace evil characters
     $filename = trim($filename, "."); // Trim remaining evil characters

     // Check for more evil
     if (str_ends_with($filename, ".php"))
          return false;

     // TODO: Not sure if this is a good limit
     if (strlen($filename) < 1)
          return false;
          
     $filePath = UPLOAD_DIR . "/" . $filename;

     // Target file path isn't within the upload directory
     if (realpath(UPLOAD_DIR) !== realpath(dirname($filePath)))
          return false;

     // Force .png extension
     if (!str_ends_with($filename, ".png"))
          $filename .= ".png";

     return $filename;
}

//

if (ENABLE_DEBUG) {
     ini_set("display_errors", 1);
     error_reporting(E_ALL);
}

header("Content-Type: application/json");

// TODO: This will work for now
if (empty(BASE_URL) || empty(AUTH_TOKEN) || empty(DELETE_TOKEN_SEED) || !is_dir(UPLOAD_DIR))
     respond(500, ["error" => "Application not configured."]);

if (!is_writable(UPLOAD_DIR))
     respond(500, ["error" => "Upload directory is not writable."]);

$method = $_SERVER["REQUEST_METHOD"];

$urlParsed = parse_url(trim($_SERVER["REQUEST_URI"], "/"));
if (!$urlParsed || !array_key_exists("path", $urlParsed))
     respond(400, ["error" => "Could not parse request URL."]);

$route = empty($urlParsed["path"]) 
     ? "/"
     : explode("/", $urlParsed["path"])[0];

$query = [];
if (array_key_exists("query", $urlParsed))
     parse_str($urlParsed["query"], $query);

if ($method === "POST" && $route === "upload") {
     if (!array_key_exists("HTTP_X_AUTH_TOKEN", $_SERVER))
          respond(401, ["error" => "No authentication token provided."]);
     if ($_SERVER["HTTP_X_AUTH_TOKEN"] !== AUTH_TOKEN)
          respond(401, ["error" => "Invalid authentication token."]);
     if ($_SERVER["CONTENT_TYPE"] !== "application/x-www-form-urlencoded")
          respond(415, ["error" => "Unsupported media type."]);

     $imageData = file_get_contents("php://input");

     if (array_key_exists("filename", $query)) {
          if (!($filename = sanitize_filename($query["filename"])))
               respond(400, ["error" => "Malformed filename."]);

          // In case the file already exists, append miliseconds since epoch
          if (is_file(UPLOAD_DIR . "/" . $filename)) {
               // Guaranteed to have a .png extension
               $filenameNoExt = substr($filename, 0, strlen($filename) - 4);
               [$epochMs, $epochSec] = explode(" ", microtime());
               $epochMs = str_replace("0.", "", $epochMs);
               $filename = $filenameNoExt . "_" . $epochSec . $epochMs . ".png";
          }
     } else
          $filename = random_filename();

     $deleteToken = hash("sha1", DELETE_TOKEN_SEED . $filename);

     file_put_contents(__DIR__ . "/" . $filename, file_get_contents("php://input"));

     respond(201, ["url" => BASE_URL . "/" . $filename, "delete_token" => $deleteToken]);
} else if ($method === "GET" && $route === "delete") {
     if (!array_key_exists("filename", $query))
          respond(400, ["error" => "No filename provided."]);
     if (!array_key_exists("token", $query))
          respond(401, ["error" => "No deletion token provided."]);
     if (!($filename = sanitize_filename($query["filename"])))
          respond(400, ["error" => "Malformed filename."]);

     $deleteToken = hash("sha1", DELETE_TOKEN_SEED . $filename);
     if ($query["token"] !== $deleteToken)
          respond(401, ["error" => "Invalid deletion token."]);

     $filePath = UPLOAD_DIR . "/" . $filename;
     if (!is_file($filePath))
          respond(400, ["error" => "Image does not exist."]);

     try {
          unlink($filePath);
     } catch (Exception $ex) {
          respond(500, ["error" => "Could not delete the image. Contact the server administrator."]);
     }

     respond(200);
}

respond(404, ["error" => "Not found"]);
